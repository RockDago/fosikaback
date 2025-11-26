<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionFieldsToAdminsTable extends Migration
{
    public function up()
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('avatar');
            $table->timestamp('last_login_at')->nullable()->after('session_id');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
        });
    }

    public function down()
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'last_login_at', 'last_login_ip']);
        });
    }
}