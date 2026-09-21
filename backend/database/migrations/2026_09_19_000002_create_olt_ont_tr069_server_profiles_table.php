<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_ont_tr069_server_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('profile_id');
            $table->string('profile_name', 190);
            $table->string('url', 1000);
            $table->string('username', 190);
            $table->text('password');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['olt_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_ont_tr069_server_profiles');
    }
};
