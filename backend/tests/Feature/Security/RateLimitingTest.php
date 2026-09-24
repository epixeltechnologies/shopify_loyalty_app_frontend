<?php

namespace Tests\Feature\Security;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SECURITY AUDIT — regression test for a real gap found during this
 * audit: `RateLimiter::for('api', ...)` was already defined in
 * RouteServiceProvider, but nothing actually applied it to the `api`
 * middleware group (see bootstrap/app.php's fix). Before that fix, this
 * test would fail — every one of 200 rapid requests would succeed with
 * no 429 ever returned, since only routes with an explicit
 * `throttle:sensitive-writes` tag were ever limited.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('api');
    }

    public function test_the_general_api_surface_is_rate_limited(): void
    {
        $shop = Shop::factory()->create();

        $lastResponse = null;
        for ($i = 0; $i < 130; $i++) {
            $lastResponse = $this->actingAsShop($shop)->getJson('/api/v1/settings');
        }

        $lastResponse->assertStatus(429);
    }

    public function test_sensitive_writes_are_limited_more_tightly_than_general_reads(): void
    {
        $shop = Shop::factory()->create();
        $customer = \App\Models\Customer::factory()->for($shop)->create();
        RateLimiter::clear('sensitive-writes');

        $lastResponse = null;
        for ($i = 0; $i < 15; $i++) {
            $lastResponse = $this->actingAsShop($shop)
                ->postJson("/api/v1/customers/{$customer->id}/points/adjust", ['points' => 1, 'note' => "Attempt {$i}"]);
        }

        // 10/minute is the configured sensitive-writes ceiling — well
        // below the general 'api' limiter's 120/minute, confirming the
        // tighter limit is the one actually in effect for a mutating,
        // money-adjacent endpoint under this middleware.
        $this->assertSame(429, $lastResponse->status());
    }
}
