<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\JournalAuditController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportGenerationController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserController;
// ✅ NOUVEAUX CONTRÔLEURS ENSEIGNANTS
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\UniversiteController;
use App\Http\Controllers\EtablissementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =========================================
// ROUTES PUBLIQUES PAS D'AUTH - VISITEURS
// =========================================

Route::get('/', fn() => response()->json([
    'message' => 'FOSIKA API is running',
    'version' => '1.0.0',
    'timestamp' => now(),
]));

Route::get('/health', fn() => response()->json([
    'status' => 'healthy',
    'timestamp' => now(),
]));

// =========================================
// ✅ ROUTES PUBLIQUES POUR LES ENSEIGNANTS (VISITEURS)
// =========================================

// ✅ AJOUT: Routes publiques complètes pour les enseignants
Route::prefix('enseignants')->group(function () {
    // ✅ Liste des enseignants avec filtres et pagination (PUBLIC)
    Route::get('/', [EnseignantController::class, 'index']);
    
    // ✅ AJOUT: Récupérer TOUS les enseignants sans pagination
    Route::get('/all', [EnseignantController::class, 'getAll']);
    
    // ✅ AJOUT: Recherche globale multi-champs
    Route::get('/search', [EnseignantController::class, 'searchGlobal']);
    
    // ✅ AJOUT: Compter les enseignants
    Route::get('/count', [EnseignantController::class, 'count']);
    
    // ✅ AJOUT: Récupérer les métadonnées (corps, catégories, diplômes)
    Route::get('/metadata', [EnseignantController::class, 'getMetadata']);
    
    // Détails d'un enseignant (PUBLIC)
    Route::get('/{id}', [EnseignantController::class, 'show']);
    
    // Statistiques par établissement (PUBLIC)
    Route::get('/statistiques/global', [EnseignantController::class, 'statistiques']);
    Route::get('/statistiques/par-etablissement', [EnseignantController::class, 'statistiquesParEtablissement']);
});

Route::prefix('universites')->group(function () {
    // Liste des universités (PUBLIC)
    Route::get('/', [UniversiteController::class, 'index']);
    
    // ✅ AJOUT: Récupérer toutes les universités sans pagination
    Route::get('/all', [UniversiteController::class, 'getAll']);
    
    // Détails d'une université (PUBLIC)
    Route::get('/{id}', [UniversiteController::class, 'show']);
});

Route::prefix('etablissements')->group(function () {
    // Liste des établissements (PUBLIC)
    Route::get('/', [EtablissementController::class, 'index']);
    
    // ✅ AJOUT: Récupérer tous les établissements sans pagination
    Route::get('/all', [EtablissementController::class, 'getAll']);
    
    // ✅ AJOUT: Récupérer les établissements par université
    Route::get('/by-universite/{universiteId}', [EtablissementController::class, 'getByUniversite']);
    
    // Détails d'un établissement (PUBLIC)
    Route::get('/{id}', [EtablissementController::class, 'show']);
});

// ✅ ROUTES CHAT PUBLIQUES
Route::post('/chats/admin/create', [ChatController::class, 'createAdminChat'])
    ->middleware(['auth:sanctum', 'twofactor.api', 'check.role:admin,agent']);

// Marquer les messages du support comme lus (pour visiteurs)
Route::post('/chats/public/{id}/mark-read', [ChatController::class, 'markPublicAsRead']);

// ============================================
// ✅ ROUTES PUBLIQUES CHAT (SANS AUTHENTIFICATION)
// ============================================

// ✅ CORRECTION: Servir les fichiers de chat publiquement (renommé pour éviter conflit)
Route::get('/chat-files/public/{filename}', [ChatController::class, 'servePublicFile'])
    ->name('chat.files.public');

// Vérifier une référence de signalement
Route::post('/chats/check-reference', [ChatController::class, 'checkReference'])
    ->name('chats.check.reference');

// ✅ Vérifier si un chat existe pour une référence
Route::get('/chats/check-by-reference/{reference}', [ChatController::class, 'checkChatByReference'])
    ->name('chats.check.by.reference');

