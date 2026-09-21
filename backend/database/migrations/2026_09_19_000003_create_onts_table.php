<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('frame');
            $table->unsignedSmallInteger('slot');
            $table->unsignedSmallInteger('pon_port');
            $table->unsignedSmallInteger('ont_id')->nullable();
            $table->string('serial_number', 190);
            $table->string('name', 190);
            $table->string('status', 30)->default('unknown')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamps();
            $table->unique(['olt_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onts');
    }
};
