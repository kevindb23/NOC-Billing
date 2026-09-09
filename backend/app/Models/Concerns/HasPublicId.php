<?php

namespace App\Models\Concerns;

trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            $model->public_id ??= (string) str()->ulid();
        });
    }
}
