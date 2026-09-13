<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultChanged = false;

        try {
            Schema::table('routers', function (Blueprint $table): void {
                $table->string('preferred_transport')->default('api')->change();
            });
            $defaultChanged = true;

            DB::transaction(function (): void {
                DB::table('routers')->where('preferred_transport', 'mock')->update(['preferred_transport' => 'api']);
            });
        } catch (\Throwable $exception) {
            if ($defaultChanged) {
                Schema::table('routers', function (Blueprint $table): void {
                    $table->string('preferred_transport')->default('mock')->change();
                });
            }

            throw $exception;
        }
    }

    public function down(): void
    {
        $defaultChanged = false;

        try {
            Schema::table('routers', function (Blueprint $table): void {
                $table->string('preferred_transport')->default('mock')->change();
            });
            $defaultChanged = true;

            DB::transaction(function (): void {
                DB::table('routers')->where('preferred_transport', 'api')->update(['preferred_transport' => 'mock']);
            });
        } catch (\Throwable $exception) {
            if ($defaultChanged) {
                Schema::table('routers', function (Blueprint $table): void {
                    $table->string('preferred_transport')->default('api')->change();
                });
            }

            throw $exception;
        }
    }
};
