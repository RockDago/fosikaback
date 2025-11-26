<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old check constraint that only allows Agent and Investigateur
        // The ENUM type now handles the validation
        DB::statement("ALTER TABLE team_users DROP CONSTRAINT team_users_role_check");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the old check constraint
        DB::statement("ALTER TABLE team_users ADD CONSTRAINT team_users_role_check CHECK (role IN ('Agent', 'Investigateur'))");
    }
};
