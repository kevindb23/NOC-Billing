<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\ActivationPreset;
use App\Models\AcsServer;
use App\Models\BillingAccount;
use App\Models\BillingStatement;
use App\Models\Customer;
use App\Models\Ont;
use App\Models\Olt;
use App\Models\OltDbaProfile;
use App\Models\OltOntServiceProfile;
use App\Models\OltOntTr069ServerProfile;
use App\Models\OltOntWanProfile;
use App\Models\OltQinqProvision;
use App\Models\OltVlanProvision;
use App\Models\OrganizationBillingSetting;
use App\Models\PlanVersion;
use App\Models\SubscriberService;
use App\Models\Subscription;
use App\Services\NumberGenerator;
use App\Services\AcsServerService;
use App\Services\OltProvisioningService;
use App\Services\RadiusSubscriberSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ActivationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Activation::query()
                ->select(['id', 'public_id', 'subscriber_service_id', 'subscription_id', 'olt_id', 'activation_preset_id', 'vlan_provision_id', 'qinq_provision_id', 'ont_id', 'provisioning_type', 'c_vlan', 's_vlan', 'status', 'activated_at', 'created_at'])
                ->with([
                    'service:id,public_id,customer_id,service_number',
                    'service.customer:id,public_id,customer_number,legal_name',
                    'subscription:id,plan_version_id,status,starts_on,ends_on',
                    'subscription.planVersion:id,plan_id,version,status,recurring_price_minor,currency,download_kbps,upload_kbps',
                    'subscription.planVersion.plan:id,public_id,name,code,status',
                    'ont:id,public_id,olt_id,name,serial_number,status,ont_id',
                    'ont.olt:id,public_id,name,vendor,model',
                    'olt:id,public_id,name,vendor,model',
                    'preset:id,public_id,name',
                    'vlanProvision:id,name,vlan_id,vlan_type,service_mode,status',
                    'qinqProvision:id,name,outer_vlan,inner_vlan,qinq_type,status',
                ])
                ->latest('id')
                ->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['olt_public_id' => ['required', 'string']]);
        $olt = Olt::query()->where('public_id', $data['olt_public_id'])->firstOrFail();

        $vlanProvisions = $olt->vlanProvisions()->where('status', '!=', 'archived')->where('service_mode', '!=', 'tr069')->orderBy('name')->get();
        $qinqProvisions = $olt->qinqProvisions()
            ->whereNotNull('inner_vlan')
            ->where('qinq_type', '!=', 'ont_line_profile')
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();
        $lineProfiles = $olt->qinqProvisions()->where('qinq_type', 'ont_line_profile')->where('status', '!=', 'archived')->orderBy('name')->get();

        $hasVlan = $vlanProvisions->isNotEmpty();
        $hasQinq = $qinqProvisions->isNotEmpty();

        return response()->json(['data' => [
            'olt' => $olt->only(['public_id', 'name', 'vendor', 'model', 'preferred_transport']),
            'setup' => ['type' => $hasVlan && $hasQinq ? 'mixed' : ($hasQinq ? 'qinq' : ($hasVlan ? 'vlan' : 'none')),
                'vlan_provisions' => $vlanProvisions->map(fn (OltVlanProvision $item) => $item->only(['id', 'name', 'vlan_id', 'vlan_type', 'service_mode', 'status'])),
                'qinq_provisions' => $qinqProvisions->map(function (OltQinqProvision $item) use ($olt): array {
                    $payload = $item->only(['id', 'name', 'outer_vlan', 'inner_vlan', 'qinq_type', 'status']);
                    $payload['outer_vlan'] ??= $this->resolveQinqOuterVlan($olt, $item);
                    return $payload;
                }),
            ],
            'profiles' => [
                'dba' => $olt->dbaProfiles()->where('status', '!=', 'archived')->orderBy('profile_name')->get()->map(fn (OltDbaProfile $item) => $item->only(['id', 'profile_id', 'profile_name', 'bandwidth_mbps', 'status'])),
                'ont_line' => $lineProfiles->map(fn (OltQinqProvision $item) => $item->only(['id', 'profile_id', 'name', 'ont_line_profile', 'dba_profile_id', 'status'])),
                'ont_service' => $olt->ontServiceProfiles()->where('status', '!=', 'archived')->orderBy('profile_name')->get()->map(fn (OltOntServiceProfile $item) => $item->only(['id', 'profile_id', 'profile_name', 'status'])),
                'ont_wan' => $olt->ontWanProfiles()->where('status', '!=', 'archived')->orderBy('profile_name')->get()->map(fn (OltOntWanProfile $item) => $item->only(['id', 'profile_id', 'profile_name', 'nat_enabled', 'status'])),
                'tr069' => $olt->ontTr069ServerProfiles()->where('status', '!=', 'archived')->orderBy('profile_name')->get()->map(fn (OltOntTr069ServerProfile $item) => $item->only(['id', 'profile_id', 'profile_name', 'url', 'username', 'status'])),
            ],
            'presets' => $this->presetQuery($olt)->get()->map(fn (ActivationPreset $preset) => $this->presetPayload($preset)),
            'acs_servers' => AcsServer::query()->where('status', 'active')->orderBy('name')->get(['public_id', 'name', 'status']),
        ]]);
    }

    public function preview(Request $request, OltProvisioningService $provisioning): JsonResponse
    {
        $data = $this->validated($request);

        if ($data['provisioning_type'] === 'vlan') {
            return response()->json(['data' => [
                'commands' => [],
                'message' => 'Normal VLAN activation does not currently push a direct OLT command.',
                'supported' => false,
            ]]);
        }

        $olt = Olt::query()->where('public_id', $data['olt_public_id'])->firstOrFail();
        $ont = Ont::query()->where('public_id', $data['ont_public_id'])->firstOrFail();
        abort_if((int) $ont->olt_id !== (int) $olt->id, 422, 'The selected ONT does not belong to the selected OLT.');
        $requestedOntId = array_key_exists('ont_id', $data) && $data['ont_id'] !== null ? (int) $data['ont_id'] : null;
        $ontId = $this->resolveOntId($olt, $ont, $requestedOntId);

        $preset = null;
        if (!empty($data['preset_public_id'])) {
            $preset = ActivationPreset::query()->where('public_id', $data['preset_public_id'])->firstOrFail();
            abort_if((int) $preset->olt_id !== (int) $olt->id, 422, 'The selected preset does not belong to the selected OLT.');
        }

        $qinq = OltQinqProvision::query()->findOrFail($data['qinq_provision_id']);
        abort_if((int) $qinq->olt_id !== (int) $olt->id || $qinq->qinq_type === 'ont_line_profile', 422, 'The selected QinQ setup does not belong to the selected OLT.');
        abort_if($qinq->outer_vlan === null && $qinq->inner_vlan === null, 422, 'The selected QinQ setup has no VLAN values.');

        $outerVlan = $this->resolveQinqOuterVlan($olt, $qinq);
        abort_if($outerVlan === null, 422, 'The selected C-VLAN is not linked to an S-VLAN. Edit the C-VLAN provisioning record first.');
        $sVlan = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->where('outer_vlan', $outerVlan)->first();
        abort_if(! $sVlan, 422, 'The selected C-VLAN is not linked to an S-VLAN. Edit the C-VLAN provisioning record first.');
        $tr069Vlan = $olt->vlanProvisions()->where('service_mode', 'tr069')->orderBy('id')->first();
        $profiles = $this->activationProfiles($olt, $preset);
        $this->ensureActivationProfilesComplete($olt, $preset, $profiles);

        $result = $provisioning->previewOntActivation($olt, [
            'frame' => $ont->frame,
            'slot' => $ont->slot,
            'pon_port' => $ont->pon_port,
            'ont_id' => $ontId,
            'serial_number' => $ont->serial_number,
            'description' => 'SUB_'.$qinq->inner_vlan.'_'.$outerVlan,
            'line_profile_id' => $profiles['line_profile_id'],
            'service_profile_id' => $profiles['service_profile_id'],
            'tr069_profile_id' => $profiles['tr069_profile_id'],
            'wan_profile_ids' => $profiles['wan_profile_ids'],
            'c_vlan' => $qinq->inner_vlan,
            's_vlan' => $outerVlan,
            'tr069_vlan' => $tr069Vlan?->vlan_id,
            'service_port_id' => $sVlan->service_port_id,
            'tr069_service_port_id' => $sVlan->service_port_id ? $sVlan->service_port_id + 10000 : null,
        ]);

        return response()->json(['data' => [
            'commands' => $result['commands'] ?? [],
            'message' => 'These commands will be sent to the OLT when the activation is created.',
            'supported' => true,
        ]]);
    }

    public function storePreset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'olt_public_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:190'],
            'dba_profile_id' => ['nullable', 'integer'],
            'ont_line_profile_id' => ['nullable', 'integer'],
            'ont_service_profile_id' => ['nullable', 'integer'],
            'ont_wan_profile_id' => ['nullable', 'integer'],
            'ont_tr069_server_profile_id' => ['nullable', 'integer'],
        ]);

        $olt = Olt::query()->where('public_id', $data['olt_public_id'])->firstOrFail();
        unset($data['olt_public_id']);
        foreach ([
            'dba_profile_id' => OltDbaProfile::class,
            'ont_line_profile_id' => OltQinqProvision::class,
            'ont_service_profile_id' => OltOntServiceProfile::class,
            'ont_wan_profile_id' => OltOntWanProfile::class,
            'ont_tr069_server_profile_id' => OltOntTr069ServerProfile::class,
        ] as $field => $model) {
            if (!isset($data[$field])) continue;
            $profile = $model::query()->find($data[$field]);
            abort_if(!$profile || (int) $profile->olt_id !== (int) $olt->id || ($field === 'ont_line_profile_id' && $profile->qinq_type !== 'ont_line_profile'), 422, 'Every selected profile must belong to the selected OLT.');
        }
        foreach ([
            'dba_profile_id' => $olt->dbaProfiles(),
            'ont_line_profile_id' => $olt->qinqProvisions()->where('qinq_type', 'ont_line_profile'),
            'ont_service_profile_id' => $olt->ontServiceProfiles(),
            'ont_wan_profile_id' => $olt->ontWanProfiles(),
            'ont_tr069_server_profile_id' => $olt->ontTr069ServerProfiles(),
        ] as $field => $query) {
            abort_if($query->exists() && empty($data[$field]), 422, 'Select a profile for every profile family configured on the selected OLT.');
        }

        $preset = $this->presetQuery($olt)->create($data + ['olt_id' => $olt->id]);
        return response()->json(['data' => $this->presetPayload($preset->fresh($this->presetRelations()))], 201);
    }

    public function destroyPreset(string $publicId): JsonResponse
    {
        $preset = ActivationPreset::query()->where('public_id', $publicId)->firstOrFail();
        abort_if($preset->activations()->exists(), 422, 'This preset is used by an activation and cannot be deleted.');
        $preset->delete();
        return response()->json(['message' => 'Activation preset deleted.']);
    }

    public function store(Request $request, RadiusSubscriberSyncService $sync, OltProvisioningService $provisioning, AcsServerService $acs): JsonResponse
    {
        $activation = $this->activate($this->validated($request), $provisioning, $acs);
        $sync->syncEnabledServersForCustomer($activation->service->customer);

        return response()->json(['data' => $activation], 201);
    }

    public function bulk(Request $request, RadiusSubscriberSyncService $sync, OltProvisioningService $provisioning, AcsServerService $acs): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.subscriber_id' => ['required', 'string'],
            'items.*.plan_version_id' => ['required', 'integer'],
            'items.*.ont_public_id' => ['required', 'string'],
            'items.*.olt_public_id' => ['required', 'string'],
            'items.*.preset_public_id' => ['nullable', 'string'],
            'items.*.provisioning_type' => ['required', 'in:vlan,qinq'],
            'items.*.vlan_provision_id' => ['nullable', 'integer', 'required_if:items.*.provisioning_type,vlan'],
            'items.*.qinq_provision_id' => ['nullable', 'integer', 'required_if:items.*.provisioning_type,qinq'],
            'items.*.starts_on' => ['required', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $created = [];
        $errors = [];
        foreach ($data['items'] as $index => $item) {
            try {
                $activation = $this->activate($item, $provisioning, $acs);
                $sync->syncEnabledServersForCustomer($activation->service->customer);
                $created[] = $activation;
            } catch (Throwable $exception) {
                $errors[] = ['index' => $index, 'message' => $this->messageFor($exception)];
            }
        }

        return response()->json(['data' => ['created' => $created, 'errors' => $errors, 'created_count' => count($created), 'error_count' => count($errors)]]);
    }

    public function deactivate(string $publicId, RadiusSubscriberSyncService $sync, OltProvisioningService $provisioning, AcsServerService $acs): JsonResponse
    {
        $activation = Activation::query()->with(['service.customer', 'subscription', 'ont.acsServer', 'ont.olt', 'olt', 'preset', 'vlanProvision', 'qinqProvision'])->where('public_id', $publicId)->firstOrFail();
        if ($activation->status !== 'active') {
            return response()->json([
                'data' => $activation->fresh(['service.customer', 'subscription.planVersion.plan', 'ont.olt', 'olt', 'preset', 'vlanProvision', 'qinqProvision']),
                'message' => 'Activation was already inactive.',
            ]);
        }
        $this->removeRemoteProvisioning($activation, $provisioning, $acs);
        $this->deactivateSubscriber($activation);
        $activation->update(['status' => 'inactive']);
        $sync->syncEnabledServersForCustomer($activation->service->customer);
        return response()->json(['data' => $activation->fresh(['service.customer', 'subscription.planVersion.plan', 'ont.olt', 'olt', 'preset', 'vlanProvision', 'qinqProvision'])]);
    }

    public function destroy(string $publicId, RadiusSubscriberSyncService $sync, OltProvisioningService $provisioning, AcsServerService $acs): JsonResponse
    {
        $activation = Activation::query()->with(['service.customer', 'subscription', 'ont.acsServer', 'ont.olt', 'olt', 'qinqProvision'])->where('public_id', $publicId)->firstOrFail();
        if ($activation->status === 'active') {
            $this->removeRemoteProvisioning($activation, $provisioning, $acs);
            $this->deactivateSubscriber($activation);
        }
        $customer = $activation->service->customer;
        $activation->delete();
        $sync->syncEnabledServersForCustomer($customer);
        return response()->json(['message' => 'Activation deleted.']);
    }

    private function removeRemoteProvisioning(Activation $activation, OltProvisioningService $provisioning, AcsServerService $acs): void
    {
        if ($activation->ont?->acsServer) {
            $acs->disablePppoe($activation->ont);
        }

        $ont = $activation->ont;
        if ($activation->provisioning_type !== 'qinq' || ! $activation->olt || $ont?->ont_id === null) {
            return;
        }

        $sVlan = $activation->olt->qinqProvisions()
            ->where('qinq_type', 's_vlan')
            ->where('outer_vlan', $activation->s_vlan)
            ->first();
        $servicePortIds = collect([$sVlan?->service_port_id, $sVlan?->service_port_id ? $sVlan->service_port_id + 10000 : null])
            ->filter(fn ($servicePortId): bool => $servicePortId !== null)
            ->map(fn ($servicePortId): int => (int) $servicePortId)
            ->values()
            ->all();
        $result = $provisioning->deactivateOnt($activation->olt, [
            'frame' => $ont->frame,
            'slot' => $ont->slot,
            'pon_port' => $ont->pon_port,
            'ont_id' => $ont->ont_id,
            'service_port_ids' => $servicePortIds,
        ]);
        abort_if(! ($result['applied'] ?? false), 422, 'The ONT deactivation could not be applied. Connect the OLT session and retry.');
        $ont->update(['ont_id' => null]);
    }

    private function deactivateSubscriber(Activation $activation): void
    {
        $activation->subscription?->update(['status' => 'inactive', 'ends_on' => today()]);
        $service = $activation->service;
        if ($service && ! $service->subscriptions()->where('status', 'active')->exists()) {
            $service->update(['status' => 'inactive', 'suspended_at' => now()]);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subscriber_id' => ['required', 'string'],
            'plan_version_id' => ['required', 'integer'],
            'ont_public_id' => ['required', 'string'],
            'olt_public_id' => ['required', 'string'],
            'acs_server_public_id' => ['nullable', 'string'],
            'ont_id' => ['nullable', 'integer', 'between:0,255'],
            'preset_public_id' => ['nullable', 'string'],
            'provisioning_type' => ['required', 'in:vlan,qinq'],
            'vlan_provision_id' => ['nullable', 'integer', 'required_if:provisioning_type,vlan'],
            'qinq_provision_id' => ['nullable', 'integer', 'required_if:provisioning_type,qinq'],
            'starts_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function activate(array $data, OltProvisioningService $provisioning, AcsServerService $acs): Activation
    {
        return DB::transaction(function () use ($data, $provisioning, $acs): Activation {
            $customer = Customer::query()->where('public_id', $data['subscriber_id'])->firstOrFail();
            $olt = Olt::query()->where('public_id', $data['olt_public_id'])->lockForUpdate()->firstOrFail();
            $ont = Ont::query()->where('public_id', $data['ont_public_id'])->lockForUpdate()->firstOrFail();
            abort_if((int) $ont->olt_id !== (int) $olt->id, 422, 'The selected ONT does not belong to the selected OLT.');
            if (! empty($data['acs_server_public_id'])) {
                $acsServer = AcsServer::query()->where('public_id', $data['acs_server_public_id'])->where('status', 'active')->firstOrFail();
                $ont->update(['acs_server_id' => $acsServer->id]);
                $ont->setRelation('acsServer', $acsServer);
                abort_unless($customer->hasPppCredentials(), 422, 'This subscriber is missing PPP username or password required for ACS provisioning.');
            }
            if ($data['provisioning_type'] === 'qinq') {
                $requestedOntId = array_key_exists('ont_id', $data) && $data['ont_id'] !== null ? (int) $data['ont_id'] : null;
                $ontId = $this->resolveOntId($olt, $ont, $requestedOntId);
            } else {
                $ontId = null;
            }
            $preset = null;
            if (!empty($data['preset_public_id'])) {
                $preset = ActivationPreset::query()->where('public_id', $data['preset_public_id'])->firstOrFail();
                abort_if((int) $preset->olt_id !== (int) $olt->id, 422, 'The selected preset does not belong to the selected OLT.');
            }
            $vlan = null;
            $qinq = null;
            $sVlan = null;
            $tr069Vlan = null;
            $outerVlan = null;
            $internetVlan = null;
            if ($data['provisioning_type'] === 'vlan') {
                $vlan = OltVlanProvision::query()->findOrFail($data['vlan_provision_id']);
                abort_if((int) $vlan->olt_id !== (int) $olt->id, 422, 'The selected VLAN setup does not belong to the selected OLT.');
                $internetVlan = (int) $vlan->vlan_id;
            } else {
                $qinq = OltQinqProvision::query()->findOrFail($data['qinq_provision_id']);
                abort_if((int) $qinq->olt_id !== (int) $olt->id || $qinq->qinq_type === 'ont_line_profile', 422, 'The selected QinQ setup does not belong to the selected OLT.');
                abort_if($qinq->outer_vlan === null && $qinq->inner_vlan === null, 422, 'The selected QinQ setup has no VLAN values.');
                $outerVlan = $this->resolveQinqOuterVlan($olt, $qinq);
                abort_if($outerVlan === null, 422, 'The selected C-VLAN is not linked to an S-VLAN. Edit the C-VLAN provisioning record first.');
                $internetVlan = $qinq->inner_vlan !== null ? (int) $qinq->inner_vlan : null;
                $sVlan = $olt->qinqProvisions()->where('qinq_type', 's_vlan')->where('outer_vlan', $outerVlan)->first();
                abort_if(! $sVlan, 422, 'The selected C-VLAN is not linked to an S-VLAN. Edit the C-VLAN provisioning record first.');
                $tr069Vlan = $olt->vlanProvisions()->where('service_mode', 'tr069')->orderBy('id')->first();
            }
            $version = PlanVersion::query()->with('plan')->findOrFail($data['plan_version_id']);

            abort_if($version->status !== 'active' || $version->plan->status !== 'active', 422, 'Only active plan versions can be activated.');
            abort_if(Activation::query()->where('subscriber_service_id', $customer->subscriberServices()->whereIn('status', ['active', 'pending'])->value('id'))->where('status', 'active')->exists(), 422, 'This subscriber already has an active activation.');
            abort_if(Activation::query()->where('ont_id', $ont->id)->where('status', 'active')->exists(), 422, 'This ONT is already assigned to an active activation.');

            if ($data['provisioning_type'] === 'qinq') {
                $profiles = $this->activationProfiles($olt, $preset);
                $this->ensureActivationProfilesComplete($olt, $preset, $profiles);
                $provisioning->activateOnt($olt, [
                    'frame' => $ont->frame,
                    'slot' => $ont->slot,
                    'pon_port' => $ont->pon_port,
                    'ont_id' => $ontId,
                    'serial_number' => $ont->serial_number,
                    'description' => 'SUB_'.$qinq->inner_vlan.'_'.$outerVlan,
                    'line_profile_id' => $profiles['line_profile_id'],
                    'service_profile_id' => $profiles['service_profile_id'],
                    'tr069_profile_id' => $profiles['tr069_profile_id'],
                    'wan_profile_ids' => $profiles['wan_profile_ids'],
                    'c_vlan' => $qinq->inner_vlan,
                    's_vlan' => $outerVlan,
                    'tr069_vlan' => $tr069Vlan?->vlan_id,
                    'service_port_id' => $sVlan->service_port_id,
                    'tr069_service_port_id' => $sVlan->service_port_id ? $sVlan->service_port_id + 10000 : null,
                ]);
                if ($ont->ont_id === null && $ontId !== null) $ont->update(['ont_id' => $ontId]);
            }

            $account = $customer->billingAccounts()->latest()->first() ?: BillingAccount::create([
                'customer_id' => $customer->id,
                'account_number' => NumberGenerator::next('billing_accounts', 'BA-', 'billing_accounts', 'account_number'),
                'currency' => config('app.currency', 'PHP'),
                'status' => 'active',
            ]);
            $service = $customer->subscriberServices()->whereIn('status', ['active', 'pending'])->latest()->first() ?: SubscriberService::create([
                'customer_id' => $customer->id,
                'billing_account_id' => $account->id,
                'service_number' => NumberGenerator::next('subscriber_services', 'SVC-', 'subscriber_services', 'service_number'),
                'service_type' => 'internet',
                'status' => 'pending',
            ]);
            abort_unless($service->billing_account_id === $account->id, 422, 'Subscriber service does not belong to the selected billing account.');
            $service->update([
                'status' => 'active',
                'activated_at' => $service->activated_at ?: now(),
                'suspended_at' => null,
            ]);

            $settings = OrganizationBillingSetting::firstOrCreate([]);
            $cycleStartDay = (int) ($settings->cycle_start_day ?: 20);
            $subscription = Subscription::create([
                'subscriber_service_id' => $service->id,
                'billing_account_id' => $account->id,
                'plan_version_id' => $version->id,
                'status' => 'active',
                'starts_on' => $data['starts_on'],
                'next_billing_date' => $this->nextBillingDate($data['starts_on'], $cycleStartDay),
                'billing_day' => $cycleStartDay,
                'price_snapshot_minor' => $version->recurring_price_minor,
                'currency_snapshot' => $version->currency,
                'plan_name_snapshot' => $version->plan->name,
            ]);
            $this->createInitialBillingStatement($subscription->fresh('planVersion'), $settings, $data['starts_on']);

            $activation = Activation::create([
                'subscriber_service_id' => $service->id,
                'subscription_id' => $subscription->id,
                'olt_id' => $olt->id,
                'activation_preset_id' => $preset?->id,
                'vlan_provision_id' => $vlan?->id,
                'qinq_provision_id' => $qinq?->id,
                'ont_id' => $ont->id,
                'provisioning_type' => $data['provisioning_type'],
                'c_vlan' => $vlan?->vlan_id ?? $qinq?->inner_vlan,
                's_vlan' => $qinq?->outer_vlan ?? $outerVlan,
                'status' => 'active',
                'activated_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Only ONTs assigned to an ACS are provisioned here. This keeps legacy
            // activations compatible while ensuring managed ONTs receive PPPoE data.
            if ($ont->acsServer) {
                $acs->configurePppoe($ont, (string) $customer->ppp_username, (string) $customer->ppp_password, $internetVlan);
            }

            return $activation->load(['service.customer', 'subscription.planVersion.plan', 'ont.olt', 'olt', 'preset', 'vlanProvision', 'qinqProvision']);
        });
    }

    private function nextBillingDate(string $startsOn, int $cycleStartDay): string
    {
        $starts = Carbon::parse($startsOn)->startOfDay();
        $candidate = $starts->copy()->day(min($cycleStartDay, $starts->daysInMonth));
        if ($candidate->lte($starts)) $candidate = $candidate->addMonth()->day(min($cycleStartDay, $candidate->daysInMonth));
        return $candidate->toDateString();
    }

    private function resolveOntId(Olt $olt, Ont $ont, ?int $requestedOntId = null): int
    {
        abort_if(
            $ont->frame === null || $ont->slot === null || $ont->pon_port === null,
            422,
            'The selected ONT is missing its frame, slot, or PON port. Discover or sync it from the OLT before previewing or activating it.'
        );

        $capacity = max(1, min(256, (int) ($olt->ont_id_capacity_per_port ?? 64)));
        $ontId = $requestedOntId ?? ($ont->ont_id === null ? null : (int) $ont->ont_id);

        if ($ontId === null) {
            $usedIds = $olt->onts()
                ->whereKeyNot($ont->getKey())
                ->where('frame', $ont->frame)
                ->where('slot', $ont->slot)
                ->where('pon_port', $ont->pon_port)
                ->whereNotNull('ont_id')
                ->pluck('ont_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            for ($candidate = 0; $candidate < $capacity; $candidate++) {
                if (! in_array($candidate, $usedIds, true)) {
                    $ontId = $candidate;
                    break;
                }
            }
        }

        abort_if($ontId === null, 422, "No ONT ID is available on PON port {$ont->pon_port}. Increase the ONT capacity setting or release an assigned ONT ID.");
        abort_if($ontId < 0 || $ontId >= $capacity, 422, "ONT ID {$ontId} is outside the configured range 0-".($capacity - 1)." for this PON port.");

        $alreadyUsed = $olt->onts()
            ->whereKeyNot($ont->getKey())
            ->where('frame', $ont->frame)
            ->where('slot', $ont->slot)
            ->where('pon_port', $ont->pon_port)
            ->where('ont_id', $ontId)
            ->exists();
        abort_if($alreadyUsed, 422, "ONT ID {$ontId} is already assigned on PON port {$ont->pon_port}.");

        return $ontId;
    }

    private function resolveQinqOuterVlan(Olt $olt, OltQinqProvision $qinq): ?int
    {
        if ($qinq->outer_vlan !== null) {
            return (int) $qinq->outer_vlan;
        }

        $sVlans = $olt->qinqProvisions()
            ->where('qinq_type', 's_vlan')
            ->whereNotNull('outer_vlan')
            ->where('status', '!=', 'archived')
            ->pluck('outer_vlan')
            ->unique()
            ->values();

        return $sVlans->count() === 1 ? (int) $sVlans->first() : null;
    }

    private function createInitialBillingStatement(Subscription $subscription, OrganizationBillingSetting $settings, string $issueDate): BillingStatement
    {
        $issue = Carbon::parse($issueDate);
        $cycleStart = $issue->copy()->day(min($settings->cycle_start_day, $issue->daysInMonth));
        $periodStart = $issue->lt($cycleStart) ? $cycleStart->copy()->subMonth() : $cycleStart;
        $periodEnd = $periodStart->copy()->addMonth()->subDay();
        $prorated = $subscription->starts_on->gt($periodStart) && $subscription->starts_on->lt($periodEnd)
            ? (int) round($subscription->price_snapshot_minor * $subscription->starts_on->diffInDays($periodEnd->copy()->addDay()) / $periodStart->daysInMonth)
            : 0;
        $amortized = (int) round(($subscription->planVersion?->setup_fee_minor ?? 0) / max(1, $settings->installation_amortization_months));
        $subtotal = $subscription->price_snapshot_minor + $prorated + $amortized;
        $tax = (int) round($subtotal * ($settings->vat_rate / 100));
        $total = $subtotal + $tax;

        return BillingStatement::create([
            'billing_account_id' => $subscription->billing_account_id,
            'subscription_id' => $subscription->id,
            'statement_number' => NumberGenerator::next('billing_statements:'.now()->format('Ym'), 'BS-'.now()->format('Ym').'-', 'billing_statements', 'statement_number'),
            'status' => 'open',
            'issue_date' => $issueDate,
            'due_date' => $periodStart->copy()->addDays(7),
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'currency' => $subscription->currency_snapshot,
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'balance_due_minor' => $total,
        ]);
    }

    private function messageFor(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) return $exception->validator->errors()->all()[0] ?? 'Validation failed.';
        return $exception->getMessage() ?: 'Activation could not be created.';
    }

    private function presetQuery(Olt $olt)
    {
        return $olt->hasMany(ActivationPreset::class)->with($this->presetRelations())->orderBy('name');
    }

    /** @return array{line_profile_id:int|null, service_profile_id:int|null, tr069_profile_id:int|null, wan_profile_ids:list<int>} */
    private function activationProfiles(Olt $olt, ?ActivationPreset $preset): array
    {
        if ($preset) {
            $preset->load(['ontLineProfile', 'ontServiceProfile', 'ontWanProfile', 'ontTr069ServerProfile']);

            return [
                'line_profile_id' => $preset->ontLineProfile?->profile_id,
                'service_profile_id' => $preset->ontServiceProfile?->profile_id,
                'tr069_profile_id' => $preset->ontTr069ServerProfile?->profile_id,
                'wan_profile_ids' => $preset->ontWanProfile?->profile_id === null ? [] : [(int) $preset->ontWanProfile->profile_id],
            ];
        }

        $lineProfile = $olt->qinqProvisions()
            ->where('qinq_type', 'ont_line_profile')
            ->where('status', '!=', 'archived')
            ->whereNotNull('profile_id')
            ->orderBy('profile_id')
            ->first();
        $serviceProfile = $olt->ontServiceProfiles()
            ->where('status', '!=', 'archived')
            ->orderBy('profile_id')
            ->first();
        $tr069Profile = $olt->ontTr069ServerProfiles()
            ->where('status', '!=', 'archived')
            ->orderBy('profile_id')
            ->first();

        return [
            'line_profile_id' => $lineProfile?->profile_id,
            'service_profile_id' => $serviceProfile?->profile_id,
            'tr069_profile_id' => $tr069Profile?->profile_id,
            'wan_profile_ids' => $olt->ontWanProfiles()
                ->where('status', '!=', 'archived')
                ->orderBy('profile_id')
                ->pluck('profile_id')
                ->filter(fn ($profileId): bool => $profileId !== null)
                ->map(fn ($profileId): int => (int) $profileId)
                ->values()
                ->all(),
        ];
    }

    /** @param array{line_profile_id:int|null, service_profile_id:int|null, tr069_profile_id:int|null, wan_profile_ids:list<int>} $profiles */
    private function ensureActivationProfilesComplete(Olt $olt, ?ActivationPreset $preset, array $profiles): void
    {
        $missing = [];

        if ($preset) {
            if (! $preset->dbaProfile?->profile_id) $missing[] = 'DBA profile';
        } else {
            if (! $olt->dbaProfiles()->where('status', '!=', 'archived')->exists()) $missing[] = 'DBA profile';
        }

        if (! $profiles['line_profile_id']) $missing[] = 'ONT line profile';
        if (! $profiles['service_profile_id']) $missing[] = 'ONT service profile';
        if ($profiles['wan_profile_ids'] === []) $missing[] = 'ONT WAN profile';
        if (! $profiles['tr069_profile_id']) $missing[] = 'TR-069 server profile';

        abort_if($missing !== [], 422, 'Activation cannot proceed. Configure the following OLT profile sections first: '.implode(', ', array_unique($missing)).'.');
    }

    private function presetRelations(): array
    {
        return ['dbaProfile', 'ontLineProfile', 'ontServiceProfile', 'ontWanProfile', 'ontTr069ServerProfile'];
    }

    private function presetPayload(ActivationPreset $preset): array
    {
        return [
            'public_id' => $preset->public_id,
            'name' => $preset->name,
            'olt_id' => $preset->olt_id,
            'dba_profile' => $preset->dbaProfile?->only(['id', 'profile_id', 'profile_name']),
            'ont_line_profile' => $preset->ontLineProfile?->only(['id', 'profile_id', 'name', 'ont_line_profile']),
            'ont_service_profile' => $preset->ontServiceProfile?->only(['id', 'profile_id', 'profile_name']),
            'ont_wan_profile' => $preset->ontWanProfile?->only(['id', 'profile_id', 'profile_name']),
            'tr069_profile' => $preset->ontTr069ServerProfile?->only(['id', 'profile_id', 'profile_name']),
        ];
    }
}
