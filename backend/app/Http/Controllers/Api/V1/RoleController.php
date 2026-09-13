<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->rolesQuery()
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));
        $roles->through(fn (Role $role): array => $this->resource($role, false));

        return response()->json(['data' => $roles]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $role = DB::transaction(function () use ($request, $validated, $permissionIds): Role {
            $role = Role::create($validated);
            $role->permissions()->sync($permissionIds);
            $role->load('permissions');
            $this->auditLogger->record($request, 'role.created', $role, [], $this->snapshot($role));

            return $role;
        });

        return response()->json(['data' => $this->resource($role, true)], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $role = $this->rolesQuery()
            ->with('permissions')
            ->findOrFail($id);

        return response()->json(['data' => $this->resource($role, true)]);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $validated = $request->validated();
        $permissionsProvided = array_key_exists('permission_ids', $validated);
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);
        $oldSnapshot = $this->snapshot($role->load('permissions'));

        $role = DB::transaction(function () use ($request, $role, $validated, $permissionsProvided, $permissionIds, $oldSnapshot): Role {
            $role->update($validated);
            if ($permissionsProvided) {
                $role->permissions()->sync($permissionIds);
            }
            $role->load('permissions');
            $newSnapshot = $this->snapshot($role);
            if ($oldSnapshot['name'] !== $newSnapshot['name']) {
                $this->auditLogger->record($request, 'role.updated', $role, $oldSnapshot, $newSnapshot);
            }
            if ($permissionsProvided && $oldSnapshot['permission_ids'] !== $newSnapshot['permission_ids']) {
                $this->auditLogger->record($request, 'role.permissions_updated', $role, ['permission_ids' => $oldSnapshot['permission_ids']], ['permission_ids' => $newSnapshot['permission_ids']]);
            }

            return $role;
        });

        return response()->json(['data' => $this->resource($role, true)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id)->load('permissions');
        $assigned = DB::table('role_assignments')
            ->where('role_id', $role->id)
            ->exists();

        abort_if($assigned, Response::HTTP_CONFLICT, 'The role cannot be deleted while it is assigned.');

        DB::transaction(function () use ($request, $role): void {
            $snapshot = $this->snapshot($role);
            $this->auditLogger->record($request, 'role.deleted', $role, $snapshot, []);
            $role->delete();
        });

        return response()->json(['message' => 'Role deleted.']);
    }

    private function rolesQuery(): Builder
    {
        return Role::query()
            ->withCount([
                'users as assignment_count',
                'permissions as permission_count',
            ]);
    }

    private function resource(Role $role, bool $includePermissions): array
    {
        $resource = [
            'id' => $role->id,
            'name' => $role->name,
            'assignment_count' => $role->assignment_count ?? null,
            'permission_count' => $role->permission_count ?? null,
        ];
        if ($includePermissions) {
            $permissions = $role->permissions->sortBy('id')->values();
            $resource['permission_ids'] = $permissions->pluck('id')->all();
            $resource['permissions'] = $permissions
                ->groupBy(function ($permission): string {
                    $prefix = Str::before($permission->name, '.');

                    return in_array($prefix, ['dashboard', 'billing', 'network', 'routers'], true)
                        ? Str::title($prefix)
                        : 'System';
                })
                ->map(fn ($group): array => $group->pluck('id')->values()->all())
                ->all();
        }

        return $resource;
    }

    private function snapshot(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => $role->permissions->pluck('id')->sort()->values()->all(),
        ];
    }
}
