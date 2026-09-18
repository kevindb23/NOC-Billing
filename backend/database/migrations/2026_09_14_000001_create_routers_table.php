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
            $table->string('name', 190);
            $table->string('vendor', 100)->default('Other');
            $table->string('model', 100)->nullable();
            $table->string('management_endpoint', 190)->nullable();
            $table->string('preferred_transport', 20)->nullable();
            $table->string('status', 30)->default('unknown')->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
