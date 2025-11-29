<?php
// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'titre' => $notification->titre,
                    'message' => $notification->message,
                    'priority' => $notification->priority,
                    'status' => $notification->status,
                    'reference_dossier' => $notification->reference_dossier,
                    'timestamp' => $notification->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function markAsRead($id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    public function markAllAsRead(): JsonResponse
    {
        Notification::active()->update([
            'read_at' => now(),
            'status' => 'read'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications marquées comme lues'
        ]);
    }

    public function getUnreadCount(): JsonResponse
    {
        $count = Notification::active()->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count]
        ]);
    }

    public function destroy($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    public function deleteRead()
    {
        try {
            Notification::where('status', 'read')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications lues supprimées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    // Toutes les notifications (pour l'historique)
    public function getAll()
    {
        try {
            $notifications = Notification::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications'
            ], 500);
        }
    }

    // Notifications récentes (pour le header)
    public function getRecent()
    {
        try {
            // Récupérer les 20 dernières notifications (récentes + non lues)
            $notifications = Notification::orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications récentes'
            ], 500);
        }
    }

    /**
     * Récupérer les notifications pour l'investigateur
     */
    public function getInvestigatorNotifications(Request $request): JsonResponse
    {
        try {
            // Types de notifications spécifiques aux investigateurs
            $investigatorTypes = [
                'nouveau_signalement',
                'signalement_urgent', 
                'doublon_detecte',
                'statut_modifie',
                'enquete_assignee',
                'enquete_terminee'
            ];

            $notifications = Notification::whereIn('type', $investigatorTypes)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                        'status' => $notification->status,
                        'reference_dossier' => $notification->reference_dossier,
                        'metadata' => $notification->metadata,
                        'created_at' => $notification->created_at->toISOString(),
                        'read_at' => $notification->read_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications de l\'investigateur'
            ], 500);
        }
    }

    /**
     * Notifications récentes pour le header (par rôle)
     */
    public function getRecentByRole(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Si l'utilisateur n'est pas authentifié, retourner les notifications générales
            if (!$user) {
                return $this->getRecent();
            }

            // Définir les types de notifications par rôle
            $roleBasedTypes = [
                'admin' => [
                    'nouveau_signalement',
                    'signalement_urgent',
                    'doublon_detecte', 
                    'statut_modifie',
                    'system'
                ],
                'agent' => [
                    'nouveau_signalement',
                    'signalement_urgent',
                    'doublon_detecte',
                    'statut_modifie'
                ],
                'investigateur' => [
                    'nouveau_signalement',
                    'signalement_urgent',
                    'doublon_detecte',
                    'statut_modifie',
                    'enquete_assignee',
                    'enquete_terminee'
                ]
            ];

            // Récupérer le rôle de l'utilisateur (adaptez selon votre structure)
            $userRole = $user->role ?? 'investigateur'; // Par défaut investigateur
            
            $types = $roleBasedTypes[$userRole] ?? $roleBasedTypes['investigateur'];

            $notifications = Notification::whereIn('type', $types)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                        'status' => $notification->status,
                        'reference_dossier' => $notification->reference_dossier,
                        'metadata' => $notification->metadata,
                        'created_at' => $notification->created_at->toISOString(),
                        'read_at' => $notification->read_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications récentes par rôle'
            ], 500);
        }
    }

    /**
     * Notifications pour l'agent
     */
    public function getAgentNotifications(Request $request): JsonResponse
    {
        try {
            $agentTypes = [
                'nouveau_signalement',
                'signalement_urgent',
                'doublon_detecte',
                'statut_modifie'
            ];

            $notifications = Notification::whereIn('type', $agentTypes)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                        'status' => $notification->status,
                        'reference_dossier' => $notification->reference_dossier,
                        'metadata' => $notification->metadata,
                        'created_at' => $notification->created_at->toISOString(),
                        'read_at' => $notification->read_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications de l\'agent'
            ], 500);
        }
    }

    /**
     * Notifications pour l'administrateur
     */
    public function getAdminNotifications(Request $request): JsonResponse
    {
        try {
            $adminTypes = [
                'nouveau_signalement',
                'signalement_urgent',
                'doublon_detecte', 
                'statut_modifie',
                'system',
                'user_activity'
            ];

            $notifications = Notification::whereIn('type', $adminTypes)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                        'status' => $notification->status,
                        'reference_dossier' => $notification->reference_dossier,
                        'metadata' => $notification->metadata,
                        'created_at' => $notification->created_at->toISOString(),
                        'read_at' => $notification->read_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications de l\'administrateur'
            ], 500);
        }
    }

    /**
     * Statistiques des notifications par rôle
     */
    public function getNotificationStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'investigateur';

            $roleBasedTypes = [
                'admin' => ['nouveau_signalement', 'signalement_urgent', 'doublon_detecte', 'statut_modifie', 'system'],
                'agent' => ['nouveau_signalement', 'signalement_urgent', 'doublon_detecte', 'statut_modifie'],
                'investigateur' => ['nouveau_signalement', 'signalement_urgent', 'doublon_detecte', 'statut_modifie', 'enquete_assignee', 'enquete_terminee']
            ];

            $types = $roleBasedTypes[$userRole] ?? $roleBasedTypes['investigateur'];

            $total = Notification::whereIn('type', $types)->count();
            $unread = Notification::whereIn('type', $types)->active()->count();
            $read = Notification::whereIn('type', $types)->where('status', 'read')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'unread' => $unread,
                    'read' => $read,
                    'role' => $userRole
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques'
            ], 500);
        }
    }
}