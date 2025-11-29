<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\ReportGenerationController; 
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\JournalAuditController;
use App\Http\Controllers\TeamAuthController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamProfileController;

/*
|--------------------------------------------------------------------------
| API Routes - FOSIKA
|--------------------------------------------------------------------------
*/

// -------------------------
// Route de base
// -------------------------
Route::get('/', function () {
    return response()->json([
        'message' => 'FOSIKA API is running',
        'version' => '1.0.0',
        'timestamp' => now()
    ]);
});

// -------------------------
// Route de santé
// -------------------------
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});

// -------------------------
// Routes publiques - Signalements
// -------------------------
Route::post('/reports', [ReportController::class, 'store']);
Route::get('/reports', [ReportController::class, 'index']);
Route::get('/reports/{reference}', [ReportController::class, 'show']);
Route::get('/tracking/{reference}', [ReportController::class, 'checkTracking']);

// -------------------------
// Routes pour les fichiers (PUBLIQUES)
// -------------------------
Route::post('/upload', [ReportController::class, 'uploadFile']);
Route::get('/files/{filename}', [ReportController::class, 'getFile']);
Route::get('/files/{filename}/download', [ReportController::class, 'downloadFile']);
Route::get('/files/{filename}/url', [ReportController::class, 'getFileUrl']);
Route::get('/reports/{reference}/files', [ReportController::class, 'getReportFiles']);
Route::get('/reports/{reference}/files-status', [ReportController::class, 'getFilesStatus']);
Route::post('/reports/{reference}/generate-files', [ReportController::class, 'generateMissingFiles']);

// -------------------------
// Authentification publique
// -------------------------
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/team/login', [TeamAuthController::class, 'login']);

// -------------------------
// Routes pour les notifications (publiques)
// -------------------------
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/all', [NotificationController::class, 'getAll']);
    Route::get('/recent', [NotificationController::class, 'getRecent']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/delete-read', [NotificationController::class, 'deleteRead']);
});

