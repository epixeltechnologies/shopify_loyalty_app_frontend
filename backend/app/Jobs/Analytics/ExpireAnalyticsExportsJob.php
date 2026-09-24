<?php

namespace App\Jobs\Analytics;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\AnalyticsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Dispatched daily (see routes/console.php) — deletes the underlying
 * file for every export whose `expires_at` has passed, and clears
 * `file_path` so a subsequent download attempt 404s cleanly rather than
 * trying to serve a file that's already gone. This is the "expire file
 * after configured period" half of the export security requirement;
 * the signed-route URL's own short expiry (see
 * AnalyticsExportController::download()) is the other half.
 */
class ExpireAnalyticsExportsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        AnalyticsExport::query()
            ->whereNotNull('file_path')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use ($disk) {
                foreach ($exports as $export) {
                    try {
                        $disk->delete($export->file_path);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete expired analytics export file', ['export_id' => $export->id, 'error' => $e->getMessage()]);
                    }

                    $export->update(['file_path' => null]);
                }
            });
    }
}
