<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('telegram_enabled')->default(false);
            $table->text('telegram_bot_token');
            $table->string('telegram_chat_id');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id'], 'org_notification_user_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('organization_notification_settings'); }
};
