<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\MetricSeriesRequest;
use App\Models\Customer;
use App\Services\Analytics\AnalyticsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $service) {}

    public function series(MetricSeriesRequest $request): JsonResponse
    {
        $series = $this->service->series(
            TenantContext::shop(),
            $request->validated('metric'),
            $request->date('from'),
            $request->date('to'),
        );

        return response()->json(['data' => $series]);
    }

    /**
     * CSV export — Professional-only (`feature:export.csv`, see
     * routes/api.php). Deliberately the plainest possible implementation
     * of the named plan feature: streams the shop's customer list.
     * Streamed (not built up in memory) so this scales to a shop's full
     * customer count without a memory spike — the same reasoning
     * `chunkById` usage elsewhere in this app (e.g.
     * App\Jobs\Shopify\PurgeShopDataJob) is built on.
     */
    public function exportCustomersCsv(): StreamedResponse
    {
        $shop = TenantContext::shop();

        return response()->streamDownload(function () use ($shop) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'First Name', 'Last Name', 'Points Balance', 'Status', 'Enrolled At']);

            Customer::query()->where('shop_id', $shop->id)->chunk(500, function ($customers) use ($handle) {
                foreach ($customers as $customer) {
                    fputcsv($handle, [
                        $customer->email,
                        $customer->first_name,
                        $customer->last_name,
                        $customer->points_balance,
                        $customer->status,
                        $customer->enrolled_at?->toDateString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'customers.csv', ['Content-Type' => 'text/csv']);
    }
}
