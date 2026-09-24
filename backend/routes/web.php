<?php

use App\Http\Controllers\Auth\ShopifyAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| OAuth install/callback and the embedded app shell (serves the built
| React SPA from resources/views/app.blade.php).
*/
Route::get('/auth', [ShopifyAuthController::class, 'redirect'])->name('shopify.auth');
Route::get('/auth/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.auth.callback');

Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|webhooks).*$')
    ->name('app.shell');
