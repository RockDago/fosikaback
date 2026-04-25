<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    private function getSafeUser(Request $request): ?User
    {
        try {
            if ($request->user()) {
                return $request->user();
            }

            if ($request->session()->has('userid')) {
                return User::find($request->session()->get('userid'));
            }
        } catch (\Throwable $e) {
            Log::error('Erreur getSafeUser notifications', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function visibleNotificationsQuery(Request $request)
    {
        $user = $this->getSafeUser($request);
        $role = strtolower($user->role ?? 'admin');

        $query = Notification::query()
            ->where(function ($query) use ($user) {
                if (!Schema::hasColumn('notifications', 'user_id')) {
                    return;
                }

                if ($user) {
                    $query->whereNull('user_id')->orWhere('user_id', $user->id);
                } else {
                    $query->whereNull('user_id');
                }
            });

        if (Schema::hasColumn('notifications', 'target_roles')) {
            $query->where(function ($query) use ($role) {
                $query->whereNull('target_roles')
                    ->orWhere('target_roles', 'LIKE', '%'.$role.'%');
            });
        }

        if (Schema::hasColumn('notifications', 'expires_at')) {
            $query->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        try {
            $user = $this->getSafeUser($request);
            $notifications = $this->visibleNotificationsQuery($request)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => $notifications->count(),
                'user_role' => $user->role ?? 'admin',
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur chargement notifications', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications',
            ], 500);
        }
    }

    public function getRecent(Request $request)
    {
        return $this->index($request);
    }

    public function getRecentByRole(Request $request)
    {
        return $this->index($request);
    }

    public function getUnreadCount(Request $request)
    {
        try {
            $count = $this->visibleNotificationsQuery($request)
                ->where('status', Notification::STATUS_ACTIVE)
                ->count();

            return response()->json(['success' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            Log::error('Erreur comptage notifications', ['error' => $e->getMessage()]);
            return response()->json(['success' => true, 'count' => 0]);
        }
    }

    public function markAsRead(Request $request, $id)
    {
        $updated = $this->visibleNotificationsQuery($request)
            ->where('id', $id)
            ->update([
                'status' => Notification::STATUS_READ,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => $updated > 0], $updated > 0 ? 200 : 404);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = $this->visibleNotificationsQuery($request)
            ->where('status', Notification::STATUS_ACTIVE)
            ->update([
                'status' => Notification::STATUS_READ,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    public function destroy(Request $request, $id)
    {
        $deleted = $this->visibleNotificationsQuery($request)
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => $deleted > 0], $deleted > 0 ? 200 : 404);
    }

    public function deleteRead(Request $request)
    {
        $deleted = $this->visibleNotificationsQuery($request)
            ->where('status', Notification::STATUS_READ)
            ->delete();

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function getNotificationStats(Request $request)
    {
        $query = $this->visibleNotificationsQuery($request);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (clone $query)->count(),
                'unread' => (clone $query)->where('status', Notification::STATUS_ACTIVE)->count(),
                'read' => (clone $query)->where('status', Notification::STATUS_READ)->count(),
            ],
        ]);
    }
}
