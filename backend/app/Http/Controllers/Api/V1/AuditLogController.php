<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'actor_user_id' => ['nullable', 'integer'],
            'auditable_type' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $organization = $request->attributes->get('organization');
        $query = AuditLog::with('actor')->latest();
        if ($organization) {
            $query->where('organization_id', $organization->id);
        }

        $query->when($filters['search'] ?? null, function ($builder, string $search): void {
            $builder->where(function ($nested) use ($search): void {
                $nested->where('action', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });
        $query->when($filters['action'] ?? null, fn ($builder, string $action) => $builder->where('action', $action));
        $query->when($filters['actor_user_id'] ?? null, fn ($builder, int $actor) => $builder->where('actor_user_id', $actor));
        $query->when($filters['auditable_type'] ?? null, fn ($builder, string $type) => $builder->where('auditable_type', $type));
        $query->when($filters['from'] ?? null, fn ($builder, string $from) => $builder->whereDate('created_at', '>=', $from));
        $query->when($filters['to'] ?? null, fn ($builder, string $to) => $builder->whereDate('created_at', '<=', $to));

        return response()->json(['data' => $query->paginate($filters['per_page'] ?? 25)]);
    }
}