// Créer un chat de support (visiteur)
Route::post('/chats/support', [ChatController::class, 'createSupportChat'])
    ->name('chats.support.create');

// Envoyer un message public (visiteur)
Route::post('/chats/{chatId}/messages/public', [ChatController::class, 'sendPublicMessage'])
    ->name('chats.messages.public.send');

// Voir une conversation publique (visiteur)
Route::get('/chats/{id}/public', [ChatController::class, 'showPublic'])
    ->name('chats.public.show');

// Récupérer les chats récents (visiteur)
Route::get('/chats/recent/public', [ChatController::class, 'getRecentPublicChats'])
    ->middleware(['auth:sanctum', 'twofactor.api', 'check.role:admin,agent,investigateur'])
    ->name('chats.recent.public');

// Mettre à jour le statut en ligne du visiteur
Route::post('/chats/{chatId}/visitor/online-status', [ChatController::class, 'updateVisitorOnlineStatus'])
    ->name('chats.visitor.online.update');

// Récupérer le statut en ligne du support
Route::get('/chats/{chatId}/visitor/online-status', [ChatController::class, 'getVisitorOnlineStatus'])
    ->name('chats.visitor.online.status');

// =========================================
// ✅ ANCIEN FORMAT ROUTES DE CHAT PUBLIQUES (COMPATIBILITÉ)
// =========================================

Route::prefix('chat')->group(function () {
    // ✅ Créer un chat de support (visiteurs)
    Route::post('/support/create', [ChatController::class, 'createSupportChat']);

    // ✅ Envoyer un message public (visiteur)
    Route::post('/{chatId}/public-message', [ChatController::class, 'sendPublicMessage']);

    // ✅ Récupérer une conversation publique
    Route::get('/conversation/{id}', [ChatController::class, 'showPublic']);

    // ✅ Liste des conversations récentes
    Route::get('/recent', [ChatController::class, 'getRecentPublicChats'])
        ->middleware(['auth:sanctum', 'twofactor.api', 'check.role:admin,agent,investigateur']);

    // ✅ Vérifier une référence de dossier
    Route::get('/dossier/{reference}', [ChatController::class, 'checkReference']);

    // ✅ Routes pour le statut en ligne des visiteurs
    Route::post('/{chatId}/visitor/online-status', [ChatController::class, 'updateVisitorOnlineStatus']);
    Route::get('/{chatId}/visitor/online-status', [ChatController::class, 'getVisitorOnlineStatus']);
});

// =========================================
// ROUTES PUBLIQUES POUR LES DOSSIERS
// =========================================

// Routes pour les dossiers (signalements)
Route::get('/dossier/{reference}', [DossierController::class, 'getDossierInfo']);
Route::get('/dossier/{reference}/status', [DossierController::class, 'checkChatStatus']);

// ROUTES PUBLIQUES POUR LES SIGNALEMENTS (VISITEURS)
Route::post('/reports/public/submit', [ReportController::class, 'store']);
Route::post('/reports', [ReportController::class, 'store']); // Compatibilité ancien frontend

// ROUTES PUBLIQUES POUR L'AJOUT DE FICHIERS AUX DOSSIERS (VISITEURS)
Route::post('/reports/{reference}/add-files', [ReportController::class, 'addFilesToReport']);

// ROUTES PUBLIQUES POUR LE SUIVI DES DOSSIERS
Route::get('/reports/tracking/{reference}', [ReportController::class, 'publicTracking']);
Route::get('/reports/tracking-old/{reference}', [ReportController::class, 'checkTracking']);

// =========================================
// ✅ ROUTES PUBLIQUES POUR LES FICHIERS DOSSIERS (VISITEURS)
// =========================================
Route::prefix('files')->group(function () {
    // ✅ Accès aux fichiers publics des dossiers SANS auth
    Route::get('public/{filename}', [ReportController::class, 'getPublicFile'])
        ->where('filename', '.*')
        ->name('files.public.get');
    
    // ✅ Télécharger un fichier public
    Route::get('public/{filename}/download', [ReportController::class, 'downloadPublicFile'])
        ->where('filename', '.*')
        ->name('files.public.download');
    
    // ✅ Visualiser un fichier public
    Route::get('public/{filename}/view', [ReportController::class, 'viewPublicFile'])
        ->where('filename', '.*')
        ->name('files.public.view');
});

