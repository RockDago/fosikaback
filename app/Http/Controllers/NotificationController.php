<?php
// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
}