<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // === CHAMPS UNIQUES ET COHÉRENTS ===
            $table->string('name')->nullable();                    // Laravel Auth standard + frontend
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();                   // Alias principal (utilisé par le frontend)
            $table->string('telephone')->nullable();               // Compatibilité ancienne base
            $table->text('adresse')->nullable();
            $table->string('departement')->nullable();
            $table->string('username')->unique()->nullable();

            // === MOT DE PASSE ===
            $table->string('password'); // ← Laravel va hasher grâce au mutator

            // === RÔLE & DROITS ===
            $table->string('role')->default('agent'); // admin, agent, investigateur
            $table->text('responsabilites')->nullable();
            $table->json('specialisations')->nullable();
            $table->boolean('statut')->default(true);

            // === AVATAR ===
            $table->string('avatar')->nullable();

            // === CONNEXION ===
            $table->string('session_id')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // === CORRECTION CRITIQUE : Réparer tous les mots de passe existants ===
        $this->fixExistingPasswords();
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }

    /**
     * Répare tous les mots de passe en clair dans la base
     */
    private function fixExistingPasswords()
    {
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            // Si le password ne commence pas par $2y$ ou $2a$ → c'est du texte brut
            if (!empty($user->password) && !str_starts_with($user->password, '$2y$') && !str_starts_with($user->password, '$2a$')) {
                $plainPassword = $user->password;

                // Option 1 : hasher le mot de passe existant (si tu le connais)
                $hashed = Hash::make($plainPassword);

                // Option 2 (plus safe) : forcer un mot de passe par défaut
                // $hashed = Hash::make('123456'); // ou 'admin2025'

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => $hashed]);

                \Log::info("Mot de passe corrigé pour l'utilisateur ID {$user->id} ({$user->email})");
            }
        }
    }
};