// =========================================
// AUTHENTIFICATION PUBLIQUE
// =========================================
Route::post('/auth/login', [UserAuthController::class, 'login'])->middleware('throttle:login');

// =========================================
// ROUTES PROTÉGÉES - SANCTUM SEULEMENT (SANS 2FA)
// =========================================
Route::middleware(['auth:sanctum'])->group(function () {

    // ✅ VÉRIFICATION D'ÉTAT D'APPAREIL
    Route::get('/auth/device-status', [UserAuthController::class, 'checkDeviceStatus']);
    Route::get('/auth/check-2fa-required', [UserAuthController::class, 'checkTwoFactorRequired']);

    // ✅ ROUTES 2FA APRÈS LOGIN
    Route::post('/auth/verify-2fa', [UserAuthController::class, 'verifyTwoFactor'])->middleware('throttle:twofactor');
    Route::post('/auth/resend-2fa-code', [UserAuthController::class, 'resendTwoFactorCode'])->middleware('throttle:twofactor');

    // ✅ ÉTAT SESSION 2FA
    Route::get('/auth/2fa-status', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'two_factor_verified' => false,
            ], 401);
        }

        $agent = new \Jenssegers\Agent\Agent();
        $currentDevice = [
            'ip' => $request->ip(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
        ];

        $storedDevice = session('two_factor_verified_device');
        $deviceChanged = false;
        if ($storedDevice) {
            $deviceChanged =
                $storedDevice['ip'] !== $currentDevice['ip'] ||
                $storedDevice['browser'] !== $currentDevice['browser'] ||
                $storedDevice['platform'] !== $currentDevice['platform'];
        }

        return response()->json([
            'authenticated' => true,
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_verified' => session('2fa_verified', false),
            'requires_2fa' => $user->two_factor_enabled && (!session('2fa_verified', false) || $deviceChanged),
            'device_changed' => $deviceChanged,
            'current_device' => $currentDevice,
            'stored_device' => $storedDevice,
            'user' => $user->only(['id', 'email', 'name', 'role']),
            'session_id' => session()->getId(),
        ]);
    });

    // ✅ VÉRIFICATION EMAIL
    Route::prefix('email')->group(function () {
        Route::post('/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
        Route::post('/verify', [EmailVerificationController::class, 'verifyEmail']);
        Route::post('/resend-code', [EmailVerificationController::class, 'resendVerificationCode']);
        Route::get('/verification-status', [EmailVerificationController::class, 'checkVerificationStatus']);
    });

    // ✅ GESTION 2FA (sans middleware 2FA)
    Route::prefix('2fa')->group(function () {
        Route::post('/send-code', [TwoFactorAuthenticationController::class, 'sendVerificationCode'])->middleware('throttle:twofactor');
        Route::post('/verify-code', [TwoFactorAuthenticationController::class, 'verifyCode'])->middleware('throttle:twofactor');
        Route::post('/verify', [TwoFactorAuthenticationController::class, 'verifyCode'])->middleware('throttle:twofactor');
        Route::post('/verify-recovery-code', [TwoFactorAuthenticationController::class, 'verifyRecoveryCode'])->middleware('throttle:twofactor');
        Route::get('/status', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'two_factor_enabled' => $user->two_factor_enabled ?? false,
                'two_factor_verified' => session('2fa_verified', false),
                'requires_2fa' => $user->two_factor_enabled && !session('2fa_verified'),
                'has_recovery_codes' => !empty($user->two_factor_recovery_codes),
            ]);
        });

        Route::middleware('twofactor.api')->group(function () {
            Route::post('/toggle', [TwoFactorAuthenticationController::class, 'toggleTwoFactor']);
            Route::post('/generate-recovery-codes', [TwoFactorAuthenticationController::class, 'generateRecoveryCodes']);
        });
    });

    // ✅ ROUTES USER 2FA
    Route::prefix('user')->group(function () {
        Route::post('/two-factor-authentication/send-code', [TwoFactorAuthenticationController::class, 'sendVerificationCode']);
        Route::post('/two-factor-authentication/confirm', [TwoFactorAuthenticationController::class, 'verifyCode']);
        Route::post('/two-factor-authentication', [TwoFactorAuthenticationController::class, 'toggleTwoFactor'])
            ->middleware('twofactor.api');
        Route::get('/check-2fa-required', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'requires_2fa' => $user->two_factor_enabled && !session('2fa_verified'),
                'two_factor_enabled' => $user->two_factor_enabled,
                'two_factor_verified' => session('2fa_verified', false),
            ]);
        });
    });

    // AUTH CHECK
    Route::get('/check-auth', [UserAuthController::class, 'checkAuth']);
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [UserAuthController::class, 'logout']);
        Route::get('/user', [UserAuthController::class, 'user']);
    });

    // ✅ ROUTES DOSSIERS PROTÉGÉES (avec auth seulement, sans 2FA)
    Route::middleware('twofactor.api')->group(function () {
        Route::post('/dossier/{reference}/create-chat', [DossierController::class, 'createChatFromDossier']);
        Route::get('/user/dossiers', [DossierController::class, 'getUserDossiers']);
    });

    // 🔍 DEBUG & TEST
    if (app()->environment('local')) {
    Route::prefix('debug')->middleware('check.role:admin')->group(function () {
        Route::get('/token-info', fn(Request $request) => response()->json([
            'success' => true,
            'user' => $request->user()?->only(['id', 'email', 'role']),
            'token_valid' => $request->user() !== null,
            'headers' => [
                'authorization_present' => $request->hasHeader('Authorization'),
                'accept_json' => $request->expectsJson(),
            ],
            '2fa_status' => [
                'enabled' => $request->user()->two_factor_enabled ?? false,
                'verified' => session('2fa_verified', false),
            ],
        ]));

        Route::get('/auth-status', fn(Request $request) => response()->json([
            'authenticated' => $request->user() !== null,
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'user_role' => $request->user()?->role,
            'auth_method' => 'sanctum_token',
            '2fa_enabled' => $request->user()->two_factor_enabled ?? false,
            '2fa_verified' => session('2fa_verified', false),
        ]));

        Route::get('/2fa-info', function (Request $request) {
            $user = $request->user();
            $agent = new \Jenssegers\Agent\Agent();
            return response()->json([
                'user_id' => $user->id,
                'email' => $user->email,
                'two_factor_enabled' => $user->two_factor_enabled,
                'two_factor_code' => $user->two_factor_code ? 'Set' : 'Not set',
                'two_factor_code_expires_at' => $user->two_factor_code_expires_at,
                'session_2fa_verified' => session('2fa_verified'),
                'session_2fa_verified_at' => session('2fa_verified_at'),
                'two_factor_recovery_codes_count' => $user->two_factor_recovery_codes ? count($user->two_factor_recovery_codes) : 0,
                'device_info' => [
                    'stored_device' => session('two_factor_verified_device'),
                    'current_device' => [
                        'ip' => $request->ip(),
                        'browser' => $agent->browser(),
                        'platform' => $agent->platform(),
                    ],
                ],
            ]);
        });

        Route::get('/auth-check', function (Request $request) {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'authenticated' => false,
                    'message' => 'Token invalide ou non authentifié'
                ], 401);
            }
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
                'token_valid' => true,
            ]);
        });

        Route::get('/file-access-test', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'authenticated' => $user ? true : false,
                'user' => $user ? [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role
                ] : null,
                'token_exists' => $request->bearerToken() ? true : false,
                'session_2fa' => session('2fa_verified', false),
                'can_access_admin_files' => $user && in_array(strtolower($user->role ?? ''), ['admin', 'agent', 'investigateur', 'investigator'])
            ]);
        });
    });
    }
});

