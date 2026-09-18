<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('portal_username', 120)->nullable()->after('phone');
            $table->text('portal_password')->nullable()->after('portal_username');
            $table->string('ppp_username', 120)->nullable()->after('portal_password');
            $table->text('ppp_password')->nullable()->after('ppp_username');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['portal_username', 'portal_password', 'ppp_username', 'ppp_password']);
        });
    }
};
