<?php

namespace Tests\Unit\Observers;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Support\Cache\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Confirms the gap this observer closes stays closed: a direct edit to
 * `plans`/`features`/`plan_features` must invalidate both the cached
 * plan catalog AND every affected shop's cached entitlements — not just
 * wait out the TTL. See PlanConfigurationObserver's docblock.
 */
class PlanConfigurationObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_plan_clears_the_active_plans_catalog_cache(): void
    {
        $plan = Plan::factory()->starter()->create();
        Cache::put(CacheKeys::activePlans(), 'stale-cached-value', 3600);

        $plan->update(['max_active_customers' => 750]);

        $this->assertNull(Cache::get(CacheKeys::activePlans()));
    }

    public function test_saving_a_plan_clears_cached_entitlements_for_every_shop_subscribed_to_it(): void
    {
        $plan = Plan::factory()->starter()->create();
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        // Warm the cache the way EntitlementService normally would.
        $shop->entitlements()->limit('max_active_customers');
        Cache::tags([CacheKeys::shopTag($shop->id)])->put('warm-check', true, 60);
        $this->assertTrue(Cache::tags([CacheKeys::shopTag($shop->id)])->get('warm-check'));

        $plan->update(['max_active_customers' => 750]);

        $this->assertNull(Cache::tags([CacheKeys::shopTag($shop->id)])->get('warm-check'));
    }

    public function test_saving_a_plan_feature_clears_cache_for_shops_on_that_plan_only(): void
    {
        $planA = Plan::factory()->starter()->create();
        $planB = Plan::factory()->professional()->create();
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        Subscription::factory()->for($shopA)->for($planA)->create(['status' => 'active']);
        Subscription::factory()->for($shopB)->for($planB)->create(['status' => 'active']);

        Cache::tags([CacheKeys::shopTag($shopA->id)])->put('warm', true, 60);
        Cache::tags([CacheKeys::shopTag($shopB->id)])->put('warm', true, 60);

        $feature = Feature::factory()->create();
        $planA->features()->attach($feature->id, ['value' => '1']);

        $this->assertNull(Cache::tags([CacheKeys::shopTag($shopA->id)])->get('warm'));
        $this->assertTrue(Cache::tags([CacheKeys::shopTag($shopB->id)])->get('warm'));
    }
}
