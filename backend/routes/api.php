<?php

use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsExportController;
use App\Http\Controllers\Api\V1\Analytics\ReportController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Entitlements\EntitlementController;
use App\Http\Controllers\Api\V1\PointRules\PointRuleController;
use App\Http\Controllers\Api\V1\Points\PointStatisticsController;
use App\Http\Controllers\Api\V1\Points\PointTransactionController;
use App\Http\Controllers\Api\V1\PublicApi\PublicApiController;
use App\Http\Controllers\Api\V1\Referrals\ReferralController;
use App\Http\Controllers\Api\V1\Rewards\CustomerRewardController;
use App\Http\Controllers\Api\V1\Rewards\RewardController;
use App\Http\Controllers\Api\V1\Rewards\RewardRedemptionController;
use App\Http\Controllers\Api\V1\Rewards\RewardStatisticsController;
use App\Http\Controllers\Api\V1\Referrals\CustomerReferralController;
use App\Http\Controllers\Api\V1\Referrals\ReferralAttributionController;
use App\Http\Controllers\Api\V1\Referrals\ReferralSettingsController;
use App\Http\Controllers\Api\V1\Notifications\NotificationSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontPointsController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontProfileController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontReferralController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontRewardController;
use App\Http\Controllers\Api\V1\Storefront\StorefrontVipController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\VipTiers\CustomerVipController;
use App\Http\Controllers\Api\V1\VipTiers\VipStatisticsController;
use App\Http\Controllers\Api\V1\VipTiers\VipSettingsController;
use App\Http\Controllers\Api\V1\VipTiers\VipTierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| Every route in this file is an embedded-app route, authenticated via a
| Shopify App Bridge session token (verified JWT — see
| VerifyShopifySessionToken), tenant-resolved (see
| ResolveShopFromSession), and shop-active-checked (see
| EnsureShopIsActive — rejects a shop that has uninstalled the app).
|
| Routes are grouped by domain to mirror the database schema: Auth,
| Shop, Customers, Points (points + point-rules), Rewards, Referrals,
| VIP Tiers, Billing, Analytics, Settings. `subscription.active` blocks
| every group except Auth/Shop/Billing, since this app has no free plan.
*/
Route::prefix('v1')->middleware(['verify.shopify.session', 'tenant.resolve', 'shop.active'])->group(function () {

    // --- Authentication -----------------------------------------------
    // No merchant login flow exists beyond the session token itself;
    // this simply confirms the resolved session for the frontend's boot
    // sequence (see SubscriptionGate.tsx).
    Route::get('/auth/me', [SessionController::class, 'me']);

    // --- Shop --------------------------------------------------------
    Route::get('/shop', [ShopController::class, 'show']);

    // --- Billing (accessible without an active subscription, by design) --
    Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe'])->middleware('throttle:sensitive-writes');
    Route::get('/billing/status', [BillingController::class, 'status']);
    Route::get('/billing/subscription', [BillingController::class, 'subscription']);
    Route::get('/billing/usage', [BillingController::class, 'usage']);
    Route::get('/billing/downgrade-check', [BillingController::class, 'downgradeCheck']);

    Route::middleware('subscription.active')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/entitlements', [EntitlementController::class, 'index']);

        // --- Customers -----------------------------------------------
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/{customer}', [CustomerController::class, 'show']);

            // --- Points (nested under a customer) ---------------------
            Route::get('/{customer}/points', [PointTransactionController::class, 'index']);
            Route::get('/{customer}/points/balance', [PointTransactionController::class, 'balance']);
            Route::post('/{customer}/points/adjust', [PointTransactionController::class, 'adjust'])
                ->middleware('throttle:sensitive-writes');

            // --- Rewards: redemption (nested under a customer + reward) -
            Route::get('/{customer}/rewards/eligible', [CustomerRewardController::class, 'eligible']);
            Route::get('/{customer}/rewards/redemptions', [CustomerRewardController::class, 'redemptionHistory']);
            Route::post('/{customer}/rewards/{reward}/redeem', [RewardRedemptionController::class, 'store'])
                ->middleware('throttle:sensitive-writes');
        });

        // --- Point statistics: shop-wide, for the admin dashboard -----
        Route::get('/points/statistics', [PointStatisticsController::class, 'index']);

        // --- Point rules: how points are earned -----------------------
        Route::prefix('point-rules')->group(function () {
            Route::get('/', [PointRuleController::class, 'index']);
            Route::post('/', [PointRuleController::class, 'store']);
            Route::get('/{pointRule}', [PointRuleController::class, 'show']);
            Route::patch('/{pointRule}', [PointRuleController::class, 'update']);
        });

        // --- Rewards: catalog — what points can be spent on -----------
        Route::prefix('rewards')->group(function () {
            Route::get('/', [RewardController::class, 'index']);
            Route::post('/', [RewardController::class, 'store']);
            Route::get('/available', [RewardController::class, 'available']);
            Route::get('/{reward}', [RewardController::class, 'show']);
            Route::patch('/{reward}', [RewardController::class, 'update']);
        });

        // --- Reward redemptions: shop-wide history + statistics -------
        Route::get('/reward-redemptions', [RewardRedemptionController::class, 'index']);
        Route::get('/rewards/statistics', [RewardStatisticsController::class, 'index']);

        // --- Referrals ---------------------------------------------------
        Route::prefix('referrals')->group(function () {
            Route::get('/', [ReferralController::class, 'index']);
            Route::get('/statistics', [ReferralController::class, 'statistics']);
            Route::get('/fraud-queue', [ReferralController::class, 'fraudQueue']);
            Route::get('/settings', [ReferralSettingsController::class, 'show']);
            Route::patch('/settings', [ReferralSettingsController::class, 'update']);
            Route::get('/{referral}', [ReferralController::class, 'show']);
            Route::post('/{referral}/clear-fraud', [ReferralController::class, 'clearFraud']);
            Route::post('/{referral}/confirm-fraud', [ReferralController::class, 'confirmFraud']);
            // Prepared for a future storefront widget — see
            // ReferralAttributionController's docblock on why this
            // isn't yet a genuinely public/unauthenticated route.
            Route::post('/attribution/click', [ReferralAttributionController::class, 'click']);
        });

        // --- Customer-facing referral endpoints (Customer Experience Foundation) --
        Route::prefix('customers/{customer}/referral')->group(function () {
            Route::get('/code', [CustomerReferralController::class, 'code']);
            Route::get('/link', [CustomerReferralController::class, 'link']);
            Route::get('/statistics', [CustomerReferralController::class, 'statistics']);
            Route::get('/history', [CustomerReferralController::class, 'history']);
        });

        // --- VIP Tiers ---------------------------------------------------
        // Creating a tier beyond Silver/Gold requires the vip_tier.platinum
        // feature — enforced in VipTierPolicy, not by route middleware,
        // since the gate depends on the *slug in the request body*, not
        // just the route being hit.
        Route::prefix('vip-tiers')->group(function () {
            Route::get('/', [VipTierController::class, 'index']);
            Route::post('/', [VipTierController::class, 'store']);
            Route::post('/reorder', [VipTierController::class, 'reorder']);
            Route::post('/evaluate', [VipTierController::class, 'evaluate']);
            Route::get('/statistics', [VipStatisticsController::class, 'index']);
            Route::get('/downgrade-warnings', [VipTierController::class, 'downgradeWarnings']);
            Route::get('/settings', [VipSettingsController::class, 'show']);
            Route::patch('/settings', [VipSettingsController::class, 'update']);
            Route::get('/{vipTier}', [VipTierController::class, 'show']);
            Route::patch('/{vipTier}', [VipTierController::class, 'update']);
            Route::get('/{vipTier}/customers', [VipTierController::class, 'customers']);
        });

        // --- Customer-facing VIP endpoints (Customer Experience Foundation) --
        Route::prefix('customers/{customer}/vip')->group(function () {
            Route::get('/current', [CustomerVipController::class, 'current']);
            Route::get('/progress', [CustomerVipController::class, 'progress']);
            Route::get('/benefits', [CustomerVipController::class, 'benefits']);
            Route::get('/history', [CustomerVipController::class, 'history']);
        });

        // --- Analytics -----------------------------------------------
        Route::middleware('feature:analytics.basic')->group(function () {
            Route::get('/analytics/series', [AnalyticsController::class, 'series']);

            // --- Reports: Customer Growth, Points, Rewards, Redemption,
            // Referral, VIP, Loyalty Engagement — see ReportController.
            Route::prefix('analytics/reports')->group(function () {
                Route::get('/customer-growth', [ReportController::class, 'customerGrowth']);
                Route::get('/points', [ReportController::class, 'points']);
                Route::get('/rewards', [ReportController::class, 'rewards']);
                Route::get('/redemptions', [ReportController::class, 'redemptions']);
                Route::get('/referrals', [ReportController::class, 'referrals']);
                Route::get('/vip', [ReportController::class, 'vip']);
                Route::get('/engagement', [ReportController::class, 'engagement']);
            });
        });

        // CSV export — Professional only. Uses the same generic
        // `feature:{key}` gate as every other feature check in this app
        // (EnsureFeatureEntitlement middleware) — no separate
        // "CsvExportMiddleware" class exists, since one isn't needed.
        Route::middleware('feature:export.csv')->group(function () {
            Route::get('/analytics/export', [AnalyticsController::class, 'exportCustomersCsv']);

            // The async report-export flow — request/status/download-url
            // sit under the normal merchant-session auth; the actual
            // file stream (`analytics.exports.download`, below) does
            // not, since a signed URL must work standalone. See
            // AnalyticsExportController's docblock.
            Route::post('/analytics/exports', [AnalyticsExportController::class, 'store']);
            Route::get('/analytics/exports/{export}', [AnalyticsExportController::class, 'show']);
            Route::get('/analytics/exports/{export}/download-url', [AnalyticsExportController::class, 'downloadUrl']);
        });

        // Advanced-only analytics endpoints (cohort/retention) land here
        // once implemented — see docs/NEXT_STEPS.md:
        // Route::middleware('feature:analytics.advanced')->get('/analytics/cohorts', ...);

        // --- Settings --------------------------------------------------
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'show']);
            Route::patch('/', [SettingsController::class, 'update']);
        });

        // --- Notification settings & templates --------------------------
        Route::prefix('notification-settings')->group(function () {
            Route::get('/', [NotificationSettingsController::class, 'index']);
            Route::get('/{type}', [NotificationSettingsController::class, 'show']);
            Route::patch('/{type}', [NotificationSettingsController::class, 'update']);
            Route::post('/{type}/preview', [NotificationSettingsController::class, 'preview']);
        });

        // Full public API access — Professional only. Same generic
        // `feature:{key}` gate; see docs/ENTITLEMENTS.md#api-access.
        Route::middleware('feature:api.full_access')->prefix('public-api')->group(function () {
            Route::get('/ping', [PublicApiController::class, 'ping']);
        });
    });
});

