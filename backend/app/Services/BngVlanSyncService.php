<?php

namespace App\Services;

use App\Models\BngVlanInterface;
use App\Models\BngVlanSync;
use App\Models\Olt;
use Illuminate\Support\Collection;
use Throwable;

class BngVlanSyncService
{
    public function __construct(private BngSessionService $sessions) {}

    public function syncNormalVlan(Olt $olt, array $vlan): array
    {
        if (blank($vlan['vlan_id'] ?? null)) return ['warnings' => []];
        $descriptor = ['vlan_id' => (int) $vlan['vlan_id'], 'outer_vlan' => (int) $vlan['vlan_id'], 'inner_vlan' => null, 'vlan_mode' => 'normal'];
        if (($vlan['service_mode'] ?? 'internet') === 'tr069') return $this->syncTr069Vlan($olt, $descriptor);
        return $this->applyToRules($olt, 'normal', [$descriptor]);
    }

    public function syncTr069Vlan(Olt $olt, array $vlan): array
    {
        $descriptor = ['vlan_id' => (int) ($vlan['vlan_id'] ?? 0), 'outer_vlan' => (int) ($vlan['outer_vlan'] ?? $vlan['vlan_id'] ?? 0), 'inner_vlan' => null, 'vlan_mode' => 'tr069'];
        if ($descriptor['vlan_id'] < 1 || $descriptor['outer_vlan'] < 1) return ['warnings' => []];
        return $this->mergeResults($this->applyToRules($olt, 'normal', [$descriptor]), $this->applyToRules($olt, 'qinq', [$descriptor]));
    }

    public function syncQinq(Olt $olt, array $qinq): array
    {
        $type = (string) ($qinq['qinq_type'] ?? 's_vlan');
        if (! in_array($type, ['s_vlan', 'c_vlan'], true)) return ['warnings' => []];
        $outer = $this->resolveOuterVlan($olt, $qinq);
        if ($outer === null) return ['warnings' => ['C-VLAN '.((int) ($qinq['inner_vlan'] ?? 0)).' has no resolvable S-VLAN on OLT '.$olt->name.'.']];
        $descriptors = [['vlan_id' => $outer, 'outer_vlan' => $outer, 'inner_vlan' => null, 'vlan_mode' => 'qinq']];
        if ($type === 'c_vlan' && (int) ($qinq['inner_vlan'] ?? 0) > 0) {
            $inner = (int) $qinq['inner_vlan'];
            $descriptors[] = ['vlan_id' => $inner, 'outer_vlan' => $outer, 'inner_vlan' => $inner, 'vlan_mode' => 'qinq'];
        }

        return $this->applyToRules($olt, 'qinq', $descriptors);
    }

    public function removeNormalVlan(Olt $olt, array $vlan): array
    {
        $vlanId = (int) ($vlan['vlan_id'] ?? 0);
        if ($vlanId < 1) return ['warnings' => []];
        if (($vlan['service_mode'] ?? 'internet') === 'tr069') return $this->removeTr069Vlan($olt, $vlanId);
        return $this->removeFromRules($olt, 'normal', [$vlanId]);
    }

    public function removeTr069Vlan(Olt $olt, int $vlanId): array
    {
        return $this->mergeResults($this->removeFromRules($olt, 'normal', [$vlanId]), $this->removeFromRules($olt, 'qinq', [$vlanId]));
    }

    public function removeQinq(Olt $olt, array $qinq): array
    {
        $type = (string) ($qinq['qinq_type'] ?? 's_vlan');
        if (! in_array($type, ['s_vlan', 'c_vlan'], true)) return ['warnings' => []];
        $outer = $this->resolveOuterVlan($olt, $qinq);
        if ($outer === null) return ['warnings' => []];
        $inner = (int) ($qinq['inner_vlan'] ?? 0);
        $result = ['warnings' => []];
        if ($type === 'c_vlan' && $inner > 0) $result = $this->mergeResults($result, $this->removeFromRules($olt, 'qinq', [], $outer, $inner));
        if ($this->outerIsUnused($olt, $outer, $qinq['id'] ?? null)) $result = $this->mergeResults($result, $this->removeFromRules($olt, 'qinq', [], $outer, null));
        return $result;
    }

    public function removeRule(BngVlanSync $rule): array
    {
        $interfaces = BngVlanInterface::query()->where('bng_id', $rule->bng_id)->where('olt_id', $rule->olt_id)->get();
        $warnings = [];
        foreach ($interfaces->sortByDesc(fn (BngVlanInterface $row) => substr_count($row->interface_name, '.')) as $interface) {
            if (BngVlanInterface::query()->where('bng_id', $interface->bng_id)->where('interface_name', $interface->interface_name)->count() > 1) continue;
            $warnings = [...$warnings, ...$this->removeInterface($rule->bng, $interface)];
        }
        return ['warnings' => $warnings];
    }

    public function reconcileRule(BngVlanSync $rule): array
    {
        if (! $rule->enabled) return ['warnings' => []];

        $rule->loadMissing('olt');
        $result = ['warnings' => []];
        if ($rule->vlan_mode === 'normal') {
            foreach ($rule->olt->vlanProvisions()->get() as $vlan) {
                $result = $this->mergeResults($result, $this->syncNormalVlan($rule->olt, $vlan->toArray()));
            }
        } else {
            foreach ($rule->olt->qinqProvisions()->whereIn('qinq_type', ['s_vlan', 'c_vlan'])->get() as $qinq) {
                $result = $this->mergeResults($result, $this->syncQinq($rule->olt, $qinq->toArray()));
            }
            foreach ($rule->olt->vlanProvisions()->where('service_mode', 'tr069')->get() as $vlan) {
                $result = $this->mergeResults($result, $this->syncTr069Vlan($rule->olt, $vlan->toArray()));
            }
        }

        return $result;
    }

