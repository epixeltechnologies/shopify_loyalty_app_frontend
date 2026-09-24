<?php

namespace Tests\Feature\Analytics;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function subscribeTo(Shop $shop, string $planType): void
    {
        $plan = $planType === 'starter' ? Plan::factory()->starter()->create() : Plan::factory()->professional()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);
    }

    public function test_starter_can_access_basic_reports(): void
    {
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, 'starter');

        $this->actingAsShop($shop)
            ->getJson('/api/v1/analytics/reports/points')
            ->assertOk();
    }

    public function test_starter_cannot_request_a_csv_export(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, 'starter');

        $this->actingAsShop($shop)
            ->postJson('/api/v1/analytics/exports', ['report_type' => 'points', 'preset' => 'last_30_days'])
            ->assertStatus(403);
    }

    public function test_professional_can_request_a_csv_export(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $this->subscribeTo($shop, 'professional');

        $this->actingAsShop($shop)
            ->postJson('/api/v1/analytics/exports', ['report_type' => 'points', 'preset' => 'last_30_days'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(\App\Jobs\Analytics\GenerateReportExportJob::class);
    }

    public function test_reports_are_unreachable_without_an_active_subscription(): void
    {
        $shop = Shop::factory()->create(); // no subscription at all

        $this->actingAsShop($shop)
            ->getJson('/api/v1/analytics/reports/points')
            ->assertStatus(402);
    }
}
