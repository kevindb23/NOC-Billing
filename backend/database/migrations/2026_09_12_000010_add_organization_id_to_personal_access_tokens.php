<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('tokenable_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'tokenable_id'], 'pat_org_tokenable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('pat_org_tokenable_idx');
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
