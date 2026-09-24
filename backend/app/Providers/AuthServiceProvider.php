<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\PointRule;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\AnalyticsExport;
use App\Models\User;
use App\Models\VipTier;
use App\Policies\AnalyticsExportPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\PointRulePolicy;
use App\Policies\ReferralPolicy;
use App\Policies\RewardPolicy;
use App\Policies\VipTierPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Customer::class => CustomerPolicy::class,
        VipTier::class => VipTierPolicy::class,
        PointRule::class => PointRulePolicy::class,
        Reward::class => RewardPolicy::class,
        Referral::class => ReferralPolicy::class,
        AnalyticsExport::class => AnalyticsExportPolicy::class,
    ];

    public function boot(): void
    {
        /**
         * Authorization foundation: an 'owner' role always passes every
         * Gate/policy check without each policy needing an explicit
         * "unless owner" branch. Returning `null` (not `false`) falls
         * through to the normal policy/ability check for everyone else —
         * see https://laravel.com/docs/authorization#intercepting-gate-checks.
         *
         * Permission slugs (role_permission table) are checked via
         * User::hasPermission() directly (EnsurePermission middleware,
         * or `$user->hasPermission('points.adjust')` in a policy) rather
         * than being registered as individual Gate::define() calls —
         * the permission catalog is data (see RolePermissionSeeder), so
         * defining a matching Gate for each would just be duplicating
         * that data in code.
         */
        Gate::before(function (?User $user, string $ability) {
            return $user?->role?->slug === 'owner' ? true : null;
        });
    }
}
