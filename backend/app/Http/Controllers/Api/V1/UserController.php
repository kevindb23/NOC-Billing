<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $users = $organization->users()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', fn ($query) => $query->wherePivot('status', $request->string('status')->toString()))
            ->latest('users.created_at')
            ->paginate($request->integer('per_page', 20));

        $users->through(fn (User $user): array => $this->resource($user, $organization));

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $validated = $request->validated();
        $roleIds = $validated['role_ids'] ?? [];
        $membershipStatus = $validated['status'] ?? 'active';
        unset($validated['role_ids'], $validated['status']);

        $user = DB::transaction(function () use ($request, $organization, $validated, $roleIds, $membershipStatus): User {
            $user = User::create($validated);
            $this->syncRoles($user, $organization, $roleIds);
            $organization->users()->attach($user, [
                'is_default' => false,
                'status' => $membershipStatus,
            ]);
            $freshUser = $this->organizationUser($organization, $user->public_id, true);
            $this->auditLogger->record($request, 'user.created', $user, [], $this->snapshot($freshUser, $organization));
            if ($roleIds !== []) {
                $this->auditLogger->record($request, 'user.roles_updated', $user, [], ['roles' => $this->roleSnapshot($freshUser, $organization)]);
            }

            return $freshUser;
        });

        return response()->json(['data' => $this->resource($user, $organization)], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $organization = $this->organization($request);

        return response()->json(['data' => $this->resource($this->organizationUser($organization, $publicId), $organization)]);
    }

    public function update(UpdateUserRequest $request, string $publicId): JsonResponse
    {
        $organization = $this->organization($request);
        $validated = $request->validated();
        $user = $this->organizationUser($organization, $publicId, true);
        $oldSnapshot = $this->snapshot($user, $organization);
        $rolesProvided = array_key_exists('role_ids', $validated);
        $roleIds = $validated['role_ids'] ?? [];
        $statusProvided = array_key_exists('status', $validated);
        $membershipStatus = $validated['status'] ?? $user->pivot->status;
        unset($validated['role_ids'], $validated['status']);

        $user = DB::transaction(function () use ($request, $organization, $user, $validated, $rolesProvided, $roleIds, $oldSnapshot, $statusProvided, $membershipStatus): User {
            $currentRoleIds = $this->roleIds($user, $organization);
            $newRoleIds = $rolesProvided ? $roleIds : $currentRoleIds;
            $this->ensureAdministratorProtection($organization, $user, $newRoleIds, $membershipStatus === 'inactive');
            $user->update($validated);
            if ($rolesProvided) {
                $this->syncRoles($user, $organization, $newRoleIds);
            }
            if ($statusProvided) {
                $organization->users()->updateExistingPivot($user->id, ['status' => $membershipStatus]);
            }
            $freshUser = $this->organizationUser($organization, $user->public_id, true);
            $newSnapshot = $this->snapshot($freshUser, $organization);
            $auditAction = 'user.updated';
            if ($statusProvided && $oldSnapshot['membership_status'] !== 'inactive' && $newSnapshot['membership_status'] === 'inactive') {
                $auditAction = 'user.deactivated';
            } elseif ($statusProvided && $oldSnapshot['membership_status'] !== 'active' && $newSnapshot['membership_status'] === 'active') {
                $auditAction = 'user.activated';
            }
            $this->auditLogger->record($request, $auditAction, $user, $oldSnapshot, $newSnapshot);
            if ($rolesProvided && $currentRoleIds !== $newRoleIds) {
                $this->auditLogger->record($request, 'user.roles_updated', $user, ['roles' => $oldSnapshot['roles']], ['roles' => $newSnapshot['roles']]);
            }

            return $freshUser;
        });

        return response()->json(['data' => $this->resource($user, $organization)]);
    }

    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $organization = $this->organization($request);
        $user = $this->organizationUser($organization, $publicId);

        $user = DB::transaction(function () use ($request, $organization, $user): User {
            $this->ensureAdministratorProtection($organization, $user, $this->roleIds($user, $organization), true);
            $oldSnapshot = $this->snapshot($user, $organization);
            $organization->users()->updateExistingPivot($user->id, ['status' => 'inactive']);
            $freshUser = $this->organizationUser($organization, $user->public_id, true);
            $this->auditLogger->record($request, 'user.deactivated', $user, $oldSnapshot, $this->snapshot($freshUser, $organization));

            return $freshUser;
        });

        return response()->json(['data' => $this->resource($user, $organization)]);
    }

    private function organization(Request $request): Organization
    {
        return $request->attributes->get('organization');
    }

    private function organizationUser(Organization $organization, string $publicId, bool $includeInactive = false): User
    {
        $query = $organization->users()
            ->where('users.public_id', $publicId);
        if (! $includeInactive) {
            $query->where('organization_user.status', 'active');
        }

        return $query->firstOrFail();
    }

    private function resource(User $user, Organization $organization): array
    {
        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'membership_status' => $user->pivot->status,
            'membership_is_default' => (bool) $user->pivot->is_default,
            'roles' => $this->roleSnapshot($user, $organization),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function snapshot(User $user, Organization $organization): array
    {
        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'membership_status' => $user->pivot->status,
            'roles' => $this->roleSnapshot($user, $organization),
        ];
    }

    private function roleSnapshot(User $user, Organization $organization): array
    {
        return $user->rolesForOrganization($organization)
            ->orderBy('roles.name')
            ->get(['roles.id', 'roles.name'])
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
            ->values()->all();
    }

    private function roleIds(User $user, Organization $organization): array
    {
        return $user->rolesForOrganization($organization)->orderBy('roles.id')->pluck('roles.id')->all();
    }

    private function syncRoles(User $user, Organization $organization, array $roleIds): void
    {
        DB::table('role_assignments')
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($roleIds !== []) {
            DB::table('role_assignments')->insert(array_map(fn (int $roleId): array => [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $roleIds));
        }
    }

    private function ensureAdministratorProtection(Organization $organization, User $user, array $newRoleIds, bool $deactivating): void
    {
        $isAdministrator = $user->rolesForOrganization($organization)
            ->whereRaw("lower(roles.name) in ('admin', 'administrator')")
            ->exists();
        $willRemainAdministrator = Role::query()
            ->whereIn('id', $newRoleIds)
            ->where(function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id)->orWhereNull('organization_id');
            })->whereRaw("lower(name) in ('admin', 'administrator')")
            ->exists();

        if (! $isAdministrator || (! $deactivating && $willRemainAdministrator)) {
            return;
        }

        $activeAdministrators = $this->activeAdministratorCount($organization);
        abort_if($activeAdministrators <= 1, 422, 'The organization must retain at least one active administrator.');
    }

    private function activeAdministratorCount(Organization $organization): int
    {
        return User::query()
            ->join('organization_user', 'organization_user.user_id', '=', 'users.id')
            ->join('role_assignments', function ($join) use ($organization): void {
                $join->on('role_assignments.user_id', '=', 'users.id')
                    ->where('role_assignments.organization_id', $organization->id);
            })
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->where('organization_user.organization_id', $organization->id)
            ->where('organization_user.status', 'active')
            ->where('users.status', 'active')
            ->whereRaw("lower(roles.name) in ('admin', 'administrator')")
            ->distinct('users.id')
            ->count('users.id');
    }
}
