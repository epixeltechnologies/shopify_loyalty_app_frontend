<?php

use App\Jobs\Analytics\BuildDailyAnalyticsSnapshotsJob;
use App\Jobs\Analytics\ExpireAnalyticsExportsJob;
use App\Jobs\Points\AwardBirthdayPointsJob;
use App\Jobs\Points\ExpirePointsJob;
use App\Jobs\Referrals\ExpireStaleReferralsJob;
use App\Jobs\Referrals\ProcessReferralRewardsJob;
use App\Jobs\VipTiers\EvaluateShopVipTiersJob;
use App\Models\Shop;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled jobs
|--------------------------------------------------------------------------
| Fan out per-shop so a slow/failing shop never blocks the others —
| each iteration only dispatches a queued job, it does no work inline.
*/
Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => BuildDailyAnalyticsSnapshotsJob::dispatch($shop, now()->subDay()->toDateString())
    );
})->dailyAt('01:00')->name('analytics:build-daily-snapshots');

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => ExpirePointsJob::dispatch($shop)
    );
})->dailyAt('02:00')->name('loyalty:expire-points');

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => AwardBirthdayPointsJob::dispatch($shop)
    );
})->dailyAt('08:00')->name('loyalty:award-birthday-points'); // morning, not midnight — a birthday reward arriving at 2am is a worse experience than one arriving with the day

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => ProcessReferralRewardsJob::dispatch($shop)
    );
})->hourly()->name('referrals:process-rewards'); // hourly, not daily — a configurable reward delay (e.g. "3 days") shouldn't round up to a near-full extra day of waiting

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => ExpireStaleReferralsJob::dispatch($shop)
    );
})->dailyAt('03:00')->name('referrals:expire-stale');

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => EvaluateShopVipTiersJob::dispatch($shop)
    );
})->dailyAt('04:00')->name('vip:evaluate-tiers'); // after points-expiry (02:00) and referral-expiry (03:00), so a shop's VIP evaluation sees the day's fully-settled point/referral state

Schedule::call(function () {
    Shop::query()->where('is_installed', true)->each(
        fn (Shop $shop) => BuildDailyAnalyticsSnapshotsJob::dispatch($shop, now()->subDay()->toDateString())
    );
})->dailyAt('05:00')->name('analytics:build-daily-snapshots'); // last of the daily jobs — rolls up yesterday's fully-settled activity across every domain above

Schedule::call(fn () => ExpireAnalyticsExportsJob::dispatch())
    ->daily()
    ->name('analytics:expire-exports');

// OPS: scheduled here as the intended cadence (see
// docs/BACKUP_AND_RECOVERY.md's recommended schedule) — but this
// registration alone does NOT make backups "working." It still
// requires: (1) BACKUP_AWS_* credentials/bucket actually provisioned,
// (2) the scheduler actually running in production (`schedule:run` via
// cron or the `scheduler` container — see docker-compose.yml), and (3)
// an actual restore test performed and documented before this can be
// relied on. See that doc for what "configured" vs "verified" means
// here specifically.
Schedule::command('db:backup --encrypt')
    ->dailyAt('01:00')
    ->name('database:backup')
    ->onOneServer();
// Failure alerting for this job is NOT wired here — see
// docs/MONITORING.md's alerting section for how to monitor scheduled-
// job failures (e.g. via `schedule:list`/failed-job inspection, or a
// real APM once ErrorTrackingServiceProvider has a provider configured)
// rather than a speculative, unverified email call referencing a mail
// address that isn't actually configured anywhere in this app yet.
