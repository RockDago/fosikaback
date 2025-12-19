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
     * Route: GET /api/chats/check-by-reference/{reference}
     */
    public function checkChatByReference($reference)
    {
        try {
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
                // Si un message est fourni, l'ajouter
                if ($request->message) {
                    Message::create([
                        'chat_id' => $existingChat->id,
                        'sender_id' => null,
                        'sendername' => $request->name ?? $report->name ?? 'Visiteur',
                        'senderemail' => $report->email,
                        'content' => $request->message,
                        'type' => 'text',
                        'reference' => $report->reference,
                        'is_public' => true,
                        'status' => 'sent',
                    ]);

                    $existingChat->update(['last_message_at' => now()]);
                }

                return response()->json([
                    'success' => true,
                    'chatid' => $existingChat->id,
                    'is_new' => false,
                    'message' => $request->message ? 'Message ajouté au chat existant' : 'Chat existant trouvé',
                ]);
            }

            // Créer un nouveau chat
            $chat = Chat::create([
                'reference' => $report->reference,
                'title' => "Support - {$report->reference}",
                'type' => 'support',
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            // Si un message est fourni, l'ajouter
            if ($request->message) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => null,
                    'sendername' => $request->name ?? $report->name ?? 'Visiteur',
                    'senderemail' => $report->email,
                    'content' => $request->message,
                    'type' => 'text',
                    'reference' => $report->reference,
                    'is_public' => true,
                    'status' => 'sent',
                ]);
            }

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
                Cache::put($cacheKey, [
                    'reference' => $chat->reference,
                    'last_seen' => now(),
                    'is_online' => true,
                ], now()->addMinutes(2));
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
     */
    public function getVisitorOnlineStatus($chatId)
    {
        try {
            $chat = Chat::findOrFail($chatId);

            if (!$chat->reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat non trouvé',
                ], 404);
            }

            $cacheKey = "visitor_online_{$chat->reference}";
            $onlineData = Cache::get($cacheKey);

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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Envoyer un message depuis un chat public (visiteur)
     */
    public function sendPublicMessage(Request $request, $chatId)
    {
        // ✅ LOGS DE DÉBOGAGE
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

            // ✅ LOG AVANT L'UPLOAD
            \Log::info('Message data avant upload:', $messageData);

            if ($request->hasFile('file') && in_array($request->type, ['file', 'image', 'video'])) {
                $file = $request->file('file');

                // ✅ LOG DU FICHIER
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

                // ✅ LOG DU CHEMIN
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

            // ✅ LOG AVANT L'INSERTION
            \Log::info('Message data final:', $messageData);

            $message = Message::create($messageData);

            // ✅ LOG APRÈS L'INSERTION
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

            DB::commit();

            \Log::info('=== FIN sendPublicMessage SUCCESS ===');

            // Construction de fileInfo
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
     */
    /**
     * Voir une conversation en lecture seule (pour visiteurs)
     */
    public function showPublic($id)
    {
        $chat = Chat::with(['report', 'messages' => function($q) {
            $q->where('is_public', true)->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        $report = $chat->report;
        $isAnonymous = empty($report->name) || $report->name === 'Anonyme';
        $visitorName = $report->name ?? 'Visiteur';

        // ✅ NE PAS marquer comme "delivered" automatiquement pour détecter les nouveaux messages
        // On le fera seulement quand l'utilisateur ouvre vraiment le chat

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
                        'readat' => $message->read_at,        // ✅ IMPORTANT
                        'deliveredat' => $message->delivered_at,  // ✅ IMPORTANT
                        'type' => $message->type,
                        'fileinfo' => $fileInfo,
                    ];
                }),
                'is_active' => $chat->status === 'active',
                'created_at' => $chat->created_at->format('d/m/Y H:i'),
            ]
        ]);
    }


    /**
     * Marquer les messages du support comme lus (pour visiteurs)
     */
    public function markPublicAsRead($id)
    {
        try {
            $chat = Chat::where('status', 'active')->findOrFail($id);

            // Marquer tous les messages du support non lus comme lus
            $chat->messages()
                ->whereNotNull('sender_id')  // Messages du support
                ->where(function($q) {
                    $q->whereNull('read_at')
                        ->orWhere('status', '!=', 'read');
                })
                ->update([
                    'read_at' => now(),
                    'status' => 'read',
                    'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())')
                ]);

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
     * Récupérer les conversations récentes (publiques)
     */
    public function getRecentPublicChats()
    {
        try {
            $chats = Chat::where('status', 'active')
                ->where('type', 'support')
                ->with(['report', 'messages'])
                ->latest('last_message_at')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'chats' => $chats->map(function($chat) {
                    return [
                        'id' => $chat->id,
                        'reference' => $chat->reference,
                        'report_title' => $chat->report ?
                            ($chat->report->getTypeLabel('fr') . ' - ' . $chat->report->getCategoryLabel('fr'))
                            : 'Signalement',
                        'lastMessageAt' => $chat->last_message_at ?
                            $this->formatTime($chat->last_message_at) : null,
                        'messageCount' => $chat->messages->count(),
                    ];
                })
            ]);
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
    // =========================================

    /**
     * Récupérer toutes les conversations d'un utilisateur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');

        $query = Chat::forUser($user->id)
            ->with(['report', 'participants.user'])
            ->latest('last_message_at');

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
    }

    /**
     * Récupérer une conversation spécifique
     */
    public function show($id)
    {
        $user = Auth::user();

        $chat = Chat::with(['report', 'messages' => function($q) {
            $q->orderBy('created_at', 'asc');
        }, 'messages.sender', 'participants.user'])
            ->forUser($user->id)
            ->findOrFail($id);

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

        return response()->json([
            'success' => true,
            'chat' => $this->formatChatForDetail($chat, $user->id),
        ]);
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
            'type' => 'required|in:document,image',
        ]);

        $chat = Chat::forUser($user->id)->findOrFail($chatId);
        $file = $request->file('file');

        if ($request->type === 'image') {
            $request->validate([
                'file' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120'
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
                'content' => $request->type === 'image' ? '📷 Image jointe' : '📎 Fichier joint',
                'type' => $request->type === 'image' ? 'image' : 'file',
                'reference' => $chat->reference,
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'filesize' => $file->getSize(),
                'filetype' => $file->getMimeType(),
                'is_public' => true,
                'status' => 'sent',
            ]);

            $chat->update(['last_message_at' => now()]);

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

        return response()->json([
            'success' => true,
            'message' => 'Conversation marquée comme lue',
        ]);
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
                'sharedFiles' => $chat->messages()->whereIn('type', ['file', 'image'])->count(),
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
            'sharedFiles' => $chat->messages()->whereIn('type', ['file', 'image'])->count(),
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
                    ->whereIn('type', ['file', 'image'])
                    ->get()
                    ->map(function($message) {
                        return [
                            'name' => $message->filename,
                            'type' => $message->type === 'image' ? 'image' : 'pdf',
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
                ->whereIn('type', ['file', 'image'])
                ->get()
                ->map(function($message) {
                    return [
                        'name' => $message->filename,
                        'type' => $message->type === 'image' ? 'image' : 'pdf',
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

        // ✅ Les accesseurs permettent d'utiliser filepath, filename, etc.
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

        if ($datetime instanceof \Carbon\Carbon) {
            return $datetime->format('H:i');
        }

        return \Carbon\Carbon::parse($datetime)->format('H:i');
    }

    public function createAdminChat(Request $request)
    {
        $request->validate([
            'reference' => 'required|string|exists:reports,reference',
            'message' => 'nullable|string'
        ]);

        $reference = $request->reference;

        // Vérifier si un chat existe déjà pour cette référence
        $existingChat = Chat::where('reference', $reference)->first();

        if ($existingChat) {
            return response()->json([
                'success' => true,
                'chat' => $existingChat,
                'message' => 'Conversation existante trouvée'
            ]);
        }

        // Récupérer les infos du signalement
        $report = Report::where('reference', $reference)->first();

        // Créer le nouveau chat
        $chat = Chat::create([
            'reference' => $reference,
            'visitor_name' => $report->is_anonymous ? 'Anonyme' : $report->name,
            'is_anonymous' => $report->is_anonymous,
            'dossier_titre' => $report->title ?? 'Signalement',
            'status' => 'active',
            'initiated_by' => 'admin' // Important : marqueur admin
        ]);

        // Envoyer le premier message automatique (optionnel)
        if ($request->message) {
            Message::create([
                'chat_id' => $chat->id,
                'sender' => 'me',
                'sender_type' => 'support',
                'type' => 'text',
                'text' => $request->message,
                'status' => 'sent'
            ]);
        }

        return response()->json([
            'success' => true,
            'chat' => $chat->load('messages'),
            'message' => 'Conversation créée avec succès'
        ]);
    }

}