// -------------------------
// Routes protégées par Sanctum
// -------------------------
Route::middleware(['auth:sanctum'])->group(function () {
    
    // ==================== ROUTES ASSIGNATION INVESTIGATEURS ✅ ====================
    Route::get('/reports/assigned', [ReportController::class, 'getAssignedReports']);
    Route::post('/reports/{id}/assign', [ReportController::class, 'assignInvestigator']);

    // ==================== ROUTES ÉQUIPE ====================
    Route::prefix('team')->group(function () {
        // Gestion de l'authentification
        Route::get('/user', [TeamAuthController::class, 'user']);
        Route::get('/check-auth', [TeamAuthController::class, 'checkAuth']);
        Route::post('/logout', [TeamAuthController::class, 'logout']);
        
        // Gestion du profil
        Route::prefix('profile')->group(function () {
            Route::get('/', [TeamProfileController::class, 'getProfile']);
            Route::put('/', [TeamProfileController::class, 'updateProfile']);
            Route::post('/avatar', [TeamProfileController::class, 'updateAvatar']);
            Route::delete('/avatar', [TeamProfileController::class, 'deleteAvatar']);
            Route::post('/password', [TeamProfileController::class, 'updatePassword']);
            Route::get('/stats', [TeamProfileController::class, 'getPersonalStats']);
        });

        // Gestion des utilisateurs d'équipe (admin seulement)
        Route::prefix('users')->group(function () {
            Route::get('/', [TeamController::class, 'getAllUsers']);
            Route::get('/agents', [TeamController::class, 'getAgents']);
            Route::get('/investigateurs', [TeamController::class, 'getInvestigateurs']);
            Route::get('/administrateurs', [TeamController::class, 'getAdministrateurs']);
            Route::get('/stats', [TeamController::class, 'getStats']);
            Route::post('/', [TeamController::class, 'createUser']);
            Route::put('/{id}', [TeamController::class, 'updateUser']);
            Route::delete('/{id}', [TeamController::class, 'deleteUser']);
            Route::post('/{id}/toggle-status', [TeamController::class, 'toggleStatus']);
            Route::post('/{id}/reset-password', [TeamController::class, 'resetPassword']);
        });
    });

    // ==================== ROUTES SPÉCIFIQUES PAR RÔLE ====================
    Route::prefix('agent')->middleware(['check.role:Agent'])->group(function () {
        Route::prefix('profile')->group(function () {
            Route::get('/', [TeamProfileController::class, 'getProfile']);
            Route::put('/', [TeamProfileController::class, 'updateProfile']);
            Route::post('/avatar', [TeamProfileController::class, 'updateAvatar']);
            Route::post('/password', [TeamProfileController::class, 'updatePassword']);
            Route::get('/stats', [TeamProfileController::class, 'getPersonalStats']);
        });
    });

    Route::prefix('investigateur')->middleware(['check.role:Investigateur'])->group(function () {
        Route::prefix('profile')->group(function () {
            Route::get('/', [TeamProfileController::class, 'getProfile']);
            Route::put('/', [TeamProfileController::class, 'updateProfile']);
            Route::post('/avatar', [TeamProfileController::class, 'updateAvatar']);
            Route::post('/password', [TeamProfileController::class, 'updatePassword']);
            Route::get('/stats', [TeamProfileController::class, 'getPersonalStats']);
        });
    });

    // ==================== ROUTES ADMINISTRATION ====================
    Route::prefix('admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/check', [AdminAuthController::class, 'checkAuth']);
        Route::get('/user', [AdminAuthController::class, 'user']);
        
        Route::prefix('profile')->group(function () {
            Route::get('/', [AdminProfileController::class, 'getProfile']);
            Route::put('/', [AdminProfileController::class, 'updateProfile']);
            Route::post('/avatar', [AdminProfileController::class, 'updateAvatar']);
            Route::delete('/avatar', [AdminProfileController::class, 'deleteAvatar']);
            Route::post('/password', [AdminProfileController::class, 'updatePassword']);
        });
    });

    // ==================== ROUTES RAPPORTS PROTÉGÉES ====================
    Route::prefix('reports')->group(function () {
        Route::post('/create', [ReportController::class, 'createReport']);
        Route::post('/generate', [ReportGenerationController::class, 'generateReport']);
        Route::get('/last-generated', [ReportGenerationController::class, 'getLastGeneratedReport']);
        Route::get('/generated', [ReportGenerationController::class, 'getGeneratedReports']);
        Route::post('/{reportId}/send', [ReportGenerationController::class, 'sendReportToInstitution']);
        Route::get('/{reportId}/download', [ReportGenerationController::class, 'downloadReport']);
        
        // Gestion des rapports
        Route::put('/{id}/status', [ReportController::class, 'updateStatus']);
        Route::put('/{id}/workflow', [ReportController::class, 'updateWorkflow']);
        Route::post('/{id}/files', [ReportController::class, 'uploadFiles']);
        Route::put('/{id}', [ReportController::class, 'update']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);
    });

    // ==================== STATISTIQUES ====================
    Route::get('/stats', [ReportController::class, 'getStats']);

    // ==================== NOTIFICATIONS PROTÉGÉES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        
        // NOUVELLES ROUTES PAR RÔLE
        Route::get('/investigator', [NotificationController::class, 'getInvestigatorNotifications']);
        Route::get('/agent', [NotificationController::class, 'getAgentNotifications']);
        Route::get('/admin', [NotificationController::class, 'getAdminNotifications']);
        Route::get('/recent-by-role', [NotificationController::class, 'getRecentByRole']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        
        // Routes spécifiques par rôle avec middleware
        Route::prefix('investigator')->middleware(['check.role:Investigateur'])->group(function () {
            Route::get('/', [NotificationController::class, 'getInvestigatorNotifications']);
            Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        });
        
        Route::prefix('agent')->middleware(['check.role:Agent'])->group(function () {
            Route::get('/', [NotificationController::class, 'getAgentNotifications']);
            Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        });
        
        Route::prefix('admin')->middleware(['check.role:Administrateur'])->group(function () {
            Route::get('/', [NotificationController::class, 'getAdminNotifications']);
            Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        });
    });

    // ==================== JOURNAL D'AUDIT ====================
    Route::prefix('journal-audit')->group(function () {
        Route::get('/', [JournalAuditController::class, 'getJournalData']);
        Route::post('/export', [JournalAuditController::class, 'exportAudit']);
    });
});

// -------------------------
// Routes de notifications par rôle (protégées)
// -------------------------
Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
    // Routes générales par rôle
    Route::get('/role/investigator', [NotificationController::class, 'getInvestigatorNotifications']);
    Route::get('/role/agent', [NotificationController::class, 'getAgentNotifications']);
    Route::get('/role/admin', [NotificationController::class, 'getAdminNotifications']);
    
    // Route universelle pour notifications récentes par rôle
    Route::get('/role/recent', [NotificationController::class, 'getRecentByRole']);
    
    // Statistiques des notifications par rôle
    Route::get('/role/stats', [NotificationController::class, 'getNotificationStats']);
});

// -------------------------
// Routes spécifiques pour l'investigateur
// -------------------------
Route::middleware(['auth:sanctum', 'check.role:Investigateur'])->prefix('investigator')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'getInvestigatorNotifications']);
        Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});

// -------------------------
// Routes spécifiques pour l'agent
// -------------------------
Route::middleware(['auth:sanctum', 'check.role:Agent'])->prefix('agent')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'getAgentNotifications']);
        Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});

// -------------------------
// Routes spécifiques pour l'admin
// -------------------------
Route::middleware(['auth:sanctum', 'check.role:Administrateur'])->prefix('admin')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'getAdminNotifications']);
        Route::get('/recent', [NotificationController::class, 'getRecentByRole']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});

// Routes pour les statuts
Route::get('/reports/{id}/debug', [ReportController::class, 'getReport']);
Route::put('/reports/{id}/status', [ReportController::class, 'updateStatus']);
// Routes pour le workflow
Route::put('/reports/{id}/status', [ReportController::class, 'updateStatus']);
Route::put('/reports/{id}/workflow-step', [ReportController::class, 'updateWorkflowStep']);
Route::get('/reports/{id}/workflow', [ReportController::class, 'getWorkflow']);
// -------------------------
// Route fallback
// -------------------------
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found'
    ], 404);
});