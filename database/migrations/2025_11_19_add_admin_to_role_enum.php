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
        // Laravel created a simple enum using team_users_role_enum type
        // We need to replace it with all values including Admin
        
        // 1. Create new enum type with all values
        DB::statement("CREATE TYPE role_enum_new AS ENUM ('Admin', 'Agent', 'Investigateur')");
        
        // 2. Drop default constraint
        DB::statement("ALTER TABLE team_users ALTER COLUMN role DROP DEFAULT");
        
        // 3. Convert column to new type
        DB::statement("ALTER TABLE team_users ALTER COLUMN role TYPE role_enum_new USING role::text::role_enum_new");
        
        // 4. Set default back
        DB::statement("ALTER TABLE team_users ALTER COLUMN role SET DEFAULT 'Agent'");
        
        // 5. Drop old enum type if it exists
        DB::statement("DROP TYPE IF EXISTS team_users_role_enum CASCADE");
        
        // 6. Rename new type
        DB::statement("ALTER TYPE role_enum_new RENAME TO team_users_role_enum");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old enum with original values
        DB::statement("CREATE TYPE role_enum_new AS ENUM ('Agent', 'Investigateur')");
        
        DB::statement("ALTER TABLE team_users ALTER COLUMN role DROP DEFAULT");
        
        DB::statement("ALTER TABLE team_users ALTER COLUMN role TYPE role_enum_new USING role::text::role_enum_new");
        
        DB::statement("ALTER TABLE team_users ALTER COLUMN role SET DEFAULT 'Agent'");
        
        DB::statement("DROP TYPE IF EXISTS team_users_role_enum CASCADE");
        
        DB::statement("ALTER TYPE role_enum_new RENAME TO team_users_role_enum");
    }
};
