<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bngs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('vendor');
            $table->string('model')->nullable();
            $table->string('management_endpoint')->nullable();
            $table->string('preferred_transport')->default('ssh');
            $table->text('ssh_username')->nullable();
            $table->text('ssh_password')->nullable();
            $table->boolean('session_requested')->default(false);
            $table->string('status')->default('unknown');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bngs');
    }
};
