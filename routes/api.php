<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\TeamAuthController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\TeamProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportGenerationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\JournalAuditController;

/*
|--------------------------------------------------------------------------
| API Routes - FOSIKA
|--------------------------------------------------------------------------
*/

// -------------------------
// Routes de base et santé
// -------------------------
Route::get('/', function () {
    return response()->json([
        'message'   => 'FOSIKA API is running',
        'version'   => '1.0.0',
        'timestamp' => now(),
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status'    => 'healthy',
        'timestamp' => now(),
    ]);
});

// -------------------------
// Routes publiques - Signalements
// -------------------------
Route::prefix('reports')->group(function () {
    Route::post('/', [ReportController::class, 'store']);
    Route::get('/', [ReportController::class, 'index']);
    Route::get('/{reference}', [ReportController::class, 'show']);
    Route::get('/tracking/{reference}', [ReportController::class, 'checkTracking']);
});

// -------------------------
// Routes publiques - Fichiers
// -------------------------
Route::prefix('files')->group(function () {
    Route::post('/upload', [ReportController::class, 'uploadFile']);
    Route::get('/{filename}', [ReportController::class, 'getFile']);
    Route::get('/{filename}/download', [ReportController::class, 'downloadFile']);
    Route::get('/{filename}/url', [ReportController::class, 'getFileUrl']);
    Route::get('/reports/{reference}/files', [ReportController::class, 'getReportFiles']);
    Route::get('/reports/{reference}/files-status', [ReportController::class, 'getFilesStatus']);
    Route::post('/reports/{reference}/generate-files', [ReportController::class, 'generateMissingFiles']);
});

// -------------------------
// Authentification publique
// -------------------------
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/team/login', [TeamAuthController::class, 'login']);

// -------------------------
// Routes d’audit système (API, admin uniquement)
// -------------------------
// GET  /api/journal-audit
// POST /api/journal-audit/export
Route::middleware(['auth:sanctum', 'check.role:Admin'])->group(function () {
    Route::get('/journal-audit', [JournalAuditController::class, 'getJournalData']);
    Route::post('/journal-audit/export', [JournalAuditController::class, 'exportAudit']);
});

