<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bng_radius_servers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('bng_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('server_address', 255);
            $table->string('secret');
            $table->unsignedSmallInteger('auth_port')->default(1812);
            $table->unsignedSmallInteger('accounting_port')->default(1813);
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_radius_servers');
    }
};
