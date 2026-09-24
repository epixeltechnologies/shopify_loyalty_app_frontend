<?php

namespace App\Jobs\Analytics;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\AnalyticsExport;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Services\Analytics\DateRangeResolver;
use App\Services\Analytics\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ANALYTICS: does the actual CSV generation — see ExportService for the
 * request half of the flow. Never runs inside an HTTP request; the
 * controller only creates the `pending` AnalyticsExport row and
 * dispatches this. Writes to a PRIVATE disk (`local`/`s3`, never
 * `public`) under a per-shop, per-export path — the file is only ever
 * reachable through a short-lived signed route
 * (`AnalyticsExportController::download()`), never a direct storage URL.
 *
 * Reuses `ReportService` — the exact same methods the JSON report
 * endpoints call — so an export can never disagree with what the
 * dashboard shows for the same report/date-range.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    private const FILE_LIFETIME_HOURS = 48;

    public function __construct(public readonly AnalyticsExport $export) {}

    public function handle(ReportService $reports, DateRangeResolver $dates): void
    {
        $this->export->update(['status' => AnalyticsExport::STATUS_PROCESSING]);

        try {
            $shop = $this->export->shop;
            $filters = $this->export->filters ?? [];
            [$start, $end] = $dates->resolve($shop, $filters['preset'] ?? 'last_30_days', $filters['from'] ?? null, $filters['to'] ?? null);

            $relativePath = "analytics-exports/{$shop->id}/".$this->export->id.'-'.now()->timestamp.'.csv';
            $rowCount = $this->writeCsv($relativePath, $this->export->report_type, $reports, $shop, $start, $end);

            $this->export->update([
                'status' => AnalyticsExport::STATUS_COMPLETED,
                'file_path' => $relativePath,
                'row_count' => $rowCount,
                'expires_at' => now()->addHours(self::FILE_LIFETIME_HOURS),
            ]);
        } catch (\Throwable $e) {
            Log::error('Analytics export failed', ['export_id' => $this->export->id, 'error' => $e->getMessage()]);
            $this->export->update(['status' => AnalyticsExport::STATUS_FAILED, 'failure_reason' => $e->getMessage()]);
        }
    }

    private function writeCsv(string $relativePath, string $reportType, ReportService $reports, Shop $shop, Carbon $start, Carbon $end): int
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));
        $tmpPath = tempnam(sys_get_temp_dir(), 'export');
        $handle = fopen($tmpPath, 'w');
        $rowCount = 0;

        if ($reportType === 'redemptions') {
            $rowCount = $this->writeRedemptionsCsv($handle, $shop, $start, $end);
        } else {
            $rowCount = $this->writeSummaryReportCsv($handle, $reportType, $reports, $shop, $start, $end);
        }

        fclose($handle);
        $disk->put($relativePath, file_get_contents($tmpPath));
        unlink($tmpPath);

        return $rowCount;
    }

    /** Streamed via chunked queries (never loading the full result set into memory) — the same reasoning `AnalyticsController::exportCustomersCsv()` and `PurgeShopDataJob` are built on. */
    private function writeRedemptionsCsv($handle, Shop $shop, Carbon $start, Carbon $end): int
    {
        fputcsv($handle, ['Customer Email', 'Reward', 'Points Spent', 'Status', 'Date']);
        $count = 0;

        RewardRedemption::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$start, $end])
            ->with(['customer', 'reward'])
            ->chunkById(500, function ($redemptions) use ($handle, &$count) {
                foreach ($redemptions as $redemption) {
                    fputcsv($handle, [
                        $redemption->customer?->email ?? '',
                        $redemption->reward?->name ?? '',
                        $redemption->points_spent,
                        $redemption->status,
                        $redemption->created_at?->toDateString(),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /** Summary-shaped reports (customer growth, points, rewards, referrals, vip, engagement) — a key/value summary section followed by each trend metric's daily series. */
    private function writeSummaryReportCsv($handle, string $reportType, ReportService $reports, Shop $shop, Carbon $start, Carbon $end): int
    {
        $data = match ($reportType) {
            'customer_growth' => $reports->customerGrowth($shop, $start, $end),
            'points' => $reports->points($shop, $start, $end),
            'rewards' => $reports->rewards($shop, $start, $end),
            'referrals' => $reports->referrals($shop, $start, $end),
            'vip' => $reports->vip($shop, $start, $end),
            'engagement' => $reports->loyaltyEngagement($shop, $start, $end),
            default => throw new \InvalidArgumentException("Unknown report type [{$reportType}]."),
        };

        $rowCount = 0;

        fputcsv($handle, ['Metric', 'Value']);
        foreach ($data['summary'] ?? [] as $key => $value) {
            fputcsv($handle, [$key, is_array($value) ? json_encode($value) : $value]);
            $rowCount++;
        }

        foreach (($data['trend'] ?? []) as $metricName => $series) {
            fputcsv($handle, []);
            fputcsv($handle, ["Trend: {$metricName}"]);
            fputcsv($handle, ['Date', 'Value']);
            foreach ($series as $point) {
                fputcsv($handle, [$point->date, $point->value]);
                $rowCount++;
            }
        }

        return $rowCount;
    }
}
