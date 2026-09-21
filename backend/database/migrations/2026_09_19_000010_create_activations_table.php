<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('subscriber_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('ont_id')->constrained('onts')->restrictOnDelete();
            $table->unsignedSmallInteger('c_vlan');
            $table->unsignedSmallInteger('s_vlan');
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['subscriber_service_id', 'status']);
            $table->index(['ont_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};
