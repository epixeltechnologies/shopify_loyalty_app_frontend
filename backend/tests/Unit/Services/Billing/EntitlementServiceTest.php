<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_with_no_subscription_has_no_active_subscription(): void
    {
        $shop = Shop::factory()->create();

        $this->assertFalse($shop->entitlements()->hasActiveSubscription());
    }

    public function test_shop_with_active_subscription_reports_correct_limits(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $entitlements = $shop->entitlements();

        $this->assertTrue($entitlements->hasActiveSubscription());
        $this->assertSame(500, $entitlements->limit('max_active_customers'));
        $this->assertTrue($entitlements->canAddCustomer());
    }

    public function test_unlimited_plan_limit_returns_null_and_always_allows(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->create(['max_active_customers' => null]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $entitlements = $shop->entitlements();

        $this->assertNull($entitlements->limit('max_active_customers'));
        $this->assertTrue($entitlements->canAddCustomer());
    }

    public function test_feature_flag_reads_from_plan_features_pivot(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->create(['key' => 'analytics.advanced']);
        $plan->features()->attach($feature->id, ['value' => '1']);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->assertTrue($shop->entitlements()->has('analytics.advanced'));
        $this->assertFalse($shop->entitlements()->has('analytics.nonexistent'));
    }

    public function test_has_feature_and_can_use_feature_are_aliases_of_has(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->create(['key' => 'export.csv']);
        $plan->features()->attach($feature->id, ['value' => '1']);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $entitlements = $shop->entitlements();

        $this->assertTrue($entitlements->hasFeature('export.csv'));
        $this->assertTrue($entitlements->canUseFeature('export.csv'));
        $this->assertFalse($entitlements->hasFeature('api.full_access'));
    }

    public function test_get_limit_is_an_alias_of_limit(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->assertSame(500, $shop->entitlements()->getLimit('max_active_customers'));
    }

    public function test_has_reached_limit_reflects_current_usage(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(); // max_active_point_rules = 5
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->assertFalse($shop->entitlements()->hasReachedLimit('max_active_point_rules', SubscriptionUsage::METRIC_ACTIVE_POINT_RULES));

        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_POINT_RULES, 'value' => 5]);

        $this->assertTrue($shop->entitlements()->hasReachedLimit('max_active_point_rules', SubscriptionUsage::METRIC_ACTIVE_POINT_RULES));
    }

    public function test_has_reached_limit_is_always_false_for_unlimited(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->professional()->create(); // max_active_point_rules = null (unlimited)
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_POINT_RULES, 'value' => 999]);

        $this->assertFalse($shop->entitlements()->hasReachedLimit('max_active_point_rules', SubscriptionUsage::METRIC_ACTIVE_POINT_RULES));
    }

    public function test_can_create_dispatches_to_the_correct_resource_check(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $entitlements = $shop->entitlements();

        $this->assertTrue($entitlements->canCreate('customer'));
        $this->assertTrue($entitlements->canCreate('point_rule'));
        $this->assertTrue($entitlements->canCreate('reward_campaign')); // alias resource name
    }

    public function test_can_create_rejects_an_unknown_resource(): void
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);

        $this->expectException(\InvalidArgumentException::class);
        $shop->entitlements()->canCreate('something_unknown');
    }

    public function test_can_create_reward_campaign_is_an_alias_of_can_add_point_rule(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        SubscriptionUsage::query()->create(['shop_id' => $shop->id, 'metric' => SubscriptionUsage::METRIC_ACTIVE_POINT_RULES, 'value' => 5]);

        $this->assertSame(
            $shop->entitlements()->canAddPointRule(),
            $shop->entitlements()->canCreateRewardCampaign(),
        );
        $this->assertFalse($shop->entitlements()->canCreateRewardCampaign());
    }

    public function test_can_use_vip_tier_reflects_plan_features(): void
    {
        $shop = Shop::factory()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($starter)->create(['status' => 'active']);

        $silver = Feature::factory()->create(['key' => 'vip_tier.silver', 'group' => 'vip_tiers']);
        $starter->features()->attach($silver->id, ['value' => '1']);

        $entitlements = $shop->entitlements();

        $this->assertTrue($entitlements->canUseVipTier('silver'));
        $this->assertFalse($entitlements->canUseVipTier('platinum'));
    }

    public function test_can_export_csv_and_can_use_api_reflect_plan_features(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        $csv = Feature::factory()->create(['key' => 'export.csv']);
        $api = Feature::factory()->create(['key' => 'api.full_access']);
        $professional->features()->attach([$csv->id => ['value' => '1'], $api->id => ['value' => '1']]);

        $entitlements = $shop->entitlements();

        $this->assertTrue($entitlements->canExportCsv());
        $this->assertTrue($entitlements->canUseApi());
    }

    public function test_starter_plan_cannot_export_csv_or_use_api_by_default(): void
    {
        $shop = Shop::factory()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($starter)->create(['status' => 'active']);

        $entitlements = $shop->entitlements();

        $this->assertFalse($entitlements->canExportCsv());
        $this->assertFalse($entitlements->canUseApi());
    }

    public function test_no_subscription_denies_every_feature_and_limit(): void
    {
        $shop = Shop::factory()->create();
        $entitlements = $shop->entitlements();

        $this->assertFalse($entitlements->hasFeature('export.csv'));
        $this->assertFalse($entitlements->canExportCsv());
        $this->assertFalse($entitlements->canUseApi());
        $this->assertFalse($entitlements->canAddCustomer());
        $this->assertSame(0, $entitlements->getLimit('max_active_customers'));
    }

    public function test_an_expired_subscription_is_treated_the_same_as_no_subscription(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'expired']);

        $entitlements = $shop->entitlements();

        $this->assertFalse($entitlements->hasActiveSubscription());
        $this->assertFalse($entitlements->canAddCustomer());
    }
}
