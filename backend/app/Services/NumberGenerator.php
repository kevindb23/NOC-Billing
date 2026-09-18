<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NumberGenerator
{
    public static function next(string $key, string $prefix, string $table, string $column, int $width = 6): string
    {
        self::ensureSequenceTable();

        return DB::transaction(function () use ($key, $prefix, $table, $column, $width): string {
            $sequence = DB::table('number_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            if (! $sequence) {
                DB::table('number_sequences')->insertOrIgnore([
                    'sequence_key' => $key,
                    'next_value' => self::currentMaximum($table, $column, $prefix),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = DB::table('number_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            }
            $next = (int) $sequence->next_value + 1;
            DB::table('number_sequences')->where('sequence_key', $key)->update(['next_value' => $next, 'updated_at' => now()]);
            return $prefix.str_pad((string) $next, $width, '0', STR_PAD_LEFT);
        });
    }

    private static function ensureSequenceTable(): void
    {
        if (Schema::hasTable('number_sequences')) {
            return;
        }

        Schema::create('number_sequences', function ($table): void {
            $table->string('sequence_key', 100)->primary();
            $table->unsignedBigInteger('next_value')->default(0);
            $table->timestamps();
        });
    }

    private static function currentMaximum(string $table, string $column, string $prefix): int
    {
        $pattern = '/^'.preg_quote($prefix, '/').'([0-9]+)$/';
        return DB::table($table)->pluck($column)->reduce(
            fn (int $maximum, mixed $value): int => preg_match($pattern, (string) $value, $matches)
                ? max($maximum, (int) $matches[1])
                : $maximum,
            0,
        );
    }
}