// -------------------------
// Routes protégées par Sanctum
// -------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // ==================== ROUTES PROFIL UNIFIÉES ====================
    Route::prefix('profile')->group(function () {
        Route::get('/', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $controller = $user instanceof \App\Models\Admin
                ? app(AdminProfileController::class)
                : app(TeamProfileController::class);

            return $controller->getProfile($request);
        });

        Route::put('/', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $controller = $user instanceof \App\Models\Admin
                ? app(AdminProfileController::class)
                : app(TeamProfileController::class);

            return $controller->updateProfile($request);
        });

        Route::post('/avatar', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $controller = $user instanceof \App\Models\Admin
                ? app(AdminProfileController::class)
                : app(TeamProfileController::class);

            return $controller->updateAvatar($request);
        });

        Route::delete('/avatar', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $controller = $user instanceof \App\Models\Admin
                ? app(AdminProfileController::class)
                : app(TeamProfileController::class);

            return $controller->deleteAvatar($request);
        });

        Route::post('/password', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $controller = $user instanceof \App\Models\Admin
                ? app(AdminProfileController::class)
                : app(TeamProfileController::class);

            return $controller->updatePassword($request);
        });
    });

    // ✅ ROUTES ADMIN PROFILE
    Route::get('/admin/profile', [AdminProfileController::class, 'getProfile']);
    Route::put('/admin/profile', [AdminProfileController::class, 'updateProfile']);
    Route::post('/admin/profile/avatar', [AdminProfileController::class, 'updateAvatar']);
    Route::delete('/admin/profile/avatar', [AdminProfileController::class, 'deleteAvatar']);
    Route::post('/admin/profile/password', [AdminProfileController::class, 'updatePassword']);

    // ✅ ROUTES TEAM PROFILE
    Route::get('/team/profile', [TeamProfileController::class, 'getProfile']);
    Route::put('/team/profile', [TeamProfileController::class, 'updateProfile']);
    Route::post('/team/profile/avatar', [TeamProfileController::class, 'updateAvatar']);
    Route::delete('/team/profile/avatar', [TeamProfileController::class, 'deleteAvatar']);
    Route::post('/team/profile/password', [TeamProfileController::class, 'updatePassword']);

    // ✅ ALIAS POUR COMPATIBILITÉ FRONTEND
    Route::get('/agent/profile', [TeamProfileController::class, 'getProfile']);
    Route::get('/investigateur/profile', [TeamProfileController::class, 'getProfile']);
    Route::get('/investigator/profile', [TeamProfileController::class, 'getProfile']);

    // -------------------------
    // Notifications
    // -------------------------
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/all', [NotificationController::class, 'index']);
        Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        // Si tu veux éviter toute erreur si le front appelle encore cette URL :
        // Route::get('/recent-by-role', [NotificationController::class, 'getRecentByRole']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/delete-read', [NotificationController::class, 'deleteRead']);
    });

    // ✅ ROUTES TEAM (LECTURE POUR TOUS)
    Route::prefix('team')->group(function () {
        Route::get('/users', [TeamController::class, 'getAllUsers']);
        Route::get('/agents', [TeamController::class, 'getAgents']);
        Route::get('/investigateurs', [TeamController::class, 'getInvestigateurs']);
        Route::get('/administrateurs', [TeamController::class, 'getAdministrateurs']);
        Route::get('/stats', [TeamController::class, 'getStats']);
        Route::get('/roles', [TeamController::class, 'getRoles']);

        // ✅ RESET-PASSWORD DIRECT
        Route::post('/users/{id}/reset-password', [TeamController::class, 'resetPassword']);
        Route::put('/users/{id}/reset-password', [TeamController::class, 'resetPassword']);
    });

    // -------------------------
    // Déconnexion / Vérification / Utilisateur courant
    // -------------------------
    Route::post('/logout', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $controller = $user instanceof \App\Models\Admin
            ? app(AdminAuthController::class)
            : app(TeamAuthController::class);

        return $controller->logout($request);
    });

    Route::get('/check-auth', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $controller = $user instanceof \App\Models\Admin
            ? app(AdminAuthController::class)
            : app(TeamAuthController::class);

        return $controller->checkAuth($request);
    });

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $controller = $user instanceof \App\Models\Admin
            ? app(AdminAuthController::class)
            : app(TeamAuthController::class);

        return $controller->user($request);
    });

    // ==================== ROUTE /admin/check ====================
    Route::get('/admin/check', function (Request $request) {
        try {
            $admin = $request->user();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                ], 401);
            }

            if (!($admin instanceof \App\Models\Admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé - Accès réservé aux administrateurs',
                ], 403);
            }

            $responseData = [
                'id'         => $admin->id ?? null,
                'name'       => $admin->name ?? '',
                'email'      => $admin->email ?? '',
                'first_name' => $admin->first_name ?? '',
                'last_name'  => $admin->last_name ?? '',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'data'    => $responseData,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur /admin/check:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur',
                'debug'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    });

    // ==================== ROUTES PAR RÔLE ====================
    // Admin - Actions de modification uniquement
    Route::middleware(['check.role:Admin'])->prefix('admin')->group(function () {
        Route::prefix('team')->group(function () {
            Route::post('/users', [TeamController::class, 'createUser']);
            Route::put('/users/{id}', [TeamController::class, 'updateUser']);
            Route::delete('/users/{id}', [TeamController::class, 'deleteUser']);
            Route::post('/users/{id}/toggle-status', [TeamController::class, 'toggleStatus']);
            Route::post('/users/{id}/reset-password', [TeamController::class, 'resetPassword']);
            Route::put('/users/{id}/reset-password', [TeamController::class, 'resetPassword']);
        });

        Route::prefix('audit')->group(function () {
            Route::get('/journal', [JournalAuditController::class, 'getJournalData']);
            Route::post('/journal/export', [JournalAuditController::class, 'exportAudit']);
        });
    });

    // Agent
    Route::middleware(['check.role:Agent'])->prefix('agent')->group(function () {
        Route::get('/profile/stats', [TeamProfileController::class, 'getPersonalStats']);

        Route::prefix('reports')->group(function () {
            Route::get('/assigned', [ReportController::class, 'getAssignedReports']);
        });
    });

    // Investigateur / Investigator
    Route::middleware(['check.role:Investigateur'])->prefix('investigator')->group(function () {
        Route::get('/profile/stats', [TeamProfileController::class, 'getPersonalStats']);

        Route::prefix('reports')->group(function () {
            Route::get('/assigned', [ReportController::class, 'getAssignedReports']);
        });
    });

    Route::middleware(['check.role:Investigateur'])->prefix('investigateur')->group(function () {
        Route::get('/profile/stats', [TeamProfileController::class, 'getPersonalStats']);

        Route::prefix('reports')->group(function () {
            Route::get('/assigned', [ReportController::class, 'getAssignedReports']);
        });
    });

    // -------------------------
    // Reports protégés
    // -------------------------
    Route::prefix('reports')->group(function () {
        Route::post('/create', [ReportController::class, 'createReport']);
        Route::post('/generate', [ReportGenerationController::class, 'generateReport']);
        Route::get('/last-generated', [ReportGenerationController::class, 'getLastGeneratedReport']);
        Route::get('/generated', [ReportGenerationController::class, 'getGeneratedReports']);
        Route::post('/{reportId}/send', [ReportGenerationController::class, 'sendReportToInstitution']);
        Route::get('/{reportId}/download', [ReportGenerationController::class, 'downloadReport']);

        Route::middleware(['check.role:Admin'])->group(function () {
            Route::post('/{id}/assign', [ReportController::class, 'assignInvestigator']);
        });

        Route::put('/{id}/status', [ReportController::class, 'updateStatus']);
        Route::put('/{id}/workflow', [ReportController::class, 'updateWorkflow']);
        Route::put('/{id}/workflow-step', [ReportController::class, 'updateWorkflowStep']);
        Route::get('/{id}/workflow', [ReportController::class, 'getWorkflow']);
        Route::post('/{id}/files', [ReportController::class, 'uploadFiles']);
        Route::put('/{id}', [ReportController::class, 'update']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);

        if (app()->environment('local')) {
            Route::get('/{id}/debug', [ReportController::class, 'getReport']);
        }
    });

    // -------------------------
    // Statistiques
    // -------------------------
    Route::get('/stats/overview', [ReportController::class, 'getStats']);
});

// -------------------------
// Fallback
// -------------------------
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found',
    ], 404);
});
