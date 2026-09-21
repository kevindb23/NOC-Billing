<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOntRequest;
use App\Models\AcsServer;
use App\Models\Ont;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\OntSetting;
use App\Services\OltSessionService;
use App\Services\AcsServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OntController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Ont::query()
                ->select(['id', 'public_id', 'olt_id', 'acs_server_id', 'frame', 'slot', 'pon_port', 'ont_id', 'serial_number', 'name', 'status', 'last_discovered_at', 'created_at'])
                ->with(['olt:id,public_id,name,vendor', 'acsServer:id,public_id,name,status'])
                ->latest('id')
                ->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function settings(): JsonResponse
    {
        $setting = $this->setting();
        return response()->json(['data' => [
            'do_not_allow_rogue_onus' => (bool) $setting->do_not_allow_rogue_onus,
            'ont_id_capacity_per_port' => (int) $setting->ont_id_capacity_per_port,
        ]]);
    }

    public function acsServers(): JsonResponse
    {
        return response()->json([
            'data' => AcsServer::query()
                ->select(['id', 'public_id', 'name', 'status'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $values = $request->validate([
            'do_not_allow_rogue_onus' => ['required', 'boolean'],
            'ont_id_capacity_per_port' => ['sometimes', 'required', 'integer', 'between:1,256'],
        ]);
        $setting = $this->setting();
        $setting->update($values);

        if (array_key_exists('ont_id_capacity_per_port', $values)) {
            Olt::query()->update(['ont_id_capacity_per_port' => $values['ont_id_capacity_per_port']]);
        }

        Ont::query()
            ->whereIn('status', ['rogue', 'discovered'])
            ->update(['status' => $setting->do_not_allow_rogue_onus ? 'rogue' : 'discovered']);

        $setting = $setting->fresh();
        return response()->json(['data' => [
            'do_not_allow_rogue_onus' => (bool) $setting->do_not_allow_rogue_onus,
            'ont_id_capacity_per_port' => (int) $setting->ont_id_capacity_per_port,
        ]]);
    }

    public function store(StoreOntRequest $request): JsonResponse
    {
        $values = $request->validated();
        $olt = Olt::query()->where('public_id', $values['olt_public_id'])->firstOrFail();
        $acsServer = $this->acsServer($values['acs_server_public_id'] ?? null);
        $this->ensureSerialIsAllowed($values, true);
        unset($values['olt_public_id']);
        unset($values['acs_server_public_id']);

        $this->ensureSerialIsAvailable($olt, $values['serial_number']);
        $ont = $olt->onts()->create($values + ['acs_server_id' => $acsServer?->id, 'status' => $values['status'] ?? 'unknown']);

        return response()->json(['data' => $ont->fresh()->load(['olt:id,public_id,name,vendor', 'acsServer:id,public_id,name,status'])], Response::HTTP_CREATED);
    }

    public function show(string $publicId): JsonResponse
    {
        return response()->json([
            'data' => Ont::query()->with(['olt:id,public_id,name,vendor', 'acsServer:id,public_id,name,status'])->where('public_id', $publicId)->firstOrFail(),
        ]);
    }

    public function management(string $publicId, AcsServerService $acs): JsonResponse
    {
        $ont = Ont::query()->with('acsServer')->where('public_id', $publicId)->firstOrFail();

        try {
            return response()->json(['data' => $acs->deviceForOnt($ont)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function exportConfiguration(string $publicId, AcsServerService $acs): JsonResponse|Response
    {
        $ont = Ont::query()->with('acsServer')->where('public_id', $publicId)->firstOrFail();

        try {
            $export = $acs->exportConfiguration($ont);

            return response($export['content'], Response::HTTP_OK, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="hw_ctree.xml"',
                'Cache-Control' => 'no-store',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function managementTask(Request $request, string $publicId, AcsServerService $acs): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:refresh_device,refresh_wifi,refresh_lan,reboot,firmware,update_wifi,update_upstream_port,configure_pppoe'],
            'upstream_port' => ['nullable', 'string', 'in:optical,lan1,lan2,lan3,lan4', 'required_if:action,update_upstream_port'],
            'firmware_url' => ['nullable', 'url', 'max:2000', 'required_if:action,firmware'],
            'wlan' => ['nullable', 'array', 'required_if:action,update_wifi'],
            'wlan.band_24' => ['nullable', 'array', 'required_if:action,update_wifi'],
            'wlan.band_24.enabled' => ['required_with:wlan.band_24', 'boolean'],
            'wlan.band_24.ssid' => ['required_with:wlan.band_24', 'string', 'max:64'],
            'wlan.band_24.password' => ['nullable', 'string', 'max:128'],
            'wlan.band_24.hide_ssid' => ['required_with:wlan.band_24', 'boolean'],
            'wlan.band_24.auto_channel' => ['required_with:wlan.band_24', 'boolean'],
            'wlan.band_24.channel' => ['nullable', 'integer', 'min:0', 'max:200'],
            'wlan.band_5' => ['nullable', 'array'],
            'wlan.band_5.enabled' => ['required_with:wlan.band_5', 'boolean'],
            'wlan.band_5.ssid' => ['required_with:wlan.band_5', 'string', 'max:64'],
            'wlan.band_5.password' => ['nullable', 'string', 'max:128'],
            'wlan.band_5.hide_ssid' => ['required_with:wlan.band_5', 'boolean'],
            'wlan.band_5.auto_channel' => ['required_with:wlan.band_5', 'boolean'],
            'wlan.band_5.channel' => ['nullable', 'integer', 'min:0', 'max:200'],
            'pppoe_username' => ['nullable', 'string', 'max:120', 'required_if:action,configure_pppoe'],
            'pppoe_password' => ['nullable', 'string', 'max:255', 'required_if:action,configure_pppoe'],
        ]);
        $ont = Ont::query()->with('acsServer')->where('public_id', $publicId)->firstOrFail();

        try {
            return response()->json(['data' => $acs->enqueueOntTask($ont, $validated['action'], $validated['firmware_url'] ?? null, $validated['wlan'] ?? null, $validated['upstream_port'] ?? null, isset($validated['pppoe_username']) ? ['username' => $validated['pppoe_username'], 'password' => $validated['pppoe_password']] : null)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function update(StoreOntRequest $request, string $publicId): JsonResponse
    {
        $ont = Ont::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated();
        $olt = Olt::query()->where('public_id', $values['olt_public_id'])->firstOrFail();
        $acsServer = array_key_exists('acs_server_public_id', $values) ? $this->acsServer($values['acs_server_public_id']) : null;
        $this->ensureSerialIsAllowed($values);
        unset($values['olt_public_id']);
        if (array_key_exists('acs_server_public_id', $values)) {
            unset($values['acs_server_public_id']);
            $values['acs_server_id'] = $acsServer?->id;
        }

        $this->ensureSerialIsAvailable($olt, $values['serial_number'], $ont->id);
        $ont->update($values + ['olt_id' => $olt->id]);

        return response()->json(['data' => $ont->fresh()->load(['olt:id,public_id,name,vendor', 'acsServer:id,public_id,name,status'])]);
    }

    public function destroy(string $publicId): Response
    {
        Ont::query()->where('public_id', $publicId)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function discover(Request $request, OltSessionService $sessions): JsonResponse
    {
        $validated = $request->validate(['olt_public_id' => ['required', 'string', 'exists:olts,public_id']]);
        $olt = Olt::query()->where('public_id', $validated['olt_public_id'])->firstOrFail();

        try {
            $result = $sessions->discover($olt);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $records = is_array($result['output'] ?? null) ? $result['output'] : [];
        $discovered = 0;
        $updated = 0;
        $skipped = 0;

        $status = $this->setting()->do_not_allow_rogue_onus ? 'rogue' : 'discovered';

        DB::transaction(function () use ($olt, $records, $status, &$discovered, &$updated, &$skipped): void {
            foreach ($records as $record) {
                if (! is_array($record) || blank($record['serial_number'] ?? null)) {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'frame' => (int) ($record['frame'] ?? 0),
                    'slot' => (int) ($record['slot'] ?? 0),
                    'pon_port' => (int) ($record['pon_port'] ?? 0),
                    'ont_id' => isset($record['ont_id']) && $record['ont_id'] !== '' ? (int) $record['ont_id'] : null,
                    'name' => $record['name'] ?? 'ONT '.$record['serial_number'],
                    'status' => $status,
                    'last_discovered_at' => now(),
                ];
                $ont = $olt->onts()->where('serial_number', $record['serial_number'])->first();
                if ($ont) {
                    $ont->update($payload);
                    $updated++;
                } else {
                    $olt->onts()->create($payload + ['serial_number' => $record['serial_number']]);
                    $discovered++;
                }
            }
        });

        return response()->json([
            'data' => [
                'discovered' => $discovered,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => ($discovered + $updated) === 0
                    ? 'No unregistered ONTs were returned. Verify ONU auto-discovery is enabled on the OLT PON ports.'
                    : null,
                'records' => $olt->onts()->with(['olt:id,public_id,name,vendor', 'acsServer:id,public_id,name,status'])->latest('last_discovered_at')->get(),
            ],
        ]);
    }

    private function ensureSerialIsAvailable(Olt $olt, string $serialNumber, ?int $ignoreId = null): void
    {
        $exists = $olt->onts()->where('serial_number', $serialNumber)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['serial_number' => 'This serial number is already registered on the selected OLT.']);
        }
    }

    private function setting(): OntSetting
    {
        return OntSetting::query()->firstOrCreate([], ['do_not_allow_rogue_onus' => false, 'ont_id_capacity_per_port' => 64]);
    }

    private function acsServer(?string $publicId): ?AcsServer
    {
        return blank($publicId) ? null : AcsServer::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** @param array<string, mixed> $values */
    private function ensureSerialIsAllowed(array $values, bool $creating = false): void
    {
        if (! $this->setting()->do_not_allow_rogue_onus || (! $creating && ! array_key_exists('serial_number', $values))) {
            return;
        }

        $serialNumber = (string) ($values['serial_number'] ?? '');
        if ($serialNumber === '' || ! Onu::query()->where('serial_number', $serialNumber)->exists()) {
            throw ValidationException::withMessages(['serial_number' => 'Select a serial number that exists in ONU inventory while rogue ONU protection is enabled.']);
        }
    }
}
