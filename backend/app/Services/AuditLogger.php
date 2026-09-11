<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(Request $request, string $action, ?Model $auditable = null, array $oldValues = [], array $newValues = []): AuditLog
    {
        return AuditLog::create([
            'organization_id' => $request->attributes->get('organization')?->id,
            'actor_user_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : 'system',
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'correlation_id' => $request->header('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function sanitize(array $values): array
    {
        foreach (['password', 'password_confirmation', 'token', 'access_token', 'plain_text_token'] as $secret) {
            unset($values[$secret]);
        }

        return $values;
    }
}
