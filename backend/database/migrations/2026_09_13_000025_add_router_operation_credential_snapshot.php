<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('router_operations', function (Blueprint $table): void {
            $table->string('credential_profile_id', 26)->nullable()->after('transport')->index();
            $table->unsignedInteger('credential_version')->nullable()->after('credential_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('router_operations', function (Blueprint $table): void {
            $table->dropColumn(['credential_profile_id', 'credential_version']);
        });
    }
};
