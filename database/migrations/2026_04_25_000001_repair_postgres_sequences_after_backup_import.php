<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'audit_systemes',
            'notifications',
            'universites',
            'etablissements',
            'enseignants',
            'users',
            'reports',
            'trackings',
            'workflow_logs',
            'chats',
            'chat_participants',
            'messages',
            'dossiers',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('{$table}', 'id'),
                    GREATEST(COALESCE((SELECT MAX(id) FROM {$table}), 0), 1),
                    true
                )
            ");
        }
    }

    public function down(): void
    {
        //
    }
};
