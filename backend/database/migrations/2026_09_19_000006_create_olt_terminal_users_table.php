<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_terminal_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->string('username', 64);
            $table->string('profile_name', 15)->default('root');
            $table->text('password');
            $table->unsignedTinyInteger('privilege_level')->default(3);
            $table->unsignedTinyInteger('reenter_limit')->default(1);
            $table->string('appended_info', 30)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['olt_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_terminal_users');
    }
};
