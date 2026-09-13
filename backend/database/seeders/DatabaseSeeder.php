<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $user = User::firstOrCreate(['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')], [
            'name' => env('SEED_ADMIN_NAME', 'Billing Administrator'),
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'change-this-password')),
            'status' => 'active',
        ]);
        $administrator = Role::firstOrCreate(['name' => 'Administrator'], ['guard_name' => 'api']);
        $administrator->permissions()->sync(Permission::query()->pluck('id'));
        $administrator->users()->syncWithoutDetaching([$user->id]);

        $cycle = BillingCycle::firstOrCreate(['name' => 'Monthly'], ['interval_unit' => 'month', 'interval_count' => 1, 'billing_day' => 1, 'grace_days' => 7, 'status' => 'active']);
        $plan = Plan::firstOrCreate(['code' => 'HOME-100'], ['billing_cycle_id' => $cycle->id, 'name' => 'Home 100', 'service_type' => 'internet', 'status' => 'active']);
        PlanVersion::firstOrCreate(['plan_id' => $plan->id, 'version' => 1], [
            'recurring_price_minor' => 150000, 'setup_fee_minor' => 0, 'currency' => 'PHP', 'download_kbps' => 100000, 'upload_kbps' => 50000, 'effective_from' => now()->toDateString(), 'status' => 'active',
        ]);
    }
}
