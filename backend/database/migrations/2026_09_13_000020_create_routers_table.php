<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name')->unique();
            $table->string('hostname')->nullable();
            $table->ipAddress('management_ip')->nullable();
            $table->string('vendor')->default('other');
            $table->string('model')->nullable();
            $table->string('software_version')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('driver');
            $table->string('preferred_transport')->default('mock');
            $table->string('status')->default('unknown');
            $table->json('capabilities')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('vendor');
            $table->index('driver');
            $table->index('status');
            $table->index('management_ip');
            $table->index('last_contact_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
