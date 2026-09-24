<?php

namespace Tests\Unit\Services\VipTiers;

use App\Events\VipTiers\VipTierChanged;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\VipTier;
use App\Services\VipTiers\VipTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VipTierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_a_tier_change_updates_the_customer_and_writes_history(): void
    {
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create();

        app(VipTierService::class)->applyTierChange($customer, $tier, 'Test reason');

        $this->assertSame($tier->id, $customer->fresh()->vip_tier_id);
        $this->assertDatabaseHas('customer_vip_history', [
            'customer_id' => $customer->id, 'to_vip_tier_id' => $tier->id, 'direction' => 'initial', 'qualification_reason' => 'Test reason',
        ]);
    }

    public function test_applying_the_same_tier_again_is_a_no_op(): void
    {
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create();
        $service = app(VipTierService::class);

        $service->applyTierChange($customer, $tier, 'First');
        $service->applyTierChange($customer->fresh(), $tier, 'Second attempt');

        $this->assertSame(1, \App\Models\CustomerVipHistory::query()->where('customer_id', $customer->id)->count());
    }

    public function test_applying_a_tier_change_fires_the_vip_tier_changed_event(): void
    {
        Event::fake([VipTierChanged::class]);
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create();
        $customer = Customer::factory()->for($shop)->create();

        app(VipTierService::class)->applyTierChange($customer, $tier);

        Event::assertDispatched(VipTierChanged::class);
    }

    public function test_downgrading_to_no_tier_is_recorded_correctly(): void
    {
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create(['sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $tier->id]);

        app(VipTierService::class)->applyTierChange($customer, null, 'No longer qualifies');

        $this->assertNull($customer->fresh()->vip_tier_id);
        $this->assertDatabaseHas('customer_vip_history', ['customer_id' => $customer->id, 'to_vip_tier_id' => null, 'direction' => 'downgrade']);
    }

    public function test_customers_affected_by_plan_downgrade_finds_customers_on_unavailable_tiers(): void
    {
        $shop = Shop::factory()->create(); // no subscription => no vip_tier.platinum feature
        $platinum = VipTier::factory()->for($shop)->create(['slug' => 'platinum', 'sort_order' => 3]);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $platinum->id]);

        $affected = app(VipTierService::class)->customersAffectedByPlanDowngrade($shop);

        $this->assertTrue($affected->contains('id', $customer->id));
    }
}
