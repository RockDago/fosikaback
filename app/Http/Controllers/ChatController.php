<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Vérifier les informations d'un signalement (public)
     * Route: throttle:public (200/min)
     */
    public function checkReference(Request $request)
    {
        $request->validate([
            'reference' => 'required|exists:reports,reference'
        ]);

        $dossier = Report::where('reference', $request->reference)->first();

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'dossier' => [
                'reference' => $dossier->reference,
                'name' => $dossier->name,
                'email' => $dossier->email,
                'phone' => $dossier->phone,
                'type' => $dossier->type,
                'type_label' => $dossier->getTypeLabel('fr'),
                'category' => $dossier->category,
                'category_label' => $dossier->getCategoryLabel('fr'),
                'description' => $dossier->description,
                'status' => $dossier->status,
                'status_label' => $dossier->getStatusLabel('fr'),
                'workflow' => $dossier->workflow,
                'created_at' => $dossier->created_at->format('d/m/Y H:i'),
                'has_files' => $dossier->hasFiles(),
                'files_count' => $dossier->files_count,
                'address' => $dossier->address,
                'assigned_to' => $dossier->assigned_to,
                'assigned_user' => $dossier->assignedUser ? [
                    'name' => $dossier->assignedUser->name,
                    'email' => $dossier->assignedUser->email
                ] : null
            ],
        ]);
    }

    /**
     * Vérifier si un chat existe pour une référence donnée
     * Route: throttle:public (200/min)
     */
    public function checkChatByReference($reference)
    {
        try {
            // ✅ OPTIMISATION: Cache de 30 secondes pour éviter trop de requêtes DB
            $cacheKey = "chat_exists_{$reference}";
            
            return Cache::remember($cacheKey, 30, function() use ($reference) {
                // Vérifier si le signalement existe
                $report = Report::where('reference', $reference)->first();

                if (!$report) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Signalement non trouvé',
                    ], 404);
                }

                // Chercher un chat actif pour ce signalement
                $chat = Chat::where('reference', $reference)
                    ->where('status', 'active')
                    ->where('type', 'support')
                    ->first();

                if ($chat) {
                    return response()->json([
                        'success' => true,
                        'exists' => true,
                        'chatid' => $chat->id,
                        'reference' => $chat->reference,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'exists' => false,
                    'reference' => $reference,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Erreur checkChatByReference: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
            ], 500);
        }
    }

    /**
     * Créer un chat de support (pour les visiteurs)
     * Route: throttle:public (200/min)
     */
    public function createSupportChat(Request $request)
    {
        try {
            $request->validate([
                'reference' => 'required|exists:reports,reference',
                'message' => 'nullable|string|max:1000',
            ]);

            $report = Report::where('reference', $request->reference)->first();

            // Vérifier si un chat existe déjà
            $existingChat = Chat::where('reference', $report->reference)
                ->where('status', 'active')
                ->first();

            if ($existingChat) {
                // ✅ CORRECTION : Ne pas ajouter de message vide
                if ($request->message && trim($request->message) !== '') {
                    Message::create([
                        'chat_id' => $existingChat->id,
                        'sender_id' => null,
                        'sendername' => $request->name ?? $report->name ?? 'Visiteur',
                        'senderemail' => $report->email,
                        'content' => trim($request->message),
                        'type' => 'text',
                        'reference' => $report->reference,
                        'is_public' => true,
                        'status' => 'sent',
                    ]);

                    $existingChat->update(['last_message_at' => now()]);
                    
                    // ✅ OPTIMISATION: Invalider le cache
                    Cache::forget("chat_exists_{$report->reference}");
                }

                return response()->json([
                    'success' => true,
                    'chatid' => $existingChat->id,
                    'is_new' => false,
                    'message' => $request->message ? 'Message ajouté au chat existant' : 'Chat existant trouvé',
                ]);
            }

            // ✅ CORRECTION : Ne créer le chat QUE si un message est fourni et non vide
            if (!$request->message || trim($request->message) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Un message est requis pour démarrer la conversation',
                ], 400);
            }

            // Créer un nouveau chat
            $chat = Chat::create([
                'reference' => $report->reference,
                'title' => "Support - {$report->reference}",
                'type' => 'support',
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            // Ajouter le premier message (on sait qu'il existe et n'est pas vide)
            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => null,
                'sendername' => $request->name ?? $report->name ?? 'Visiteur',
                'senderemail' => $report->email,
                'content' => trim($request->message),
                'type' => 'text',
                'reference' => $report->reference,
                'is_public' => true,
                'status' => 'sent',
            ]);

            // ✅ OPTIMISATION: Invalider le cache
            Cache::forget("chat_exists_{$report->reference}");

            return response()->json([
                'success' => true,
                'chatid' => $chat->id,
                'reference' => $chat->reference,
                'is_new' => true,
                'message' => 'Chat créé avec succès',
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur createSupportChat: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne',
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut en ligne du visiteur
     * Route: throttle:public (200/min)
     * ✅ OPTIMISATION: Réduit les appels à cette méthode
     */
    public function updateVisitorOnlineStatus(Request $request, $chatId)
    {
        $request->validate([
            'is_online' => 'required|boolean',
            'reference' => 'required|string',
        ]);

        try {
            $chat = Chat::where('id', $chatId)
                ->where('reference', $request->reference)
                ->where('status', 'active')
                ->firstOrFail();

            $cacheKey = "visitor_online_{$chat->reference}";

            if ($request->is_online) {
                // ✅ OPTIMISATION: Cache de 3 minutes au lieu de 2
                Cache::put($cacheKey, [
                    'reference' => $chat->reference,
                    'last_seen' => now(),
                    'is_online' => true,
                ], now()->addMinutes(3));
            } else {
                Cache::forget($cacheKey);
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
            ], 500);
        }
    }

    /**
     * Vérifier le statut en ligne d'un visiteur
     * Route: throttle:chat (500/min)
     * ✅ OPTIMISATION: Cette méthode est appelée fréquemment par le polling
     */
    public function getVisitorOnlineStatus($chatId)
    {
        try {
            // ✅ OPTIMISATION: Cache court pour réduire la charge DB
            $cacheKey = "visitor_status_check_{$chatId}";
            
            return Cache::remember($cacheKey, 10, function() use ($chatId) {
                $chat = Chat::findOrFail($chatId);

                if (!$chat->reference) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chat non trouvé',
                    ], 404);
                }

                $onlineKey = "visitor_online_{$chat->reference}";
                $onlineData = Cache::get($onlineKey);

                if ($onlineData) {
                    return response()->json([
                        'success' => true,
                        'isonline' => true,
                        'lastseen' => $onlineData['last_seen'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'isonline' => false,
                    'lastseen' => null,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Envoyer un message depuis un chat public (visiteur)
     * Route: throttle:public (200/min)
     */
    public function sendPublicMessage(Request $request, $chatId)
    {
        \Log::info('=== DÉBUT sendPublicMessage ===');
        \Log::info('Chat ID: ' . $chatId);
        \Log::info('Request all:', $request->all());
        \Log::info('Request files:', $request->allFiles());
        \Log::info('Has file:', [$request->hasFile('file')]);
        \Log::info('Type:', [$request->type]);

        $request->validate([
            'content' => 'required_without:file|string|max:1000',
            'type' => 'required|in:text,file,image,video',
            'sendername' => 'nullable|string|max:255',
            'senderemail' => 'nullable|email',
            'file' => 'required_if:type,file,image,video|file|max:25600',
        ]);

        try {
            $chat = Chat::where('status', 'active')->findOrFail($chatId);

            DB::beginTransaction();

            $messageData = [
                'chat_id' => $chat->id,
                'sender_id' => null,
                'sendername' => $request->sendername ?? 'Visiteur',
                'senderemail' => $request->senderemail,
                'type' => $request->type,
                'reference' => $chat->reference,
                'is_public' => true,
                'status' => 'sent',
            ];

            \Log::info('Message data avant upload:', $messageData);

            if ($request->hasFile('file') && in_array($request->type, ['file', 'image', 'video'])) {
                $file = $request->file('file');

                \Log::info('Fichier reçu:', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ]);

                if ($request->type === 'image') {
                    $request->validate([
                        'file' => 'image|mimes:jpeg,jpg,png,gif,webp|max:25600'
                    ]);
                } elseif ($request->type === 'video') {
                    $request->validate([
                        'file' => 'mimes:mp4,mov,avi,wmv|max:25600'
                    ]);
                } else {
                    $request->validate([
                        'file' => 'mimes:pdf,doc,docx,txt,xlsx,xls|max:25600'
                    ]);
                }

                $filename = Str::uuid() . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs("chat_files/{$chat->id}", $filename, 'public');

                \Log::info('Fichier stocké:', [
                    'path' => $path,
                    'filename' => $filename,
                    'full_path' => storage_path('app/public/' . $path),
                ]);

                $messageData['content'] = $request->content ?? (
                $request->type === 'image' ? '📷 Image' :
                    ($request->type === 'video' ? '🎥 Vidéo' : '📎 Fichier')
                );

                $messageData['filepath'] = $path;
                $messageData['filename'] = $file->getClientOriginalName();
                $messageData['filesize'] = $file->getSize();
                $messageData['filetype'] = $file->getMimeType();
            } else {
                \Log::info('Pas de fichier reçu, message texte seulement');
                $messageData['content'] = $request->content;
            }

            \Log::info('Message data final:', $messageData);

            $message = Message::create($messageData);

            \Log::info('Message créé:', [
                'id' => $message->id,
                'filepath' => $message->filepath,
                'file_path' => $message->file_path,
            ]);

            $chat->update(['last_message_at' => now()]);

            $cacheKey = "visitor_online_chat_{$chat->reference}";
            Cache::put($cacheKey, [
                'reference' => $chat->reference,
                'last_seen' => now(),
                'is_online' => true,
            ], now()->addMinutes(2));

            // ✅ OPTIMISATION: Invalider les caches pertinents
            Cache::forget("chat_details_{$chat->id}");
            Cache::forget("visitor_status_check_{$chat->id}");

            DB::commit();

            \Log::info('=== FIN sendPublicMessage SUCCESS ===');

            $fileInfo = null;
            if ($message->filepath) {
                $fileInfo = [
                    'name' => $message->filename,
                    'size' => $this->formatFileSize($message->filesize),
                    'filesize' => $message->filesize,
                    'type' => $message->filetype,
                    'url' => url('api/files/public/' . basename($message->filepath)),
                    'preview' => $message->type === 'image' ? url('api/files/public/' . basename($message->filepath)) : null,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'text' => $message->content,
                    'content' => $message->content,
                    'sender' => 'visitor',
                    'sendername' => $message->sendername,
                    'time' => $this->formatTime($message->created_at),
                    'createdat' => $message->created_at,
                    'type' => $message->type,
                    'status' => $message->status,
                    'fileinfo' => $fileInfo,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERREUR sendPublicMessage ===');
            \Log::error('Message: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Erreur interne',
            ], 500);
        }
    }

    /**
     * Voir une conversation en lecture seule (pour visiteurs)
     * Route: throttle:public (200/min)
     * ✅ OPTIMISATION: Cache ajouté
     */
    public function showPublic($id)
    {
        // ✅ OPTIMISATION: Cache de 5 secondes pour les visiteurs
        $cacheKey = "chat_public_view_{$id}";
        
        return Cache::remember($cacheKey, 5, function() use ($id) {
            $chat = Chat::with(['report', 'messages' => function($q) {
                $q->where('is_public', true)->orderBy('created_at', 'asc');
            }])->findOrFail($id);

            $report = $chat->report;
            $isAnonymous = empty($report->name) || $report->name === 'Anonyme';
            $visitorName = $report->name ?? 'Visiteur';

            return response()->json([
                'success' => true,
                'chat' => [
                    'id' => $chat->id,
                    'reference' => $chat->reference,
                    'report_title' => $report ? ($report->getTypeLabel('fr') . ' - ' . $report->getCategoryLabel('fr')) : 'Signalement',
                    'messages' => $chat->messages->map(function($message) use ($isAnonymous, $visitorName) {
                        $fileInfo = null;
                        if ($message->filepath) {
                            $fileInfo = [
                                'name' => $message->filename,
                                'size' => $this->formatFileSize($message->filesize),
                                'filesize' => $message->filesize,
                                'type' => $message->filetype,
                                'url' => url('api/files/public/' . basename($message->filepath)),
                                'preview' => $message->type === 'image' ? url('api/files/public/' . basename($message->filepath)) : null,
                            ];
                        }

                        return [
                            'id' => $message->id,
                            'text' => $message->content,
                            'content' => $message->content,
                            'time' => $this->formatTime($message->created_at),
                            'createdat' => $message->created_at,
                            'sender' => $message->sender_id ? 'support' : 'visitor',
                            'sendername' => $message->sender_id
                                ? ($message->sender ? $message->sender->full_name : 'Support')
                                : ($isAnonymous ? 'Anonyme' : $visitorName),
                            'isanonymous' => !$message->sender_id && $isAnonymous,
                            'status' => $message->status,
                            'readat' => $message->read_at,
                            'deliveredat' => $message->delivered_at,
                            'type' => $message->type,
                            'fileinfo' => $fileInfo,
                        ];
                    }),
                    'is_active' => $chat->status === 'active',
                    'created_at' => $chat->created_at->format('d/m/Y H:i'),
                ]
            ]);
        });
    }

    /**
     * Marquer les messages du support comme lus (pour visiteurs)
     * Route: throttle:public (200/min)
     */
    public function markPublicAsRead($id)
    {
        try {
            $chat = Chat::where('status', 'active')->findOrFail($id);

            $chat->messages()
                ->whereNotNull('sender_id')
                ->where(function($q) {
                    $q->whereNull('read_at')
                        ->orWhere('status', '!=', 'read');
                })
                ->update([
                    'read_at' => now(),
                    'status' => 'read',
                    'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())')
                ]);

            // ✅ OPTIMISATION: Invalider le cache
            Cache::forget("chat_public_view_{$id}");

            return response()->json([
                'success' => true,
                'message' => 'Messages marqués comme lus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * ✅ OPTIMISATION : Récupérer les conversations récentes (publiques)
     * Route: throttle:public (200/min)
     */
    public function getRecentPublicChats()
    {
        try {
            // ✅ OPTIMISATION: Cache de 30 secondes
            return Cache::remember('recent_public_chats', 30, function() {
                $chats = Chat::where('status', 'active')
                    ->where('type', 'support')
                    ->with(['report'])
                    ->orderBy('last_message_at', 'DESC')
                    ->limit(5)
                    ->get();

                return response()->json([
                    'success' => true,
                    'chats' => $chats->map(function($chat) {
                        // Récupérer le dernier message
                        $lastMessage = DB::table('messages')
                            ->where('chat_id', $chat->id)
                            ->orderBy('created_at', 'DESC')
                            ->limit(1)
                            ->first();
                        
                        return [
                            'id' => $chat->id,
                            'reference' => $chat->reference,
                            'report_title' => $chat->report ?
                                ($chat->report->getTypeLabel('fr') . ' - ' . $chat->report->getCategoryLabel('fr'))
                                : 'Signalement',
                            'lastMessage' => $lastMessage ? $lastMessage->content : 'Pas de message',
                            'lastMessageAt' => $chat->last_message_at ?
                                $this->formatTime($chat->last_message_at) : null,
                            'messageCount' => DB::table('messages')->where('chat_id', $chat->id)->count(),
                        ];
                    })
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Erreur getRecentPublicChats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    public function servePublicFile($filename)
    {
        $filename = basename($filename);

        $base = storage_path('app/public/chat_files');
        if (!is_dir($base)) {
            return response()->json([
                'error' => 'Dossier chat_files introuvable',
                'path'  => $base,
            ], 404);
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return response()->file($file->getRealPath(), [
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        return response()->json([
            'error' => "Erreur lors de l'accès au fichier",
            'message' => 'Fichier non trouvé: ' . $filename,
        ], 404);
    }

    // =========================================
    // MÉTHODES PROTÉGÉES (AVEC AUTH)
    // Route: throttle:chat (500/min)
    // =========================================

    /**
     * ✅ OPTIMISATION : Récupérer toutes les conversations d'un utilisateur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');

        // ✅ OPTIMISATION: Cache de 3 secondes pour limiter les requêtes DB
        $cacheKey = "user_chats_{$user->id}_{$filter}";
        
        return Cache::remember($cacheKey, 3, function() use ($user, $filter) {
            $query = Chat::forUser($user->id)
                ->with(['report', 'participants.user'])
                ->orderBy('last_message_at', 'DESC');

            if ($filter === 'unread') {
                $query->withUnread($user->id);
            } elseif ($filter === 'important') {
                $query->important();
            }

            $chats = $query->get()->map(function($chat) use ($user) {
                return $this->formatChatForList($chat, $user);
            });

            return response()->json([
                'success' => true,
                'chats' => $chats,
                'filters' => [
                    'all' => Chat::forUser($user->id)->count(),
                    'unread' => Chat::forUser($user->id)->withUnread($user->id)->count(),
                    'important' => Chat::forUser($user->id)->important()->count(),
                ]
            ]);
        });
    }

    /**
     * Récupérer une conversation spécifique
     * ✅ OPTIMISATION: Cache de 2 secondes
     */
    public function show($id)
    {
        $user = Auth::user();

        // ✅ OPTIMISATION: Cache court
        $cacheKey = "chat_details_{$id}_{$user->id}";
        
        $result = Cache::remember($cacheKey, 2, function() use ($id, $user) {
            $chat = Chat::with(['report', 'messages' => function($q) {
                $q->orderBy('created_at', 'asc');
            }, 'messages.sender', 'participants.user'])
                ->forUser($user->id)
                ->findOrFail($id);

            return [
                'success' => true,
                'chat' => $this->formatChatForDetail($chat, $user->id),
            ];
        });

        // Marquer comme lu (hors cache)
        $chat = Chat::findOrFail($id);
        $chat->messages()
            ->whereNull('sender_id')
            ->where(function($q) {
                $q->where('status', 'sent')
                    ->orWhere('status', 'delivered');
            })
            ->update([
                'status' => 'read',
                'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
                'read_at' => now()
            ]);

        return response()->json($result);
    }

    /**
     * Envoyer un message (utilisateurs authentifiés)
     */
    public function sendMessage(Request $request, $chatId)
    {
        $user = Auth::user();

        $request->validate([
            'content' => 'required_if:type,text|string',
            'type' => 'required|in:text,file,image',
            'file' => 'required_if:type,file,image|file|max:5120',
        ]);

        $chat = Chat::forUser($user->id)->findOrFail($chatId);

        DB::beginTransaction();

        try {
            $messageData = [
                'chat_id' => $chat->id,
                'sender_id' => $user->id,
                'type' => $request->type,
                'reference' => $chat->reference,
                'is_public' => true,
                'status' => 'sent',
            ];

            if ($request->type === 'text') {
                $messageData['content'] = $request->content;
            } else {
                $file = $request->file('file');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('chat_files/' . $chat->id, $filename, 'public');

                $messageData['content'] = $request->type === 'image'
                    ? '🖼️ Image jointe'
                    : '📎 Fichier joint';
                $messageData['filename'] = $file->getClientOriginalName();
                $messageData['filepath'] = $path;
                $messageData['filesize'] = $file->getSize();
                $messageData['filetype'] = $file->getMimeType();
            }

            $message = Message::create($messageData);
            $chat->update(['last_message_at' => now()]);

            // ✅ OPTIMISATION: Invalider les caches
            Cache::forget("chat_details_{$chat->id}_{$user->id}");
            Cache::forget("user_chats_{$user->id}_all");
            Cache::forget("user_chats_{$user->id}_unread");
            Cache::forget("user_chats_{$user->id}_important");
            Cache::forget("chat_public_view_{$chat->id}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $this->formatMessage($message, $user->id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload un fichier
     */
    public function uploadFile(Request $request, $chatId)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|file|max:10240',
            'type' => 'required|in:document,image,video',
        ]);

        $chat = Chat::forUser($user->id)->findOrFail($chatId);
        $file = $request->file('file');

        if ($request->type === 'image') {
            $request->validate([
                'file' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120'
            ]);
        } elseif ($request->type === 'video') {
            $request->validate([
                'file' => 'mimes:mp4,mov,avi,wmv|max:10240'
            ]);
        } else {
            $request->validate([
                'file' => 'mimes:pdf,doc,docx,txt,xlsx,xls|max:10240'
            ]);
        }

        DB::beginTransaction();

        try {
            $filename = Str::uuid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("chat_files/{$chat->id}", $filename, 'public');

            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $user->id,
                'content' => $request->type === 'image' ? '📷 Image jointe' : 
                            ($request->type === 'video' ? '🎥 Vidéo jointe' : '📎 Fichier joint'),
                'type' => $request->type === 'image' ? 'image' : ($request->type === 'video' ? 'video' : 'file'),
                'reference' => $chat->reference,
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'filesize' => $file->getSize(),
                'filetype' => $file->getMimeType(),
                'is_public' => true,
                'status' => 'sent',
            ]);

            $chat->update(['last_message_at' => now()]);

            // ✅ OPTIMISATION: Invalider les caches
            Cache::forget("chat_details_{$chat->id}_{$user->id}");
            Cache::forget("user_chats_{$user->id}_all");
            Cache::forget("chat_public_view_{$chat->id}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $this->formatMessage($message, $user->id),
                'fileinfo' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $this->formatFileSize($file->getSize()),
                    'type' => $file->getMimeType(),
                    'url' => url('api/files/public/' . basename($path)),
                    'preview' => $request->type === 'image' ? url('api/files/public/' . basename($path)) : null,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur uploadFile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du fichier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marquer/Retirer important
     */
    public function toggleImportant($id)
    {
        $user = Auth::user();
        $chat = Chat::forUser($user->id)->findOrFail($id);

        $isImportant = $chat->toggleImportant();

        // ✅ OPTIMISATION: Invalider le cache
        Cache::forget("user_chats_{$user->id}_all");
        Cache::forget("user_chats_{$user->id}_important");

        return response()->json([
            'success' => true,
            'isimportant' => $isImportant,
            'message' => $isImportant
                ? 'Conversation marquée comme importante'
                : 'Conversation retirée des importantes',
        ]);
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $chat = Chat::forUser($user->id)->findOrFail($id);

        $chat->messages()
            ->whereNull('sender_id')
            ->where(function($q) {
                $q->whereNull('read_at')
                    ->orWhere('status', '!=', 'read');
            })
            ->update([
                'read_at' => now(),
                'status' => 'read',
                'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())')
            ]);

        // ✅ OPTIMISATION: Invalider le cache
        Cache::forget("user_chats_{$user->id}_all");
        Cache::forget("user_chats_{$user->id}_unread");
        Cache::forget("chat_details_{$id}_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Conversation marquée comme lue',
        ]);
    }

    /**
     * Créer un chat Admin
     */
    public function createAdminChat(Request $request)
    {
        $adminId = Auth::id() ?? auth('sanctum')->id();
        $adminUser = Auth::user() ?? auth('sanctum')->user();

        if (!$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé : Vous devez être connecté en tant qu\'administrateur.'
            ], 401);
        }

        $request->validate([
            'reference' => 'required|string',
            'message' => 'required|string'
        ]);

        $reference = $request->reference;
        $content = $request->message;

        try {
            DB::beginTransaction();

            $existingChat = Chat::where('reference', $reference)
                ->where('status', 'active')
                ->first();

            if ($existingChat) {
                Message::create([
                    'chat_id' => $existingChat->id,
                    'sender_id' => $adminId,
                    'sendername' => $adminUser->name ?? 'Support',
                    'content' => $content,
                    'type' => 'text',
                    'reference' => $reference,
                    'is_public' => true,
                    'status' => 'sent',
                ]);

                $existingChat->update(['last_message_at' => now()]);

                // ✅ OPTIMISATION: Invalider les caches
                Cache::forget("chat_details_{$existingChat->id}_{$adminId}");
                Cache::forget("chat_public_view_{$existingChat->id}");
                Cache::forget("user_chats_{$adminId}_all");

                DB::commit();

                $existingChat->load(['messages' => function($q) {
                    $q->orderBy('created_at', 'asc');
                }]);

                return response()->json([
                    'success' => true,
                    'chat' => $this->formatChatForDetail($existingChat, $adminId),
                    'message' => 'Message ajouté'
                ]);
            }

            $chat = Chat::create([
                'reference' => $reference,
                'title' => "Support - {$reference}",
                'type' => 'support',
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $adminId,
                'sendername' => $adminUser->name ?? 'Support',
                'content' => $content,
                'type' => 'text',
                'reference' => $reference,
                'is_public' => true,
                'status' => 'sent',
            ]);

            // ✅ OPTIMISATION: Invalider le cache
            Cache::forget("user_chats_{$adminId}_all");
            Cache::forget("chat_exists_{$reference}");

            DB::commit();

            $chat->load(['messages' => function($q) {
                $q->orderBy('created_at', 'asc');
            }]);

            return response()->json([
                'success' => true,
                'chat' => $this->formatChatForDetail($chat, $adminId),
                'message' => 'Conversation créée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Erreur createAdminChat: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur création: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================
    // MÉTHODES PRIVÉES D'AIDE
    // =========================================

    private function formatChatForList($chat, $user)
    {
        if ($chat->type === 'support' && $chat->reference) {
            $report = $chat->report;
            $visitorName = $report->name ?? 'Visiteur';
            $isAnonymous = empty($report->name) || $report->name === 'Anonyme';

            $lastMessage = DB::table('messages')
                ->where('chat_id', $chat->id)
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->first();

            $unreadCount = $chat->messages()
                ->whereNull('sender_id')
                ->whereNull('read_at')
                ->count();

            $cacheKey = "visitor_online_{$chat->reference}";
            $onlineData = Cache::get($cacheKey);
            $isOnline = $onlineData ? true : false;
            $lastSeen = $onlineData ? $onlineData['last_seen'] : null;

            return [
                'id' => $chat->id,
                'name' => $isAnonymous ? 'Anonyme' : $visitorName,
                'visitorname' => $isAnonymous ? 'Anonyme' : $visitorName,
                'role' => 'Visiteur',
                'location' => null,
                'status' => $isOnline ? 'online' : 'offline',
                'isonline' => $isOnline,
                'lastseen' => $lastSeen,
                'avatar' => $isAnonymous
                    ? 'https://ui-avatars.com/api/?name=Anonyme&background=94a3b8&color=fff'
                    : 'https://ui-avatars.com/api/?name=' . urlencode($visitorName) . '&background=3b82f6&color=fff',
                'lastmessage' => $lastMessage ? $lastMessage->content : 'Nouveau message',
                'lastMessage' => $lastMessage ? $lastMessage->content : 'Nouveau message',
                'lastmessagetime' => $lastMessage ? $lastMessage->created_at : $chat->created_at,
                'time' => $lastMessage ? $this->formatTime($lastMessage->created_at) : '',
                'unread' => $unreadCount,
                'unreadcount' => $unreadCount,
                'important' => $chat->is_important,
                'reference' => $chat->reference,
                'isanonymous' => $isAnonymous,
                'dossierTitre' => $report ? ($report->getTypeLabel('fr') . ' - ' . $report->getCategoryLabel('fr')) : 'Signalement',
                'sharedFiles' => $chat->messages()->whereIn('type', ['file', 'image', 'video'])->count(),
            ];
        }

        $otherParticipant = $chat->participants
            ->where('user_id', '!=', $user->id)
            ->first()
            ?->user;

        $lastMessage = DB::table('messages')
            ->where('chat_id', $chat->id)
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->first();

        return [
            'id' => $chat->id,
            'name' => $otherParticipant ? $otherParticipant->full_name : 'Support FOSIKA',
            'role' => $otherParticipant ? $otherParticipant->formatted_role : 'Support',
            'location' => $otherParticipant?->departement ?? 'FOSIKA',
            'status' => $otherParticipant?->status ?? 'active',
            'isonline' => false,
            'lastseen' => null,
            'avatar' => $otherParticipant ? $otherParticipant->avatar_url :
                'https://ui-avatars.com/api/?name=Support+FOSIKA&background=4c7026&color=fff',
            'lastmessage' => $lastMessage ? $lastMessage->content : '',
            'lastMessage' => $lastMessage ? $lastMessage->content : '',
            'lastmessagetime' => $lastMessage ? $lastMessage->created_at : $chat->created_at,
            'time' => $lastMessage ? $this->formatTime($lastMessage->created_at) : '',
            'unread' => $chat->getUnreadCountForUser($user->id),
            'unreadcount' => $chat->getUnreadCountForUser($user->id),
            'important' => $chat->is_important,
            'reference' => $chat->reference,
            'isanonymous' => false,
            'dossierTitre' => $chat->report ? ($chat->report->getTypeLabel('fr') . ' - ' . $chat->report->getCategoryLabel('fr')) : 'Signalement',
            'sharedFiles' => $chat->messages()->whereIn('type', ['file', 'image', 'video'])->count(),
        ];
    }

    private function formatChatForDetail($chat, $userId = null)
    {
        $user = $userId ? User::find($userId) : Auth::user();

        if ($chat->type === 'support' && $chat->reference) {
            $report = $chat->report;
            $visitorName = $report->name ?? 'Visiteur';
            $isAnonymous = empty($report->name) || $report->name === 'Anonyme';

            $addressParts = array_filter([
                $report->address,
                $report->city,
                $report->province,
                $report->region
            ]);
            $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : null;

            $cacheKey = "visitor_online_{$chat->reference}";
            $onlineData = Cache::get($cacheKey);
            $isOnline = $onlineData ? true : false;
            $lastSeen = $onlineData ? $onlineData['last_seen'] : null;

            return [
                'id' => $chat->id,
                'name' => $isAnonymous ? 'Anonyme' : $visitorName,
                'visitorname' => $isAnonymous ? 'Anonyme' : $visitorName,
                'role' => 'Visiteur',
                'location' => null,
                'status' => $isOnline ? 'En ligne' : 'Hors ligne',
                'isonline' => $isOnline,
                'lastseen' => $lastSeen,
                'avatar' => $isAnonymous
                    ? 'https://ui-avatars.com/api/?name=Anonyme&background=94a3b8&color=fff'
                    : 'https://ui-avatars.com/api/?name=' . urlencode($visitorName) . '&background=3b82f6&color=fff',
                'email' => $report->email ?? 'Non renseigné',
                'phone' => $report->phone ?? 'Non renseigné',
                'address' => $fullAddress,
                'city' => $report->city,
                'province' => $report->province,
                'region' => $report->region,
                'reference' => $chat->reference,
                'isanonymous' => $isAnonymous,
                'dossierTitre' => $report ? ($report->getTypeLabel('fr') . ' - ' . $report->getCategoryLabel('fr')) : 'Signalement',
                'isimportant' => $chat->is_important,
                'messages' => $chat->messages->map(function($message) use ($user, $isAnonymous, $visitorName) {
                    return $this->formatMessage($message, $user ? $user->id : null, $isAnonymous, $visitorName);
                }),
                'sharedFiles' => $chat->messages()
                    ->whereIn('type', ['file', 'image', 'video'])
                    ->get()
                    ->map(function($message) {
                        return [
                            'name' => $message->filename,
                            'type' => $message->type === 'image' ? 'image' : ($message->type === 'video' ? 'video' : 'pdf'),
                            'size' => $this->formatFileSize($message->filesize),
                            'time' => $this->formatTime($message->created_at),
                        ];
                    }),
            ];
        }

        $otherParticipant = $chat->participants
            ->where('user_id', '!=', $user ? $user->id : null)
            ->first()
            ?->user;

        return [
            'id' => $chat->id,
            'name' => $otherParticipant ? $otherParticipant->full_name : 'Support FOSIKA',
            'role' => $otherParticipant ? $otherParticipant->formatted_role : 'Support',
            'location' => $otherParticipant?->departement ?? 'FOSIKA',
            'status' => 'Active Now',
            'isonline' => false,
            'lastseen' => null,
            'avatar' => $otherParticipant ? $otherParticipant->avatar_url :
                'https://ui-avatars.com/api/?name=Support+FOSIKA&background=4c7026&color=fff',
            'email' => $otherParticipant?->email ?? 'support@fosika.mg',
            'phone' => $otherParticipant?->phone ?? '+261 XX XX XXX XX',
            'address' => null,
            'reference' => $chat->reference,
            'isanonymous' => false,
            'dossierTitre' => $chat->report ? ($chat->report->getTypeLabel('fr') . ' - ' . $chat->report->getCategoryLabel('fr')) : 'Signalement',
            'isimportant' => $chat->is_important,
            'messages' => $chat->messages->map(function($message) use ($user) {
                return $this->formatMessage($message, $user ? $user->id : null);
            }),
            'sharedFiles' => $chat->messages()
                ->whereIn('type', ['file', 'image', 'video'])
                ->get()
                ->map(function($message) {
                    return [
                        'name' => $message->filename,
                        'type' => $message->type === 'image' ? 'image' : ($message->type === 'video' ? 'video' : 'pdf'),
                        'size' => $this->formatFileSize($message->filesize),
                        'time' => $this->formatTime($message->created_at),
                    ];
                }),
        ];
    }

    private function formatMessage($message, $userId = null, $isAnonymous = false, $visitorName = 'Visiteur')
    {
        $isMe = false;
        if ($userId && $message->sender_id === $userId) {
            $isMe = true;
        } elseif (!$message->sender_id) {
            $isMe = false;
        } elseif ($userId) {
            $isMe = ($message->sender_id === $userId);
        }

        $senderName = 'Support';
        $senderType = 'support';

        if (!$message->sender_id) {
            $senderType = 'visitor';
            $senderName = $message->sendername ?? ($isAnonymous ? 'Anonyme' : $visitorName);
        } elseif ($message->sender) {
            $senderName = $message->sender->full_name;
            $senderType = 'support';
        }

        $fileInfo = null;
        if ($message->filepath) {
            $fileInfo = [
                'name' => $message->filename,
                'size' => $this->formatFileSize($message->filesize),
                'filesize' => $message->filesize,
                'type' => $message->filetype,
                'url' => url('api/files/public/' . basename($message->filepath)),
                'preview' => $message->type === 'image' ? url('api/files/public/' . basename($message->filepath)) : null,
            ];
        }

        return [
            'id' => $message->id,
            'text' => $message->content,
            'content' => $message->content,
            'sender' => $isMe ? 'me' : ($message->sender_id ? 'support' : 'visitor'),
            'sendertype' => $senderType,
            'sendername' => $senderName,
            'time' => $this->formatTime($message->created_at),
            'createdat' => $message->created_at,
            'avatar' => $message->sender
                ? $message->sender->avatar_url
                : ($isAnonymous
                    ? 'https://ui-avatars.com/api/?name=Anonyme&background=94a3b8&color=fff'
                    : 'https://ui-avatars.com/api/?name=' . urlencode($senderName) . '&background=3b82f6&color=fff'),
            'type' => $message->type,
            'fileinfo' => $fileInfo,
            'ispublic' => $message->is_public ?? false,
            'status' => $message->status ?? 'sent',
            'deliveredat' => $message->delivered_at,
            'readat' => $message->read_at,
        ];
    }

    private function formatFileSize($bytes)
    {
        if ($bytes == 0) return "0 Bytes";

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    private function formatTime($datetime)
{
    if (!$datetime) return '';
    $date = $datetime instanceof \Carbon\Carbon ? $datetime : \Carbon\Carbon::parse($datetime);
    // Force le fuseau horaire Madagascar
    return $date->setTimezone('Indian/Antananarivo')->format('H:i');
}


}
