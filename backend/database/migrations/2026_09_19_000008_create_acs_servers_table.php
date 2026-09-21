<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_servers', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->string('name', 190); $table->string('api_url', 1000); $table->string('api_username', 190); $table->text('api_password'); $table->string('transport', 30)->default('cwmp'); $table->string('status', 30)->default('active'); $table->string('ssh_username', 190); $table->text('ssh_password'); $table->unsignedSmallInteger('ssh_port')->default(22); $table->text('notes')->nullable(); $table->timestamp('last_ssh_tested_at')->nullable(); $table->timestamp('last_api_tested_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps(); $table->softDeletes();
        });
    }

    public function down(): void { Schema::dropIfExists('acs_servers'); }
};