// =========================================
// ROUTES PROTÉGÉES - SANCTUM + 2FA REQUIS
// =========================================
Route::middleware(['auth:sanctum', 'log.actions', 'twofactor.api'])->group(function () {

    // =========================================
    // ✅ ROUTES ENSEIGNANTS PROTÉGÉES (GESTION ADMIN)
    // =========================================
    Route::prefix('admin/enseignants')->middleware('check.role:admin')->group(function () {
        // CRUD Enseignants
        Route::post('/', [EnseignantController::class, 'store']);
        Route::put('/{id}', [EnseignantController::class, 'update']);
        Route::delete('/{id}', [EnseignantController::class, 'destroy']);

        // Import/Export
        Route::post('/import', [EnseignantController::class, 'import']);
        Route::get('/export', [EnseignantController::class, 'export']);
    });

    Route::prefix('admin/universites')->middleware('check.role:admin')->group(function () {
        Route::post('/', [UniversiteController::class, 'store']);
        Route::put('/{id}', [UniversiteController::class, 'update']);
        Route::delete('/{id}', [UniversiteController::class, 'destroy']);
    });

    Route::prefix('admin/etablissements')->middleware('check.role:admin')->group(function () {
        Route::post('/', [EtablissementController::class, 'store']);
        Route::put('/{id}', [EtablissementController::class, 'update']);
        Route::delete('/{id}', [EtablissementController::class, 'destroy']);
    });

    // =========================================
    // ✅ ROUTES DE CHAT PROTÉGÉES (avec auth + 2FA)
    // =========================================
    Route::prefix('chat')->middleware('check.role:admin,agent,investigateur')->group(function () {
        // Liste des conversations (admin/support)
        Route::get('/', [ChatController::class, 'index']);

        // Détails d'une conversation (admin/support)
        Route::get('/{id}', [ChatController::class, 'show']);

        // Envoyer un message (admin/support)
        Route::post('/{chatId}/message', [ChatController::class, 'sendMessage']);

        // Upload de fichier (admin/support)
        Route::post('/{chatId}/upload', [ChatController::class, 'uploadFile']);

        // Marquer/retirer important (admin/support)
        Route::put('/{chatId}/toggle-important', [ChatController::class, 'toggleImportant']);

        // Marquer comme lu (admin/support)
        Route::put('/{chatId}/mark-read', [ChatController::class, 'markAsRead']);
    });

    // ✅ ROUTES FILES ADMIN (nécessite 2FA)
    Route::prefix('files')->middleware('check.role:admin,agent,investigateur')->group(function () {
        Route::get('admin/{filename}', [ReportController::class, 'getAdminFile'])
            ->where('filename', '.*');
        Route::get('admin/{filename}/download', [ReportController::class, 'downloadAdminFile'])
            ->where('filename', '.*');
        Route::get('admin/{filename}/url', [ReportController::class, 'getFileUrl']);
    });

    Route::post('/admin/users/create-with-notification', [UserAuthController::class, 'createUserWithNotification'])
        ->middleware('check.role:admin');

    // 🔥 PROFIL
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::put('/', [ProfileController::class, 'updateProfile']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);

        Route::get('/2fa-status', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'two_factor_enabled' => $user->two_factor_enabled,
                'has_recovery_codes' => !empty($user->two_factor_recovery_codes),
                'recovery_codes_count' => $user->two_factor_recovery_codes ? count($user->two_factor_recovery_codes) : 0,
            ]);
        });

        Route::post('/2fa/enable', [TwoFactorAuthenticationController::class, 'sendVerificationCode']);
        Route::post('/2fa/verify', [TwoFactorAuthenticationController::class, 'verifyCode']);
        Route::post('/2fa/disable', [TwoFactorAuthenticationController::class, 'toggleTwoFactor']);
        Route::post('/2fa/regenerate-recovery-codes', [TwoFactorAuthenticationController::class, 'generateRecoveryCodes']);
    });

    // 🔥 NOTIFICATIONS
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/recent', [NotificationController::class, 'getRecent']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::get('/stats', [NotificationController::class, 'getNotificationStats']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/delete-read', [NotificationController::class, 'deleteRead']);
    });

    // =========================================
    // ROUTES PROTÉGÉES POUR LES SIGNALEMENTS (BACKOFFICE)
    // =========================================
    Route::prefix('reports')->middleware('check.role:admin,agent,investigateur')->group(function () {
        // Création d'un signalement (admin)
        Route::post('/admin/create', [ReportController::class, 'createReport']);

        // Liste des signalements
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/assigned', [ReportController::class, 'getAssignedReports']);

        // Gestion d'un signalement spécifique
        Route::get('/{reference}', [ReportController::class, 'show']);
        Route::put('/{id}', [ReportController::class, 'update']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);

        // Assignation et workflow
        Route::post('/{id}/assign', [ReportController::class, 'assignInvestigator']);
        Route::put('/{id}/status', [ReportController::class, 'updateStatus']);
        Route::put('/{id}/workflow', [ReportController::class, 'updateWorkflow']);
        Route::put('/{id}/workflow-step', [ReportController::class, 'updateWorkflowStep']);
        Route::get('/{id}/workflow', [ReportController::class, 'getWorkflow']);
        Route::post('/{id}/files', [ReportController::class, 'uploadFiles']);

        // Génération de rapports
        Route::post('/generate', [ReportGenerationController::class, 'generateReport']);
        Route::get('/last-generated', [ReportGenerationController::class, 'getLastGeneratedReport']);
        Route::get('/generated', [ReportGenerationController::class, 'getGeneratedReports']);
        Route::post('/{reportId}/send', [ReportGenerationController::class, 'sendReportToInstitution']);
        Route::get('/{reportId}/download', [ReportGenerationController::class, 'downloadReport']);

        // Statistiques
        Route::get('/stats/overview', [ReportController::class, 'getStats']);
    });

    // ROUTES PROTÉGÉES POUR LES FICHIERS (BACKOFFICE)
    Route::prefix('files')->middleware('check.role:admin,agent,investigateur')->group(function () {
        // Upload de fichiers (admin) - nécessite 2FA
        Route::post('/upload', [ReportController::class, 'uploadFile']);

        // Gestion des fichiers par rapport (admin) - nécessite 2FA
        Route::get('/admin/reports/{reference}/files', [ReportController::class, 'getReportFiles']);
        Route::get('/admin/reports/{reference}/files-status', [ReportController::class, 'getFilesStatus']);
        Route::post('/admin/reports/{reference}/generate-files', [ReportController::class, 'generateMissingFiles']);
    });

    // 🔥 UTILISATEURS
    Route::prefix('users')->middleware('check.role:admin')->group(function () {
        Route::get('/', [UserController::class, 'getAllUsers']);
        Route::post('/', [UserController::class, 'createUser']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'updateUser']);
        Route::delete('/{id}', [UserController::class, 'deleteUser']);
        Route::get('/{id}/restore', [UserController::class, 'restore']);
        Route::post('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::put('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('/{id}/status', [UserController::class, 'toggleStatus']);
        Route::patch('/{id}/status', [UserController::class, 'toggleStatus']);
        Route::put('/{id}/status', [UserController::class, 'toggleStatus']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::put('/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/statistics', [UserController::class, 'getStats']);
        Route::get('/trashed/list', [UserController::class, 'trashed']);
        Route::get('/agents', [UserController::class, 'getAgents']);
        Route::get('/investigateurs', [UserController::class, 'getInvestigateurs']);
        Route::get('/administrateurs', [UserController::class, 'getAdministrateurs']);
        Route::get('/by-role/{role}', [UserController::class, 'getUsersByRole']);
        Route::get('/roles', [UserController::class, 'getRoles']);
    });

    // 🔥 ADMIN
    Route::prefix('admin')->middleware('check.role:admin')->group(function () {
        Route::get('/debug', fn(Request $request) => response()->json([
            'success' => true,
            'user' => $request->user()?->only(['id', 'email', 'role']),
            'is_admin' => $request->user() && in_array(strtolower($request->user()->role ?? ''), ['admin']),
        ]));

        Route::prefix('team')->group(function () {
            Route::get('/all', [UserController::class, 'getAllUsers']);
            Route::get('/users', [UserController::class, 'getAllUsers']);
            Route::get('/agents', [UserController::class, 'getAgents']);
            Route::get('/investigateurs', [UserController::class, 'getInvestigateurs']);
            Route::get('/administrateurs', [UserController::class, 'getAdministrateurs']);
            Route::post('/users', [UserController::class, 'createUser']);
            Route::put('/users/{id}', [UserController::class, 'updateUser']);
            Route::delete('/users/{id}', [UserController::class, 'deleteUser']);
            Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::patch('/users/{id}/restore', [UserController::class, 'restoreUser']);
            Route::get('/stats', [UserController::class, 'getStats']);
            Route::get('/roles', [UserController::class, 'getRoles']);
            Route::get('/users-by-role', [UserController::class, 'getUsersByRole']);
        });

        Route::prefix('audit')->group(function () {
            Route::get('/journal', [JournalAuditController::class, 'getJournalData']);
            Route::post('/journal/export', [JournalAuditController::class, 'exportAudit']);
            Route::get('/my-logs', [JournalAuditController::class, 'getUserAuditLogs']);
        });
    });

    // 🔥 AUDIT
    Route::prefix('audit')->group(function () {
        Route::middleware('check.role:admin')->group(function () {
            Route::get('/logs', [JournalAuditController::class, 'getJournalData']);
            Route::post('/logs/export', [JournalAuditController::class, 'exportAudit']);
            Route::get('/export/download/{filename}', [JournalAuditController::class, 'downloadExport']);
            Route::get('/stats', [JournalAuditController::class, 'getAuditStats']);
        });
        Route::get('/my-logs', [JournalAuditController::class, 'getUserAuditLogs']);
    });

    // 🔥 CHECK RÔLES
    Route::prefix('check')->group(function () {
        Route::get('/admin', fn(Request $request) => response()->json([
            'success' => $request->user() && in_array(strtolower($request->user()->role ?? ''), ['admin']),
            'role' => $request->user()?->role,
        ]));
        Route::get('/agent', fn(Request $request) => response()->json([
            'success' => $request->user() && in_array(strtolower($request->user()->role ?? ''), ['agent']),
            'role' => $request->user()?->role,
        ]));
        Route::get('/investigateur', fn(Request $request) => response()->json([
            'success' => $request->user() && in_array(strtolower($request->user()->role ?? ''), ['investigateur', 'investigator']),
            'role' => $request->user()?->role,
        ]));
    });

    // 🔥 TESTS
    if (app()->environment('local')) {
    Route::prefix('test')->middleware('check.role:admin')->group(function () {
        Route::get('/audit-log', fn() =>
        \App\Models\AuditSysteme::orderBy('timestamp', 'desc')
            ->limit(10)
            ->get(['id', 'timestamp', 'utilisateur', 'action', 'entite', 'statut'])
        );
        Route::post('/manual-log', fn(Request $request) => response()->json(['success' => true]));
    });
    }
});

