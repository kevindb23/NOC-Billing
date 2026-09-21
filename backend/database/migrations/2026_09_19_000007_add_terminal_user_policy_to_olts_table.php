<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->boolean('terminal_user_security_enabled')->default(true);
            $table->unsignedTinyInteger('terminal_user_security_length')->default(12);
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->dropColumn(['terminal_user_security_enabled', 'terminal_user_security_length']);
        });
    }
};
