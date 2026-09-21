<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOltQinqProvisionRequest;
use App\Http\Requests\StoreOltVlanProvisionRequest;
use App\Http\Requests\StoreOltDbaProfileRequest;
use App\Http\Requests\StoreOltOntServiceProfileRequest;
use App\Http\Requests\StoreOltOntWanProfileRequest;
use App\Http\Requests\StoreOltOntTr069ServerProfileRequest;
use App\Http\Requests\StoreOltTerminalUserRequest;
use App\Models\Olt;
use App\Models\OltQinqProvision;
use App\Models\OltVlanProvision;
use App\Models\OltOntServiceProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\OltProvisioningService;
use App\Services\BngVlanSyncService;

class OltManagementController extends Controller
{
    public function show(string $publicId): JsonResponse
    {
        $olt = Olt::query()->with(['vlanProvisions', 'qinqProvisions', 'dbaProfiles', 'ontServiceProfiles', 'ontWanProfiles', 'ontTr069ServerProfiles', 'terminalUsers'])->where('public_id', $publicId)->firstOrFail();
        return response()->json(['data' => $olt]);
    }

    public function terminalUsers(string $publicId): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        return response()->json(['data' => $olt->terminalUsers()->latest('id')->get()]);
    }

    public function storeTerminalUser(StoreOltTerminalUserRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated();
        if ($olt->terminalUsers()->where('username', $values['username'])->exists()) {
            return response()->json(['message' => 'This terminal username already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $provisioning->createTerminalUser($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->terminalUsers()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    public function updateTerminalUser(StoreOltTerminalUserRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->terminalUsers()->whereKey($id)->firstOrFail();
        $values = $request->validated();
        if ($olt->terminalUsers()->where('username', $values['username'])->where('id', '!=', $record->id)->exists()) {
            return response()->json(['message' => 'This terminal username already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $deviceValues = array_merge($record->only(['username', 'profile_name', 'privilege_level', 'reenter_limit', 'appended_info']), $values, ['password' => $values['password'] ?? $record->password]);
        $result = $provisioning->replaceTerminalUser($olt, $deviceValues);
        if (! array_key_exists('password', $values)) unset($values['password']);
        $values['status'] = $result['status'];
        $record->update($values);
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function destroyTerminalUser(string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->terminalUsers()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteTerminalUser($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function updateTerminalUserPolicy(Request $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $values = $request->validate([
            'security_enabled' => ['required', 'boolean'],
            'security_length' => ['required', 'integer', 'between:6,128'],
        ]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $result = $provisioning->updateTerminalUserPolicy($olt, $values);
        $olt->update([
            'terminal_user_security_enabled' => $values['security_enabled'],
            'terminal_user_security_length' => $values['security_length'],
        ]);
        return response()->json(['data' => [
            'security_enabled' => (bool) $olt->terminal_user_security_enabled,
            'security_length' => (int) $olt->terminal_user_security_length,
        ], 'operation' => $result]);
    }

    public function storeVlan(StoreOltVlanProvisionRequest $request, string $publicId, OltProvisioningService $provisioning, BngVlanSyncService $vlanSync): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $this->normalizeVlanDetails($olt, $request->validated()); $result = $provisioning->createVlan($olt, $values);
        $values['status'] = $result['status']; $record = $olt->vlanProvisions()->create($values);
        $result['bng_sync'] = $vlanSync->syncNormalVlan($olt, $record->toArray());
        return response()->json(['data' => $record, 'operation' => $result], Response::HTTP_CREATED);
    }

    public function destroyVlan(Request $request, string $publicId, int $id, BngVlanSyncService $vlanSync): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->vlanProvisions()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteVlan($olt, $record->toArray());
        $vlanSync->removeNormalVlan($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function updateVlan(StoreOltVlanProvisionRequest $request, string $publicId, int $id, OltProvisioningService $provisioning, BngVlanSyncService $vlanSync): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->vlanProvisions()->whereKey($id)->firstOrFail(); $previous = $record->toArray(); $values = $this->normalizeVlanDetails($olt, $request->validated(), $record->id);
        $result = $provisioning->createVlan($olt, $values); $values['status'] = $result['status']; $record->update($values);
        if ((int) ($previous['vlan_id'] ?? 0) !== (int) ($record->vlan_id ?? 0)) $vlanSync->removeNormalVlan($olt, $previous);
        $result['bng_sync'] = $vlanSync->syncNormalVlan($olt, $record->toArray());
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function storeQinq(StoreOltQinqProvisionRequest $request, string $publicId, OltProvisioningService $provisioning, BngVlanSyncService $vlanSync): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated(); $type = $values['qinq_type'] ?? 's_vlan';
        $values = $this->normalizeSVlanDetails($olt, $values);
        if ($type === 'ont_line_profile') {
            $values['profile_id'] = $this->nextOntLineProfileId($olt);
            $values['ont_line_profile'] = 'LP_CVLAN_'.$values['outer_vlan'];
            $values['name'] = $values['ont_line_profile'];
            $values['tr069_management_enabled'] = (bool) ($values['tr069_management_enabled'] ?? true);
            $values['tr069_ip_index'] = (int) ($values['tr069_ip_index'] ?? 1);
            $values['omcc_encrypt_enabled'] = (bool) ($values['omcc_encrypt_enabled'] ?? true);
        }
        $duplicate = $olt->qinqProvisions()->where('qinq_type', $type)->when($type === 's_vlan', fn ($query) => $query->where('outer_vlan', $values['outer_vlan']))->when($type === 'c_vlan', fn ($query) => $query->where('inner_vlan', $values['inner_vlan']))->when($type === 'ont_line_profile', fn ($query) => $query->where('profile_id', $values['profile_id']))->exists();
        if ($duplicate) return response()->json(['message' => 'This provisioning record already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $result = $provisioning->createQinq($olt, $values);
        $values['status'] = $result['status']; $record = $olt->qinqProvisions()->create($values);
        $result['bng_sync'] = $vlanSync->syncQinq($olt, $record->toArray());
        return response()->json(['data' => $record, 'operation' => $result], Response::HTTP_CREATED);
    }

    public function previewQinq(Request $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $values = $request->validate([
            'qinq_type' => ['required', 'in:ont_line_profile'],
            'outer_vlan' => ['required', 'integer', 'between:1,4094'],
            'inner_vlan' => ['required', 'integer', 'between:1,4094'],
            'dba_profile_id' => ['required', 'integer', 'between:1,65535'],
            'profile_id' => ['nullable', 'integer', 'between:0,8195'],
            'tr069_management_enabled' => ['sometimes', 'boolean'],
            'tr069_ip_index' => ['sometimes', 'integer', 'in:0,1,2,3'],
            'omcc_encrypt_enabled' => ['sometimes', 'boolean'],
        ]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values['profile_id'] = (int) ($values['profile_id'] ?? $this->nextOntLineProfileId($olt));
        $values['profile_name'] = 'LP_CVLAN_'.$values['outer_vlan'];
        $values['tr069_management_enabled'] = (bool) ($values['tr069_management_enabled'] ?? true);
        $values['tr069_ip_index'] = (int) ($values['tr069_ip_index'] ?? 1);
        $values['omcc_encrypt_enabled'] = (bool) ($values['omcc_encrypt_enabled'] ?? true);
        $values['internet_vlan'] = $values['outer_vlan'];
        $values['tr069_vlan'] = $values['inner_vlan'];

        $result = $provisioning->previewQinq($olt, $values);

        return response()->json(['data' => [
            'commands' => $result['commands'] ?? [],
            'message' => 'These commands will be sent to the OLT when the line profile is added.',
        ]]);
    }

    private function normalizeSVlanDetails(Olt $olt, array $values, ?int $ignoreId = null): array
    {
        if (($values['qinq_type'] ?? 's_vlan') !== 's_vlan') return $values;
        $values['frame'] = 0;
        $values['slot'] = (int) $values['slot']; $values['port_number'] = (int) $values['port_number'];
        $values['port'] = "0/{$values['slot']} {$values['port_number']}";
        $used = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->whereNotNull('service_port_id')->pluck('service_port_id')->map(fn ($id) => (int) $id)->all();
        $candidate = 1000; while (in_array($candidate, $used, true) && $candidate <= 9000) $candidate++;
        if ($candidate > 9000) abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No service-port IDs are available in the 1000-9000 range.');
        $values['service_port_id'] = $candidate;
        return $values;
    }

    private function nextOntLineProfileId(Olt $olt): int
    {
        $startId = (int) ($olt->ont_line_profile_start_id ?? 0);
        $nextExistingId = ((int) ($olt->qinqProvisions()->where('qinq_type', 'ont_line_profile')->max('profile_id') ?? ($startId - 1))) + 1;

        return max($startId, $nextExistingId);
    }

    private function normalizeVlanDetails(Olt $olt, array $values, ?int $ignoreId = null): array
    {
        if (($values['service_mode'] ?? 'internet') !== 'tr069') return $values;
        $values['frame'] = 0;
        $values['slot'] = (int) $values['slot'];
        $values['port_number'] = (int) $values['port_number'];
        $values['port'] = "0/{$values['slot']} {$values['port_number']}";
        return $values;
    }

    public function destroyQinq(Request $request, string $publicId, int $id, BngVlanSyncService $vlanSync): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->qinqProvisions()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteQinq($olt, $record->toArray());
        $vlanSync->removeQinq($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function updateQinq(StoreOltQinqProvisionRequest $request, string $publicId, int $id, OltProvisioningService $provisioning, BngVlanSyncService $vlanSync): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->qinqProvisions()->whereKey($id)->firstOrFail(); $previous = $record->toArray(); $values = $request->validated(); $values = $this->normalizeSVlanDetails($olt, $values, $record->id); if (($values['qinq_type'] ?? 's_vlan') === 's_vlan') $values['service_port_id'] = $record->service_port_id;
        if (($values['qinq_type'] ?? 's_vlan') === 'ont_line_profile') {
            $values['profile_id'] = $record->profile_id;
            $values['ont_line_profile'] = $record->ont_line_profile ?: 'LP_CVLAN_'.$values['outer_vlan'];
            $values['name'] = $record->name ?: $values['ont_line_profile'];
            $values['tr069_management_enabled'] = (bool) ($values['tr069_management_enabled'] ?? $record->tr069_management_enabled ?? true);
            $values['tr069_ip_index'] = (int) ($values['tr069_ip_index'] ?? $record->tr069_ip_index ?? 1);
            $values['omcc_encrypt_enabled'] = (bool) ($values['omcc_encrypt_enabled'] ?? $record->omcc_encrypt_enabled ?? true);
        }
        $result = $provisioning->createQinq($olt, $values); $values['status'] = $result['status']; $record->update($values);
        if (($previous['qinq_type'] ?? 's_vlan') !== ($record->qinq_type ?? 's_vlan') || (int) ($previous['outer_vlan'] ?? 0) !== (int) ($record->outer_vlan ?? 0) || (int) ($previous['inner_vlan'] ?? 0) !== (int) ($record->inner_vlan ?? 0)) $vlanSync->removeQinq($olt, $previous);
        $result['bng_sync'] = $vlanSync->syncQinq($olt, $record->toArray());
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function storeDbaProfile(StoreOltDbaProfileRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated();
        $values['profile_id'] = $values['profile_id'] ?? max((int) ($olt->dba_profile_start_id ?: 10), ((int) ($olt->dbaProfiles()->max('profile_id') ?? 5) + 5));
        if ($values['profile_id'] > 515) return response()->json(['message' => 'No DBA profile IDs are available in the 10-515 range.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $values['profile_name'] = 'DBA_'.$values['bandwidth_mbps'].'MBPS';
        if ($olt->dbaProfiles()->where('profile_id', $values['profile_id'])->exists()) return response()->json(['message' => 'This DBA profile ID already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $result = $provisioning->createDbaProfile($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->dbaProfiles()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    public function destroyDbaProfile(string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->dbaProfiles()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteDbaProfile($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function storeOntServiceProfile(StoreOltOntServiceProfileRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $this->normalizeOntServiceProfile($request->validated());
        if ($olt->ontServiceProfiles()->where('profile_id', $values['profile_id'])->exists()) {
            return response()->json(['message' => 'This ONT service profile ID already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $provisioning->createOntServiceProfile($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->ontServiceProfiles()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    public function updateOntServiceProfile(StoreOltOntServiceProfileRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->ontServiceProfiles()->whereKey($id)->firstOrFail();
        $values = $this->normalizeOntServiceProfile($request->validated());
        if ($olt->ontServiceProfiles()->where('profile_id', $values['profile_id'])->where('id', '!=', $record->id)->exists()) {
            return response()->json(['message' => 'This ONT service profile ID already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $provisioning->createOntServiceProfile($olt, $values);
        $values['status'] = $result['status'];
        $record->update($values);
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function destroyOntServiceProfile(string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->ontServiceProfiles()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteOntServiceProfile($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function storeOntWanProfile(StoreOltOntWanProfileRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail(); $values = $request->validated();
        if ($olt->ontWanProfiles()->where('profile_id', $values['profile_id'])->exists()) return response()->json(['message' => 'This ONT WAN profile ID already exists for the OLT.'], 422);
        $result = $provisioning->createOntWanProfile($olt, $values); $values['status'] = $result['status'];
        return response()->json(['data' => $olt->ontWanProfiles()->create($values), 'operation' => $result], 201);
    }

    public function updateOntWanProfile(StoreOltOntWanProfileRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail(); $record = $olt->ontWanProfiles()->whereKey($id)->firstOrFail(); $values = $request->validated();
        if ($olt->ontWanProfiles()->where('profile_id', $values['profile_id'])->where('id', '!=', $record->id)->exists()) return response()->json(['message' => 'This ONT WAN profile ID already exists for the OLT.'], 422);
        $result = $provisioning->createOntWanProfile($olt, $values); $values['status'] = $result['status']; $record->update($values);
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function destroyOntWanProfile(string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail(); $record = $olt->ontWanProfiles()->whereKey($id)->firstOrFail(); app(OltProvisioningService::class)->deleteOntWanProfile($olt, $record->toArray()); $record->delete(); return response()->noContent();
    }

    public function storeOntTr069ServerProfile(StoreOltOntTr069ServerProfileRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated();
        if ($olt->ontTr069ServerProfiles()->where('profile_id', $values['profile_id'])->exists()) {
            return response()->json(['message' => 'This TR-069 server profile ID already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $provisioning->createOntTr069ServerProfile($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->ontTr069ServerProfiles()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    public function updateOntTr069ServerProfile(StoreOltOntTr069ServerProfileRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->ontTr069ServerProfiles()->whereKey($id)->firstOrFail();
        $values = $request->validated();
        if (blank($values['password'] ?? null)) unset($values['password']);
        if ($olt->ontTr069ServerProfiles()->where('profile_id', $values['profile_id'])->where('id', '!=', $record->id)->exists()) {
            return response()->json(['message' => 'This TR-069 server profile ID already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $provisioning->createOntTr069ServerProfile($olt, array_merge($record->toArray(), $values));
        $values['status'] = $result['status'];
        $record->update($values);
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function destroyOntTr069ServerProfile(string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->ontTr069ServerProfiles()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteOntTr069ServerProfile($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    private function normalizeOntServiceProfile(array $values): array
    {
        $count = (int) $values['eth_port_count'];
        $modes = [];
        for ($port = 1; $port <= $count; $port++) {
            $modes[(string) $port] = $values['port_modes'][(string) $port] ?? $values['port_modes'][$port] ?? 'transparent';
        }
        $values['port_modes'] = $modes;
        return $values;
    }

    public function updateDbaProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['dba_profile_start_id' => ['required', 'integer', 'min:10', 'max:515', 'multiple_of:5']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['dba_profile_start_id' => $olt->dba_profile_start_id]]);
    }

    public function updateOntServiceProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['ont_service_profile_start_id' => ['required', 'integer', 'between:0,8192']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['ont_service_profile_start_id' => $olt->ont_service_profile_start_id]]);
    }

    public function updateOntWanProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['ont_wan_profile_start_id' => ['required', 'integer', 'between:0,63']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['ont_wan_profile_start_id' => $olt->ont_wan_profile_start_id]]);
    }

    public function updateOntTr069ProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['ont_tr069_profile_start_id' => ['required', 'integer', 'between:1,32']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['ont_tr069_profile_start_id' => $olt->ont_tr069_profile_start_id]]);
    }

    public function updateOntLineProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['ont_line_profile_start_id' => ['required', 'integer', 'between:0,8195']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['ont_line_profile_start_id' => $olt->ont_line_profile_start_id]]);
    }

    public function updateSVlanStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['s_vlan_start_id' => ['required', 'integer', 'between:1,4094']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['s_vlan_start_id' => $olt->s_vlan_start_id]]);
    }

    public function updateCVlanStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['c_vlan_start_id' => ['required', 'integer', 'between:1,4094']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['c_vlan_start_id' => $olt->c_vlan_start_id]]);
    }

    public function updateTr069VlanStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['tr069_vlan_start_id' => ['required', 'integer', 'between:1,4094']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['tr069_vlan_start_id' => $olt->tr069_vlan_start_id]]);
    }

    public function updateVlanStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['vlan_start_id' => ['required', 'integer', 'between:1,4094']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['vlan_start_id' => $olt->vlan_start_id]]);
    }

    public function updateOntIdCapacity(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['ont_id_capacity_per_port' => ['required', 'integer', 'between:1,256']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['ont_id_capacity_per_port' => $olt->ont_id_capacity_per_port]]);
    }
}
