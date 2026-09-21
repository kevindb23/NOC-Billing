<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bng_speed_boost_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bng_speed_boost_id')->constrained()->cascadeOnDelete();
            $table->string('username', 120);
            $table->string('rate_value', 64);
            $table->timestamp('applied_at');
            $table->timestamps();
            $table->unique(['bng_speed_boost_id', 'username']);
            $table->index('username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_speed_boost_syncs');
    }
};
