<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onts', function (Blueprint $table): void {
            $table->foreignId('acs_server_id')->nullable()->constrained('acs_servers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('onts', function (Blueprint $table): void {
            $table->dropForeign(['acs_server_id']);
            $table->dropColumn('acs_server_id');
        });
    }
};
