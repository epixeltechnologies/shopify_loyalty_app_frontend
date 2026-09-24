<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed set of staff roles (Owner, Manager, Viewer) and the
 * in-app permission catalog, then maps each role to its permissions.
 * Like PlanSeeder, this is the single place the role/permission matrix
 * is defined — see docs/NEXT_STEPS.md step 9 for where this gets wired
 * into actual policy checks.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['slug' => 'customers.view', 'name' => 'View customers', 'group' => 'customers'],
            ['slug' => 'points.adjust', 'name' => 'Manually adjust points', 'group' => 'points'],
            ['slug' => 'point_rules.manage', 'name' => 'Manage point rules', 'group' => 'points'],
            ['slug' => 'rewards.manage', 'name' => 'Manage rewards', 'group' => 'rewards'],
            ['slug' => 'vip_tiers.manage', 'name' => 'Manage VIP tiers', 'group' => 'vip_tiers'],
            ['slug' => 'analytics.view', 'name' => 'View analytics', 'group' => 'analytics'],
            ['slug' => 'settings.manage', 'name' => 'Manage settings', 'group' => 'settings'],
            ['slug' => 'billing.manage', 'name' => 'Manage billing/subscription', 'group' => 'billing'],
            ['slug' => 'users.manage', 'name' => 'Manage staff users', 'group' => 'users'],
        ])->map(fn (array $p) => Permission::query()->updateOrCreate(['slug' => $p['slug']], $p));

        $permissionsBySlug = $permissions->keyBy('slug');

        $owner = Role::query()->updateOrCreate(
            ['slug' => 'owner'],
            ['name' => 'Owner', 'description' => 'Full access, including billing and staff management.']
        );
        $manager = Role::query()->updateOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager', 'description' => 'Runs day-to-day loyalty operations; no billing or staff management.']
        );
        $viewer = Role::query()->updateOrCreate(
            ['slug' => 'viewer'],
            ['name' => 'Viewer', 'description' => 'Read-only access to customers and analytics.']
        );

        $owner->permissions()->sync($permissionsBySlug->pluck('id'));

        $manager->permissions()->sync($permissionsBySlug->only([
            'customers.view', 'points.adjust', 'point_rules.manage',
            'rewards.manage', 'vip_tiers.manage', 'analytics.view',
        ])->pluck('id'));

        $viewer->permissions()->sync($permissionsBySlug->only([
            'customers.view', 'analytics.view',
        ])->pluck('id'));
    }
}
