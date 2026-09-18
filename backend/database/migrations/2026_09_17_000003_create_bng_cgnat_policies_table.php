<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bng_cgnat_policies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('bng_id')->constrained('bngs')->cascadeOnDelete();
            $table->string('name');
            $table->string('subscriber_network');
            $table->string('subscriber_interface');
            $table->string('internet_interface');
            $table->string('public_ip_mode')->default('range');
            $table->string('public_ip_start')->nullable();
            $table->string('public_ip_end')->nullable();
            $table->string('local_bypass_network')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_cgnat_policies');
    }
};