// The actual export file stream — deliberately OUTSIDE the merchant-
// session-authenticated group above (`verify.shopify.session`/
// `tenant.resolve`): a signed download link must work as a standalone
// URL (opened in a new tab, no App Bridge session token present).
// Protected entirely by Laravel's `signed` middleware, which validates
// the URL's own cryptographic signature and expiry — see
// AnalyticsExportController's docblock for why this is a secure,
// deliberate design rather than a bypass of tenant isolation.
Route::middleware('signed')
    ->get('/v1/analytics/exports/{export}/download', [AnalyticsExportController::class, 'download'])
    ->name('analytics.exports.download');

/*
|--------------------------------------------------------------------------
| Storefront (customer-facing) API — see docs/STOREFRONT.md
|--------------------------------------------------------------------------
| Reached via a Shopify App Proxy (https://{shop}.myshopify.com/apps/
| {proxy-subpath}/* -> this app), never via App Bridge — a storefront
| visitor has no session token. Auth is entirely the app-proxy request
| signature (verify.shopify.app_proxy) plus, for anything customer-
| specific, Shopify's own signed `logged_in_customer_id`
| (storefront.customer) — see both middleware's docblocks. Every
| endpoint below delegates to the SAME services the merchant-admin
| endpoints already use; nothing here re-implements points/rewards/
| referral/VIP business logic.
|
| `subscription.active` is reused as-is (docs/BILLING.md) — a shop with
| a lapsed subscription gets no storefront loyalty functionality either,
| consistent with this app's "no free plan" rule everywhere else.
*/
Route::prefix('v1/storefront')
    ->middleware(['verify.shopify.app_proxy', 'storefront.customer', 'subscription.active', 'throttle:storefront'])
    ->group(function () {
        Route::get('/profile', [StorefrontProfileController::class, 'show']);

        Route::get('/points/balance', [StorefrontPointsController::class, 'balance']);
        Route::get('/points/history', [StorefrontPointsController::class, 'history']);

        Route::get('/rewards/available', [StorefrontRewardController::class, 'available']);
        Route::get('/rewards/eligible', [StorefrontRewardController::class, 'eligible']);
        Route::post('/rewards/{reward}/redeem', [StorefrontRewardController::class, 'redeem'])
            ->middleware('throttle:sensitive-writes');
        Route::get('/rewards/redemptions', [StorefrontRewardController::class, 'history']);

        Route::get('/referral/code', [StorefrontReferralController::class, 'code']);
        Route::get('/referral/link', [StorefrontReferralController::class, 'link']);
        Route::get('/referral/statistics', [StorefrontReferralController::class, 'statistics']);
        Route::get('/referral/history', [StorefrontReferralController::class, 'history']);

        Route::get('/vip/current', [StorefrontVipController::class, 'current']);
        Route::get('/vip/progress', [StorefrontVipController::class, 'progress']);
        Route::get('/vip/benefits', [StorefrontVipController::class, 'benefits']);
    });
