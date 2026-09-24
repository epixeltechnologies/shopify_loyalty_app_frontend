<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\RequestExportRequest;
use App\Models\AnalyticsExport;
use App\Services\Analytics\ExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * ANALYTICS: the async CSV export flow's HTTP surface — Professional-
 * only (`feature:export.csv`, see routes/api.php), which is why this
 * whole controller sits under that middleware, not a per-method check.
 *
 * `download()` is deliberately NOT behind the merchant-session
 * middleware group (`verify.shopify.session`/`tenant.resolve`) — a
 * signed download link needs to work as a plain URL (e.g. opened in a
 * new tab), which won't carry an App Bridge session token. Its security
 * comes entirely from Laravel's `signed` middleware: the URL is only
 * ever produced by `downloadUrl()` below, which itself sits behind the
 * normal authenticated + policy-checked flow — knowledge of a validly-
 * signed URL for a specific export IS the authorization proof for that
 * one file, for the short window the signature is valid. This, plus
 * the underlying export ROW's own longer-lived `expires_at` (checked
 * inside the method, independent of the signature's own expiry), are
 * the two layers behind "temporary signed URLs... file expiration...
 * no public permanent URLs."
 */
class AnalyticsExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    public function store(RequestExportRequest $request): JsonResponse
    {
        $export = $this->exports->request(TenantContext::shop(), $request->validated('report_type'), $request->validated());

        return response()->json(['data' => $this->present($export)])->setStatusCode(202);
    }

    public function show(AnalyticsExport $export): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json(['data' => $this->present($export)]);
    }

    /** Issues a short-lived (15 minute) signed URL to the actual download route — never the file path or storage URL directly. */
    public function downloadUrl(AnalyticsExport $export): JsonResponse
    {
        $this->authorize('view', $export);

        if ($export->status !== AnalyticsExport::STATUS_COMPLETED || $export->isExpired()) {
            return response()->json(['message' => 'This export is not available for download.'], 404);
        }

        $url = URL::temporarySignedRoute('analytics.exports.download', now()->addMinutes(15), ['export' => $export->id]);

        return response()->json(['data' => ['download_url' => $url, 'expires_in_minutes' => 15]]);
    }

    /** The actual file stream — reached only via a validly-signed URL from downloadUrl() above (see routes/api.php for the `signed` middleware). */
    public function download(AnalyticsExport $export)
    {
        if ($export->status !== AnalyticsExport::STATUS_COMPLETED || ! $export->file_path || $export->isExpired()) {
            abort(404, 'This export is no longer available.');
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($export->file_path)) {
            abort(404, 'This export is no longer available.');
        }

        return $disk->download($export->file_path, "{$export->report_type}-export.csv");
    }

    private function present(AnalyticsExport $export): array
    {
        return [
            'id' => $export->id,
            'report_type' => $export->report_type,
            'status' => $export->status,
            'row_count' => $export->row_count,
            'failure_reason' => $export->status === AnalyticsExport::STATUS_FAILED ? $export->failure_reason : null,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }
}
