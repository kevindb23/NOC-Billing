<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->string('preferred_transport', 20)->default('ssh')->after('management_endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->dropColumn('preferred_transport');
        });
    }
};
