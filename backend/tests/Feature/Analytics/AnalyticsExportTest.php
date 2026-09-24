<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsExport;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    private function professionalShop(): Shop
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->professional()->create())->create(['status' => 'active']);

        return $shop;
    }

    public function test_a_shop_cannot_view_another_shops_export(): void
    {
        $shopA = $this->professionalShop();
        $shopB = $this->professionalShop();
        $otherExport = AnalyticsExport::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)
            ->getJson("/api/v1/analytics/exports/{$otherExport->id}")
            ->assertStatus(404);
    }

    public function test_a_download_url_can_only_be_issued_for_a_completed_export(): void
    {
        $shop = $this->professionalShop();
        $export = AnalyticsExport::factory()->for($shop)->create(['status' => AnalyticsExport::STATUS_PENDING]);

        $this->actingAsShop($shop)
            ->getJson("/api/v1/analytics/exports/{$export->id}/download-url")
            ->assertStatus(404);
    }

    public function test_a_completed_exports_download_url_is_a_valid_signed_route(): void
    {
        $shop = $this->professionalShop();
        $export = AnalyticsExport::factory()->for($shop)->create([
            'status' => AnalyticsExport::STATUS_COMPLETED, 'file_path' => 'analytics-exports/x.csv', 'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAsShop($shop)->getJson("/api/v1/analytics/exports/{$export->id}/download-url")->assertOk();

        $url = $response->json('data.download_url');
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_downloading_via_the_signed_url_streams_the_file(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $shop = $this->professionalShop();
        Storage::disk('local')->put('analytics-exports/test.csv', "Metric,Value\npoints_issued,100\n");
        $export = AnalyticsExport::factory()->for($shop)->create([
            'status' => AnalyticsExport::STATUS_COMPLETED, 'file_path' => 'analytics-exports/test.csv', 'expires_at' => now()->addDay(),
        ]);

        $url = URL::temporarySignedRoute('analytics.exports.download', now()->addMinutes(15), ['export' => $export->id]);

        $this->get($url)->assertOk();
    }

    public function test_an_unsigned_download_request_is_rejected(): void
    {
        $shop = $this->professionalShop();
        $export = AnalyticsExport::factory()->for($shop)->create(['status' => AnalyticsExport::STATUS_COMPLETED, 'file_path' => 'x.csv', 'expires_at' => now()->addDay()]);

        $this->get("/api/v1/analytics/exports/{$export->id}/download")->assertStatus(403);
    }

    public function test_an_expired_export_cannot_be_downloaded_even_with_a_validly_signed_url(): void
    {
        $shop = $this->professionalShop();
        $export = AnalyticsExport::factory()->for($shop)->create([
            'status' => AnalyticsExport::STATUS_COMPLETED, 'file_path' => 'analytics-exports/gone.csv', 'expires_at' => now()->subHour(),
        ]);

        $url = URL::temporarySignedRoute('analytics.exports.download', now()->addMinutes(15), ['export' => $export->id]);

        $this->get($url)->assertStatus(404);
    }

    public function test_a_failed_export_cannot_be_downloaded(): void
    {
        $shop = $this->professionalShop();
        $export = AnalyticsExport::factory()->for($shop)->create(['status' => AnalyticsExport::STATUS_FAILED, 'file_path' => null]);

        $url = URL::temporarySignedRoute('analytics.exports.download', now()->addMinutes(15), ['export' => $export->id]);

        $this->get($url)->assertStatus(404);
    }
}