// =========================================
// ROUTES UTILITAIRES (sans auth)
// =========================================
Route::prefix('common')->middleware(['auth:sanctum', 'twofactor.api', 'check.role:admin,agent,investigateur'])->group(function () {
    Route::get('/agents', [UserController::class, 'getAgents']);
    Route::get('/investigateurs', [UserController::class, 'getInvestigateurs']);
    Route::get('/stats/users', [UserController::class, 'getStats']);
    Route::get('/roles', [UserController::class, 'getRoles']);
});

// =========================================
// 404 HANDLER
// =========================================
Route::fallback(fn() => response()->json([
    'success' => false,
    'message' => 'Route non trouvée',
    'available_routes' => app()->environment('local') ? [
        // Routes publiques - Enseignants
        'GET /api/enseignants' => 'Liste enseignants avec pagination (PUBLIC)',
        'GET /api/enseignants/all' => 'Tous les enseignants sans pagination (PUBLIC)',
        'GET /api/enseignants/search' => 'Recherche globale enseignants (PUBLIC)',
        'GET /api/enseignants/count' => 'Compter enseignants (PUBLIC)',
        'GET /api/enseignants/metadata' => 'Métadonnées enseignants (PUBLIC)',
        'GET /api/enseignants/{id}' => 'Détails enseignant (PUBLIC)',
        'GET /api/enseignants/statistiques/global' => 'Statistiques globales (PUBLIC)',
        'GET /api/enseignants/statistiques/par-etablissement' => 'Statistiques par établissement (PUBLIC)',
        
        // Routes publiques - Universités
        'GET /api/universites' => 'Liste universités (PUBLIC)',
        'GET /api/universites/all' => 'Toutes les universités sans pagination (PUBLIC)',
        'GET /api/universites/{id}' => 'Détails université (PUBLIC)',
        
        // Routes publiques - Établissements
        'GET /api/etablissements' => 'Liste établissements (PUBLIC)',
        'GET /api/etablissements/all' => 'Tous les établissements sans pagination (PUBLIC)',
        'GET /api/etablissements/by-universite/{id}' => 'Établissements par université (PUBLIC)',
        'GET /api/etablissements/{id}' => 'Détails établissement (PUBLIC)',
        
        // Routes protégées Admin - Enseignants
        'POST /api/admin/enseignants' => 'Créer enseignant (ADMIN + 2FA)',
        'PUT /api/admin/enseignants/{id}' => 'Modifier enseignant (ADMIN + 2FA)',
        'DELETE /api/admin/enseignants/{id}' => 'Supprimer enseignant (ADMIN + 2FA)',
        'POST /api/admin/enseignants/import' => 'Importer enseignants (ADMIN + 2FA)',
        'GET /api/admin/enseignants/export' => 'Exporter enseignants (ADMIN + 2FA)',
        
        // Routes publiques - Fichiers Chat
        'GET /api/chat-files/public/{filename}' => 'Servir fichiers chat sans auth (PUBLIC)',

        // Routes publiques - Chat
        'POST /api/chats/check-reference' => 'Vérifier référence signalement (PUBLIC)',
        'POST /api/chats/support' => 'Créer chat de support (PUBLIC)',
        'POST /api/chats/{chatId}/messages/public' => 'Envoyer message public (PUBLIC)',
        'GET /api/chats/{id}/public' => 'Récupérer conversation publique (PUBLIC)',
        'GET /api/chats/recent/public' => 'Liste conversations récentes (PUBLIC)',
        'POST /api/chats/{chatId}/visitor/online-status' => 'Mettre à jour statut visiteur (PUBLIC)',
        'GET /api/chats/{chatId}/visitor/online-status' => 'Récupérer statut visiteur (PUBLIC)',

        // Routes publiques - Fichiers Dossiers
        'GET /api/files/public/{filename}' => 'Accéder fichier dossier public (PUBLIC)',
        'GET /api/files/public/{filename}/download' => 'Télécharger fichier dossier public (PUBLIC)',
        'GET /api/files/public/{filename}/view' => 'Visualiser fichier dossier public (PUBLIC)',

        // Routes protégées - Chat (Admin/Support avec 2FA)
        'GET /api/chat' => 'Liste conversations (ADMIN/SUPPORT + 2FA)',
        'GET /api/chat/{id}' => 'Détails conversation (ADMIN/SUPPORT + 2FA)',
        'POST /api/chat/{chatId}/message' => 'Envoyer message support (ADMIN/SUPPORT + 2FA)',
        'POST /api/chat/{chatId}/upload' => 'Upload fichier (ADMIN/SUPPORT + 2FA)',
        
        // Routes protégées - Fichiers Admin
        'GET /api/files/admin/{filename}' => 'Accéder fichier admin (ADMIN + 2FA)',
        'GET /api/files/admin/{filename}/download' => 'Télécharger fichier admin (ADMIN + 2FA)',
    ] : null,
], 404));
