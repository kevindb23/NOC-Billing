<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->text('ssh_username')->nullable()->after('preferred_transport');
            $table->text('ssh_password')->nullable()->after('ssh_username');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn(['ssh_username', 'ssh_password']);
        });
    }
};
