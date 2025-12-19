<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Changé: Report au lieu de Dossier

class DossierController extends Controller
{
    // Récupérer les infos d'un dossier (signalement) pour le chat flottant
    public function getDossierInfo($reference)
    {
        // Rechercher dans la table 'reports' au lieu de 'dossiers'
        $dossier = Report::where('reference', $reference)->first();

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé',
            ], 404);
        }

        // Si un utilisateur est connecté, vérifier les permissions
        $user = Auth::user();
        if ($user) {
            if ($user->isAgent() || $user->isInvestigateur() || $user->isAdmin()) {
                // Les agents, investigateurs et admins peuvent voir tous les dossiers
            } elseif ($dossier->email && $user->email !== $dossier->email) {
                // Pour les utilisateurs identifiés, vérifier si l'email correspond
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à ce dossier',
                ], 403);
            }
        } else {
            // Pour les visiteurs non authentifiés, on peut limiter l'accès ou autoriser
            // selon vos règles métier. Ici, on autorise l'accès pour le chat flottant
            // car on veut permettre le contact avec l'assistance.
        }

        // Récupérer les informations du citoyen depuis le signalement
        // Note: Dans le modèle Report, on n'a pas de relation 'citoyen'
        // On utilise directement les champs name, email, phone du report
        $citoyenInfo = null;
        if ($dossier->name || $dossier->email) {
            $citoyenInfo = [
                'name' => $dossier->name,
                'email' => $dossier->email,
                'phone' => $dossier->phone,
                'initials' => $this->getInitials($dossier->name),
            ];
        }

        // Vérifier s'il y a un chat actif pour ce dossier
        $hasActiveChat = Chat::where('reference', $reference)
            ->where('status', 'active')
            ->exists();

        return response()->json([
            'success' => true,
            'dossier' => [
                'id' => $dossier->id,
                'reference' => $dossier->reference,
                'titre' => $dossier->getTypeLabel('fr') . ' - ' . $dossier->getCategoryLabel('fr'),
                'description' => $dossier->description,
                'type' => $dossier->type,
                'type_label' => $dossier->getTypeLabel('fr'),
                'category' => $dossier->category,
                'category_label' => $dossier->getCategoryLabel('fr'),
                'statut' => $dossier->status,
                'statut_label' => $dossier->getStatusLabel('fr'),
                'priority' => $this->getPriorityFromStatus($dossier->status),
                'date_creation' => $dossier->created_at->format('d/m/Y'),
                'citoyen' => $citoyenInfo,
                'contact_info' => [
                    'email' => $dossier->email,
                    'phone' => $dossier->phone,
                ],
                // Informations supplémentaires
                'address' => $dossier->address,
                'has_files' => $dossier->hasFiles(),
                'files_count' => $dossier->files_count,
                'workflow' => $dossier->workflow,
                'assigned_to' => $dossier->assigned_to,
                'assigned_user' => $dossier->assignedUser ? [
                    'name' => $dossier->assignedUser->full_name ?? $dossier->assignedUser->name,
                    'email' => $dossier->assignedUser->email,
                ] : null,
                'is_anonymous' => $dossier->type === 'anonyme',
                // Pour le chat
                'has_active_chat' => $hasActiveChat,
                'can_start_chat' => $this->canStartChat($dossier, $user),
            ],
        ]);
    }

    // Méthode pour vérifier si un dossier a un chat actif
    public function checkChatStatus($reference)
    {
        $dossier = Report::where('reference', $reference)->first();

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé',
            ], 404);
        }

        $chat = Chat::where('reference', $reference)
            ->where('status', 'active')
            ->first();

        $unreadCount = 0;
        if ($chat && Auth::check()) {
            $unreadCount = $chat->messages()
                ->where('read_at', null)
                ->where('sender_id', '!=', Auth::id())
                ->count();
        }

        return response()->json([
            'success' => true,
            'has_active_chat' => $chat ? true : false,
            'chat_id' => $chat ? $chat->id : null,
            'chat_title' => $chat ? $chat->title : null,
            'last_message_at' => $chat ? $chat->last_message_at : null,
            'unread_messages' => $unreadCount,
            'can_start_chat' => $this->canStartChat($dossier, Auth::user()),
        ]);
    }

    // Méthode pour créer un chat à partir d'un dossier
    public function createChatFromDossier(Request $request, $reference)
    {
        $dossier = Report::where('reference', $reference)->first();

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé',
            ], 404);
        }

        // Vérifier les permissions
        $user = Auth::user();
        if (!$this->canStartChat($dossier, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à démarrer un chat pour ce dossier',
            ], 403);
        }

        // Vérifier s'il y a déjà un chat actif
        $existingChat = Chat::where('reference', $reference)
            ->where('status', 'active')
            ->first();

        if ($existingChat) {
            return response()->json([
                'success' => true,
                'message' => 'Chat déjà existant',
                'chat' => [
                    'id' => $existingChat->id,
                    'title' => $existingChat->title,
                    'reference' => $existingChat->reference,
                ],
                'is_new' => false,
            ]);
        }

        // Créer le chat
        try {
            $chat = Chat::create([
                'reference' => $dossier->reference,
                'dossier_id' => $dossier->id,
                'title' => "Chat - {$dossier->reference}",
                'type' => 'dossier_support',
                'status' => 'active',
                'created_by' => $user ? $user->id : null,
                'last_message_at' => now(),
            ]);

            // Ajouter les participants
            if ($user) {
                $chat->addParticipant($user->id, 'citoyen');
            } elseif ($dossier->email) {
                // Pour les visiteurs non connectés, on pourrait stocker l'email
                // selon votre logique d'application
            }

            // Ajouter un agent support
            $supportAgent = \App\Models\User::where('role', 'agent')
                ->where('statut', true)
                ->inRandomOrder()
                ->first();

            if ($supportAgent) {
                $chat->addParticipant($supportAgent->id, 'support');
            }

            // Message automatique du support
            \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $supportAgent ? $supportAgent->id : 1, // ID système ou admin
                'content' => "Bonjour ! Bienvenue sur le support FOSIKA. Nous avons bien reçu votre demande concernant le dossier {$dossier->reference}. Un agent vous répondra dans les plus brefs délais.",
                'type' => 'text',
                'reference' => $dossier->reference,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Chat créé avec succès',
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                    'reference' => $chat->reference,
                ],
                'is_new' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du chat',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================
    // MÉTHODES PRIVÉES D'AIDE
    // =========================================

    // Vérifier si un utilisateur peut démarrer un chat pour ce dossier
    private function canStartChat(Report $dossier, $user = null)
    {
        // Règles pour démarrer un chat:
        // 1. Les admins, agents et investigateurs peuvent toujours démarrer un chat
        if ($user && ($user->isAgent() || $user->isInvestigateur() || $user->isAdmin())) {
            return true;
        }

        // 2. Les citoyens peuvent démarrer un chat si:
        //    - Ils sont connectés et l'email correspond
        //    - OU ils sont anonymes mais ont accès au dossier (par référence)
        if ($dossier->type === 'anonyme') {
            // Pour les signalements anonymes, on autorise le chat avec la référence
            return true;
        }

        // Pour les signalements identifiés, vérifier l'email
        if ($user && $dossier->email && $user->email === $dossier->email) {
            return true;
        }

        // Pour les visiteurs non connectés, on pourrait vérifier d'autres critères
        // comme un token ou une session spécifique

        return false;
    }

    // Obtenir les initiales d'un nom
    private function getInitials($name)
    {
        if (!$name) {
            return 'AN';
        }

        $names = explode(' ', $name);
        $initials = '';

        foreach ($names as $n) {
            if (trim($n)) {
                $initials .= strtoupper(substr($n, 0, 1));
            }
        }

        return substr($initials, 0, 2);
    }

    // Déterminer la priorité à partir du statut
    private function getPriorityFromStatus($status)
    {
        $priorities = [
            'traitement_classification' => 'medium',
            'investigation' => 'high',
            'transmis_autorite' => 'high',
            'refuse' => 'low',
            'classifier' => 'low',
            'en_cours' => 'medium',
            'finalise' => 'low',
            'doublon' => 'low',
        ];

        return $priorities[$status] ?? 'medium';
    }

    // Récupérer la liste des dossiers d'un utilisateur
    public function getUserDossiers(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Pour les citoyens, récupérer leurs dossiers par email
        if ($user->isCitoyen()) {
            $dossiers = Report::where('email', $user->email)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        // Pour les agents, investigateurs, admins - tous les dossiers
        elseif ($user->isAgent() || $user->isInvestigateur() || $user->isAdmin()) {
            $dossiers = Report::orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        } else {
            $dossiers = collect();
        }

        return response()->json([
            'success' => true,
            'dossiers' => $dossiers->map(function($dossier) {
                return [
                    'reference' => $dossier->reference,
                    'titre' => $dossier->getTypeLabel('fr') . ' - ' . $dossier->getCategoryLabel('fr'),
                    'statut' => $dossier->status,
                    'statut_label' => $dossier->getStatusLabel('fr'),
                    'date_creation' => $dossier->created_at->format('d/m/Y'),
                    'has_active_chat' => Chat::where('reference', $dossier->reference)
                        ->where('status', 'active')
                        ->exists(),
                ];
            }),
        ]);
    }
}
