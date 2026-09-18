<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOltQinqProvisionRequest;
use App\Http\Requests\StoreOltVlanProvisionRequest;
use App\Http\Requests\StoreOltDbaProfileRequest;
use App\Models\Olt;
use App\Models\OltQinqProvision;
use App\Models\OltVlanProvision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\OltProvisioningService;

class OltManagementController extends Controller
{
    public function show(string $publicId): JsonResponse
    {
        $olt = Olt::query()->with(['vlanProvisions', 'qinqProvisions', 'dbaProfiles'])->where('public_id', $publicId)->firstOrFail();
        return response()->json(['data' => $olt]);
    }

    public function storeVlan(StoreOltVlanProvisionRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $this->normalizeVlanDetails($olt, $request->validated()); $result = $provisioning->createVlan($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->vlanProvisions()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    public function destroyVlan(Request $request, string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->vlanProvisions()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteVlan($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function updateVlan(StoreOltVlanProvisionRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->vlanProvisions()->whereKey($id)->firstOrFail(); $values = $this->normalizeVlanDetails($olt, $request->validated(), $record->id);
        $result = $provisioning->createVlan($olt, $values); $values['status'] = $result['status']; $record->update($values);
        return response()->json(['data' => $record->fresh(), 'operation' => $result]);
    }

    public function storeQinq(StoreOltQinqProvisionRequest $request, string $publicId, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated(); $type = $values['qinq_type'] ?? 's_vlan';
        $values = $this->normalizeSVlanDetails($olt, $values);
        if ($type === 'ont_line_profile') {
            $values['profile_id'] = max(9, (int) ($olt->qinqProvisions()->where('qinq_type', 'ont_line_profile')->max('profile_id') ?? 9)) + 1;
            $values['ont_line_profile'] = 'LP_CVLAN_'.$values['outer_vlan'];
            $values['name'] = $values['ont_line_profile'];
        }
        $duplicate = $olt->qinqProvisions()->where('qinq_type', $type)->when($type === 's_vlan', fn ($query) => $query->where('outer_vlan', $values['outer_vlan']))->when($type === 'c_vlan', fn ($query) => $query->where('inner_vlan', $values['inner_vlan']))->when($type === 'ont_line_profile', fn ($query) => $query->where('profile_id', $values['profile_id']))->exists();
        if ($duplicate) return response()->json(['message' => 'This provisioning record already exists for the OLT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $result = $provisioning->createQinq($olt, $values);
        $values['status'] = $result['status'];
        return response()->json(['data' => $olt->qinqProvisions()->create($values), 'operation' => $result], Response::HTTP_CREATED);
    }

    private function normalizeSVlanDetails(Olt $olt, array $values, ?int $ignoreId = null): array
    {
        if (($values['qinq_type'] ?? 's_vlan') !== 's_vlan') return $values;
        $values['frame'] = 0;
        $values['slot'] = (int) $values['slot']; $values['port_number'] = (int) $values['port_number'];
        $values['port'] = "0/{$values['slot']} {$values['port_number']}";
        $qinqOccupied = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->where('slot', $values['slot'])->where('port_number', $values['port_number'])->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
        $tr069Occupied = $olt->vlanProvisions()->where('service_mode', 'tr069')->where('slot', $values['slot'])->where('port_number', $values['port_number'])->exists();
        if ($qinqOccupied || $tr069Occupied) abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'This GE port is already assigned to an S-VLAN or TR-069 VLAN.');
        $used = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->whereNotNull('service_port_id')->pluck('service_port_id')->map(fn ($id) => (int) $id)->all();
        $candidate = 1000; while (in_array($candidate, $used, true) && $candidate <= 9000) $candidate++;
        if ($candidate > 9000) abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No service-port IDs are available in the 1000-9000 range.');
        $values['service_port_id'] = $candidate;
        return $values;
    }

    private function normalizeVlanDetails(Olt $olt, array $values, ?int $ignoreId = null): array
    {
        if (($values['service_mode'] ?? 'internet') !== 'tr069') return $values;
        $values['frame'] = 0;
        $values['slot'] = (int) $values['slot'];
        $values['port_number'] = (int) $values['port_number'];
        $values['port'] = "0/{$values['slot']} {$values['port_number']}";
        $qinqOccupied = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->where('slot', $values['slot'])->where('port_number', $values['port_number'])->exists();
        $vlanOccupied = $olt->vlanProvisions()->where('service_mode', 'tr069')->where('slot', $values['slot'])->where('port_number', $values['port_number'])->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
        if ($qinqOccupied || $vlanOccupied) abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'This GE port is already assigned to an S-VLAN or TR-069 VLAN.');
        return $values;
    }

    public function destroyQinq(Request $request, string $publicId, int $id): Response
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->qinqProvisions()->whereKey($id)->firstOrFail();
        app(OltProvisioningService::class)->deleteQinq($olt, $record->toArray());
        $record->delete();
        return response()->noContent();
    }

    public function updateQinq(StoreOltQinqProvisionRequest $request, string $publicId, int $id, OltProvisioningService $provisioning): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $record = $olt->qinqProvisions()->whereKey($id)->firstOrFail(); $values = $request->validated(); $values = $this->normalizeSVlanDetails($olt, $values, $record->id); if (($values['qinq_type'] ?? 's_vlan') === 's_vlan') $values['service_port_id'] = $record->service_port_id;
        if (($values['qinq_type'] ?? 's_vlan') === 'ont_line_profile') {
            $values['profile_id'] = $record->profile_id;
            $values['ont_line_profile'] = $record->ont_line_profile ?: 'LP_CVLAN_'.$values['outer_vlan'];
            $values['name'] = $record->name ?: $values['ont_line_profile'];
        }
        $result = $provisioning->createQinq($olt, $values); $values['status'] = $result['status']; $record->update($values);
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

    public function updateDbaProfileStart(Request $request, string $publicId): JsonResponse
    {
        $values = $request->validate(['dba_profile_start_id' => ['required', 'integer', 'min:10', 'max:515', 'multiple_of:5']]);
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $olt->update($values);
        return response()->json(['data' => ['dba_profile_start_id' => $olt->dba_profile_start_id]]);
    }
}
