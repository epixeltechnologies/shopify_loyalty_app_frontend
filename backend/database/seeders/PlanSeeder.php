<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the two launch plans and their entitlements. This is the ONLY
 * place plan limits/features are defined — everything downstream
 * (EntitlementService, middleware, UI) reads from these rows.
 * Adding a plan or changing a limit is a data change, not a deploy.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $features = collect([
            ['key' => 'vip_tier.silver', 'name' => 'Silver VIP Tier', 'group' => 'vip_tiers', 'type' => 'boolean'],
            ['key' => 'vip_tier.gold', 'name' => 'Gold VIP Tier', 'group' => 'vip_tiers', 'type' => 'boolean'],
            ['key' => 'vip_tier.platinum', 'name' => 'Platinum VIP Tier', 'group' => 'vip_tiers', 'type' => 'boolean'],
            ['key' => 'analytics.basic', 'name' => 'Basic Analytics', 'group' => 'analytics', 'type' => 'boolean'],
            ['key' => 'analytics.advanced', 'name' => 'Advanced Analytics', 'group' => 'analytics', 'type' => 'boolean'],
            ['key' => 'reporting.standard', 'name' => 'Standard Reporting', 'group' => 'reporting', 'type' => 'boolean'],
            ['key' => 'reporting.advanced', 'name' => 'Advanced Reporting', 'group' => 'reporting', 'type' => 'boolean'],
            ['key' => 'export.csv', 'name' => 'CSV Export', 'group' => 'reporting', 'type' => 'boolean'],
            ['key' => 'notifications.basic_email', 'name' => 'Basic Email Notifications', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.advanced_email', 'name' => 'Advanced Email Notifications', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'api.full_access', 'name' => 'Full API Access', 'group' => 'api', 'type' => 'boolean'],
        ])->map(fn (array $f) => Feature::query()->updateOrCreate(['key' => $f['key']], $f));

        $featuresByKey = $features->keyBy('key');

        $starter = Plan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter Plan',
                'shopify_plan_handle' => config('shopify.billing.plan_handles.starter'),
                'description' => 'Everything a growing store needs to launch a loyalty program.',
                'price_monthly_cents' => 2900,
                'currency' => 'USD',
                'max_active_customers' => 500,
                'max_active_point_rules' => 5,
                'max_active_rewards' => 5,
                'sort_order' => 1,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $professional = Plan::query()->updateOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional Plan',
                'shopify_plan_handle' => config('shopify.billing.plan_handles.professional'),
                'description' => 'Unlimited point rules, advanced analytics, and full API access.',
                'price_monthly_cents' => 9900,
                'currency' => 'USD',
                'max_active_customers' => 5000,
                'max_active_point_rules' => null, // unlimited
                'max_active_rewards' => null, // unlimited
                'sort_order' => 2,
                'is_active' => true,
                'is_default' => false,
            ]
        );

        $starterFeatureKeys = [
            'vip_tier.silver', 'vip_tier.gold',
            'analytics.basic', 'reporting.standard',
            'notifications.basic_email',
        ];

        $professionalFeatureKeys = [
            'vip_tier.silver', 'vip_tier.gold', 'vip_tier.platinum',
            'analytics.basic', 'analytics.advanced',
            'reporting.standard', 'reporting.advanced', 'export.csv',
            'notifications.basic_email', 'notifications.advanced_email',
            'api.full_access',
        ];

        $starter->features()->sync(
            collect($starterFeatureKeys)->mapWithKeys(fn ($key) => [
                $featuresByKey[$key]->id => ['value' => '1'],
            ])
        );

        $professional->features()->sync(
            collect($professionalFeatureKeys)->mapWithKeys(fn ($key) => [
                $featuresByKey[$key]->id => ['value' => '1'],
            ])
        );
    }
}
