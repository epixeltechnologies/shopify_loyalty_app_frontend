<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
| HMAC-verified by VerifyShopifyWebhook middleware. Never wrapped in the
| 'web' or 'api' groups (no CSRF, no session cookies).
*/
Route::post('/{topic}', [WebhookController::class, 'handle'])
    ->where('topic', '[a-z0-9_\-\/]+')
    ->middleware(['verify.shopify.webhook', 'throttle:webhooks'])
    ->name('webhooks.handle');
