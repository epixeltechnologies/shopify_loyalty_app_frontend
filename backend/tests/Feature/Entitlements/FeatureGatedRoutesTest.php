<?php

namespace Tests\Feature\Entitlements;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

/**
 * Confirms the two named plan features with dedicated endpoints — CSV
 * export and full API access — are enforced server-side via the
 * existing generic `feature:{key}` middleware (EnsureFeatureEntitlement),
 * for both the Starter-denied and Professional-allowed cases. See
 * docs/ENTITLEMENTS.md#api-access and #csv-export.
 */
class FeatureGatedRoutesTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    private function subscribeTo(Shop $shop, Plan $plan): void
    {
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);
    }

    private function grantFeature(Plan $plan, string $key): void
    {
        $feature = Feature::factory()->create(['key' => $key]);
        $plan->features()->attach($feature->id, ['value' => '1']);
    }

    public function test_starter_cannot_export_csv(): void
    {
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, Plan::factory()->starter()->create());

        $this->actingAsShop($shop)
            ->getJson('/api/v1/analytics/export')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_NOT_AVAILABLE', 'feature' => 'export.csv']);
    }

    public function test_professional_can_export_csv(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $this->grantFeature($professional, 'export.csv');
        $this->subscribeTo($shop, $professional);

        $response = $this->actingAsShop($shop)->get('/api/v1/analytics/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_starter_cannot_access_the_public_api(): void
    {
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, Plan::factory()->starter()->create());

        $this->actingAsShop($shop)
            ->getJson('/api/v1/public-api/ping')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_NOT_AVAILABLE', 'feature' => 'api.full_access']);
    }

    public function test_professional_can_access_the_public_api(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $this->grantFeature($professional, 'api.full_access');
        $this->subscribeTo($shop, $professional);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/public-api/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_entitlements_endpoint_reports_every_known_feature_with_availability(): void
    {
        $shop = Shop::factory()->create();
        $starter = Plan::factory()->starter()->create();
        $this->grantFeature($starter, 'vip_tier.silver');
        $csvFeature = Feature::factory()->create(['key' => 'export.csv']); // exists in catalog but NOT granted to starter
        $this->subscribeTo($shop, $starter);

        $response = $this->actingAsShop($shop)->getJson('/api/v1/entitlements')->assertOk();

        $features = collect($response->json('data.features'));
        $this->assertTrue($features->firstWhere('key', 'vip_tier.silver')['available']);
        $this->assertFalse($features->firstWhere('key', $csvFeature->key)['available']);
    }

    public function test_entitlements_endpoint_reports_usage_against_limits(): void
    {
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, Plan::factory()->starter()->create());

        $response = $this->actingAsShop($shop)->getJson('/api/v1/entitlements')->assertOk();

        $this->assertSame(500, $response->json('data.limits.active_customers.limit'));
        $this->assertSame(0, $response->json('data.limits.active_customers.used'));
        $this->assertSame(500, $response->json('data.limits.active_customers.remaining'));
    }
}
