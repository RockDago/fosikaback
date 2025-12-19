<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vérification que la table 'users' existe
            if (!Schema::hasTable('users')) {
                throw new \Exception("La table 'users' n'existe pas. Veuillez d'abord créer la table users.");
            }

            // --- Champs pour la vérification email avancée ---
            if (!Schema::hasColumn('users', 'email_verification_code')) {
                $table->string('email_verification_code', 255)->nullable()->after('email_verified_at');
            }

            if (!Schema::hasColumn('users', 'email_verification_code_expires_at')) {
                $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code');
            }

            // --- Champs pour la 2FA (Two-Factor Authentication) ---
            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('email_verification_code_expires_at');
            }

            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->string('two_factor_secret', 255)->nullable()->after('two_factor_enabled');
            }

            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code', 255)->nullable()->after('two_factor_secret');
            }

            if (!Schema::hasColumn('users', 'two_factor_code_expires_at')) {
                $table->timestamp('two_factor_code_expires_at')->nullable()->after('two_factor_code');
            }

            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_code_expires_at');
            }

            // --- Champs additionnels pour le profil ---
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('two_factor_recovery_codes');
            }

            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            }

            if (!Schema::hasColumn('users', 'login_attempts')) {
                $table->integer('login_attempts')->default(0)->after('last_login_at');
            }

            if (!Schema::hasColumn('users', 'last_login_attempt_at')) {
                $table->timestamp('last_login_attempt_at')->nullable()->after('login_attempts');
            }

            if (!Schema::hasColumn('users', 'account_locked_until')) {
                $table->timestamp('account_locked_until')->nullable()->after('last_login_attempt_at');
            }

            // --- Index pour optimiser les requêtes ---
            $table->index(['email_verification_code', 'email_verification_code_expires_at'], 'idx_email_verification');
            $table->index(['two_factor_code', 'two_factor_code_expires_at'], 'idx_2fa_codes');
            $table->index('email_verified_at', 'idx_email_verified');
            $table->index('two_factor_enabled', 'idx_2fa_enabled');
            $table->index('account_locked_until', 'idx_account_lock');
        });

        // Ajouter des commentaires sur les colonnes pour la documentation
        DB::statement("COMMENT ON COLUMN users.email_verification_code IS 'Code de vérification email (hashé)'");
        DB::statement("COMMENT ON COLUMN users.email_verification_code_expires_at IS 'Expiration du code de vérification email'");
        DB::statement("COMMENT ON COLUMN users.two_factor_enabled IS '2FA activée (0=non, 1=oui)'");
        DB::statement("COMMENT ON COLUMN users.two_factor_secret IS 'Clé secrète pour la 2FA'");
        DB::statement("COMMENT ON COLUMN users.two_factor_code IS 'Code 2FA temporaire (hashé)'");
        DB::statement("COMMENT ON COLUMN users.two_factor_code_expires_at IS 'Expiration du code 2FA'");
        DB::statement("COMMENT ON COLUMN users.two_factor_recovery_codes IS 'Codes de récupération 2FA (JSON)'");
        DB::statement("COMMENT ON COLUMN users.last_login_ip IS 'Dernière adresse IP de connexion'");
        DB::statement("COMMENT ON COLUMN users.last_login_at IS 'Dernière date de connexion'");
        DB::statement("COMMENT ON COLUMN users.login_attempts IS 'Nombre de tentatives de connexion échouées'");
        DB::statement("COMMENT ON COLUMN users.last_login_attempt_at IS 'Dernière tentative de connexion'");
        DB::statement("COMMENT ON COLUMN users.account_locked_until IS 'Compte verrouillé jusqu''à cette date'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Supprimer les index d'abord
            $table->dropIndex('idx_email_verification');
            $table->dropIndex('idx_2fa_codes');
            $table->dropIndex('idx_email_verified');
            $table->dropIndex('idx_2fa_enabled');
            $table->dropIndex('idx_account_lock');

            // Supprimer les colonnes dans l'ordre inverse
            $columnsToDrop = [
                'account_locked_until',
                'last_login_attempt_at',
                'login_attempts',
                'last_login_at',
                'last_login_ip',
                'two_factor_recovery_codes',
                'two_factor_code_expires_at',
                'two_factor_code',
                'two_factor_secret',
                'two_factor_enabled',
                'email_verification_code_expires_at',
                'email_verification_code',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
