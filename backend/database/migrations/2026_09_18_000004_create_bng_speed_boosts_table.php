<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bng_speed_boosts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('bng_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bng_radius_server_id')->constrained('bng_radius_servers')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('download_kbps');
            $table->unsignedInteger('upload_kbps');
            $table->string('status', 32)->default('draft')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_applied_at')->nullable();
            $table->unsignedInteger('synced_user_count')->default(0);
            $table->timestamps();
            $table->unique(['bng_id', 'bng_radius_server_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_speed_boosts');
    }
};
