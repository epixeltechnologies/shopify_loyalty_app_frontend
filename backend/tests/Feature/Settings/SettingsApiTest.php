<?php

namespace Tests\Feature\Settings;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_can_update_its_general_settings(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->patchJson('/api/v1/settings', ['program_name' => 'Acme Rewards', 'program_status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.program_name', 'Acme Rewards');
    }

    public function test_invalid_program_status_is_rejected(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->patchJson('/api/v1/settings', ['program_status' => 'not_a_real_status'])
            ->assertStatus(422);
    }

    public function test_invalid_brand_color_is_rejected(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->patchJson('/api/v1/settings', ['brand_secondary_color' => 'not-a-hex-color'])
            ->assertStatus(422);
    }

    public function test_updating_settings_writes_an_audit_log_entry(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)->patchJson('/api/v1/settings', ['program_name' => 'New Name'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_a_shops_settings_are_isolated_from_another_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $this->actingAsShop($shopA)->patchJson('/api/v1/settings', ['program_name' => 'Shop A Program'])->assertOk();
        $response = $this->actingAsShop($shopB)->getJson('/api/v1/settings')->assertOk();

        $this->assertNotSame('Shop A Program', $response->json('data.program_name'));
    }
}