    private function applyToRules(Olt $olt, string $mode, array $descriptors): array
    {
        $warnings = [];
        foreach (BngVlanSync::query()->with('bng')->where('olt_id', $olt->id)->where('vlan_mode', $mode)->where('enabled', true)->get() as $rule) {
            if (blank($rule->bng->parent_interface)) {
                $warnings[] = "BNG {$rule->bng->name} has no parent interface configured.";
                continue;
            }
            $descriptors = array_map(fn (array $descriptor): array => $this->forBng($descriptor, (string) $rule->bng->parent_interface), $descriptors);
            foreach ($descriptors as $descriptor) {
                BngVlanInterface::query()->updateOrCreate(
                    ['bng_id' => $rule->bng_id, 'olt_id' => $olt->id, 'interface_name' => $descriptor['name']],
                    ['vlan_mode' => $mode, 'outer_vlan' => $descriptor['outer_vlan'], 'inner_vlan' => $descriptor['inner_vlan'], 'status' => 'pending', 'last_error' => null],
                );
            }
            try {
                $this->sessions->ensureVlanInterfaces($rule->bng, array_map(fn (array $descriptor): array => ['name' => $descriptor['name'], 'parent' => $descriptor['parent'], 'vlan_id' => $descriptor['vlan_id']], $descriptors));
                BngVlanInterface::query()->where('bng_id', $rule->bng_id)->where('olt_id', $olt->id)->whereIn('interface_name', array_column($descriptors, 'name'))->update(['status' => 'applied', 'last_error' => null]);
            } catch (Throwable $exception) {
                BngVlanInterface::query()->where('bng_id', $rule->bng_id)->where('olt_id', $olt->id)->whereIn('interface_name', array_column($descriptors, 'name'))->update(['status' => 'error', 'last_error' => $exception->getMessage()]);
                $warnings[] = "BNG {$rule->bng->name}: {$exception->getMessage()}";
            }
        }
        return ['warnings' => $warnings];
    }

    private function removeFromRules(Olt $olt, string $mode, array $vlanIds, ?int $outer = null, ?int $inner = null): array
    {
        $warnings = [];
        foreach (BngVlanSync::query()->with('bng')->where('olt_id', $olt->id)->where('vlan_mode', $mode)->get() as $rule) {
            $interfaces = BngVlanInterface::query()->where('bng_id', $rule->bng_id)->where('olt_id', $olt->id)->get()->filter(function (BngVlanInterface $interface) use ($vlanIds, $outer, $inner): bool {
                if ($outer !== null && $inner !== null) return $interface->outer_vlan === $outer && $interface->inner_vlan === $inner;
                if ($outer !== null) return $interface->outer_vlan === $outer && $interface->inner_vlan === null;
                return in_array((int) ($interface->outer_vlan ?? 0), $vlanIds, true) && $interface->inner_vlan === null;
            });
            foreach ($interfaces->sortByDesc(fn (BngVlanInterface $row) => substr_count($row->interface_name, '.')) as $interface) {
                if (BngVlanInterface::query()->where('bng_id', $interface->bng_id)->where('interface_name', $interface->interface_name)->count() > 1) continue;
                $warnings = [...$warnings, ...$this->removeInterface($rule->bng, $interface)];
            }
        }
        return ['warnings' => $warnings];
    }

    private function removeInterface($bng, BngVlanInterface $interface): array
    {
        try {
            $this->sessions->removeVlanInterfaces($bng, [['name' => $interface->interface_name, 'vlan_id' => $interface->inner_vlan ?: $interface->outer_vlan, 'parent' => $this->interfaceParent($interface->interface_name)]]);
            $interface->delete();
            return [];
        } catch (Throwable $exception) {
            $interface->update(['status' => 'error', 'last_error' => $exception->getMessage()]);
            return ["BNG {$bng->name}: {$exception->getMessage()}"];
        }
    }

    private function mergeResults(array $left, array $right): array
    {
        return ['warnings' => [...($left['warnings'] ?? []), ...($right['warnings'] ?? [])]];
    }

    private function outerIsUnused(Olt $olt, int $outer, mixed $ignoreId): bool
    {
        return ! $olt->qinqProvisions()->whereIn('qinq_type', ['s_vlan', 'c_vlan'])->where('outer_vlan', $outer)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
    }

    private function interfaceName(string $parent, int $vlan): string
    {
        return $parent.'.'.$vlan;
    }

    private function forBng(array $descriptor, string $parent): array
    {
        $outerName = $this->interfaceName($parent, (int) $descriptor['outer_vlan']);
        $name = $descriptor['inner_vlan'] === null ? $outerName : $this->interfaceName($outerName, (int) $descriptor['inner_vlan']);
        return [...$descriptor, 'name' => $name, 'parent' => $descriptor['inner_vlan'] === null ? $parent : $outerName];
    }

    private function interfaceParent(string $interface): string
    {
        return str_contains($interface, '.') ? substr($interface, 0, strrpos($interface, '.')) : $interface;
    }

    private function resolveOuterVlan(Olt $olt, array $qinq): ?int
    {
        $outer = (int) ($qinq['outer_vlan'] ?? 0);
        if ($outer > 0) return $outer;
        if (($qinq['qinq_type'] ?? 's_vlan') !== 'c_vlan') return null;

        $candidates = $olt->qinqProvisions()
            ->where('qinq_type', 's_vlan')
            ->whereNotNull('outer_vlan')
            ->pluck('outer_vlan')
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
