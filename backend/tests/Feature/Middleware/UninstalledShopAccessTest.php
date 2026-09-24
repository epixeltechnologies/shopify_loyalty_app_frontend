<?php

namespace Tests\Feature\Middleware;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

/**
 * Confirms App\Http\Middleware\EnsureShopIsActive actually blocks a shop
 * that has uninstalled the app, and that activity tracking behaves.
 */
class UninstalledShopAccessTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    public function test_an_uninstalled_shop_is_rejected_with_410(): void
    {
        $shop = Shop::factory()->uninstalled()->create();

        $response = $this->actingAsShop($shop)->getJson('/api/v1/shop');

        $response->assertStatus(410)->assertJson(['error_code' => 'SHOP_UNINSTALLED']);
    }

    public function test_an_installed_shop_is_not_affected(): void
    {
        $shop = Shop::factory()->create(); // is_installed => true by default

        $this->actingAsShop($shop)->getJson('/api/v1/shop')->assertOk();
    }

    public function test_a_request_from_an_active_shop_updates_last_activity(): void
    {
        $shop = Shop::factory()->create(['last_activity_at' => null]);

        $this->actingAsShop($shop)->getJson('/api/v1/shop')->assertOk();

        $this->assertNotNull(DB::table('shops')->where('id', $shop->id)->value('last_activity_at'));
    }

    public function test_activity_updates_are_throttled_and_do_not_touch_updated_at(): void
    {
        $shop = Shop::factory()->create(['last_activity_at' => now()]);
        $originalUpdatedAt = DB::table('shops')->where('id', $shop->id)->value('updated_at');

        $this->actingAsShop($shop)->getJson('/api/v1/shop')->assertOk();

        // last_activity_at was set moments ago (within the 5-minute
        // throttle window), so this request must not re-write it or
        // bump `updated_at`.
        $this->assertSame($originalUpdatedAt, DB::table('shops')->where('id', $shop->id)->value('updated_at'));
    }
}
