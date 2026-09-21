<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('vendor', 120);
            $table->string('model', 190);
            $table->string('serial_number', 190)->nullable()->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 30)->default('in_stock')->index();
            $table->date('purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
