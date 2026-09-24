<?php

namespace App\Services\Analytics;

use App\Jobs\Analytics\GenerateReportExportJob;
use App\Models\AnalyticsExport;
use App\Models\Shop;

/**
 * ANALYTICS: the request/status half of the async CSV export flow —
 * `GenerateReportExportJob` (queued) does the actual generation. See
 * that job and `docs/ANALYTICS.md` for the full request -> queue ->
 * generate -> store -> signed-URL -> expire flow. Professional-only,
 * enforced at the route level (`feature:export.csv`) — this service
 * itself doesn't check plan features, matching this app's convention
 * that services are plan-agnostic and controllers/middleware are the
 * enforcement point.
 */
class ExportService
{
    private const REPORT_TYPES = ['customer_growth', 'points', 'rewards', 'redemptions', 'referrals', 'vip', 'engagement'];

    public function request(Shop $shop, string $reportType, array $filters): AnalyticsExport
    {
        if (! in_array($reportType, self::REPORT_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown report type [{$reportType}].");
        }

        $export = AnalyticsExport::query()->create([
            'shop_id' => $shop->id,
            'report_type' => $reportType,
            'status' => AnalyticsExport::STATUS_PENDING,
            'filters' => $filters,
        ]);

        GenerateReportExportJob::dispatch($export);

        return $export;
    }
}
