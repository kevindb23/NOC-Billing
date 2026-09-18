<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->string('ssh_username', 190)->nullable()->after('preferred_transport');
            $table->text('ssh_password')->nullable()->after('ssh_username');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->dropColumn(['ssh_username', 'ssh_password']);
        });
    }
};
