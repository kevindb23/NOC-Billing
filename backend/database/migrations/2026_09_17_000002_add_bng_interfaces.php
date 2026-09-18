<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bngs', function (Blueprint $table): void {
            $table->string('parent_interface')->nullable()->after('notes');
            $table->string('egress_interface')->nullable()->after('parent_interface');
        });
    }

    public function down(): void
    {
        Schema::table('bngs', function (Blueprint $table): void {
            $table->dropColumn(['parent_interface', 'egress_interface']);
        });
    }
};
