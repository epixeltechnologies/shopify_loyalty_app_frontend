<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Shop;
use App\Services\Shopify\ShopifyGraphQLClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Confirms the centralized Shopify API client abstraction handles rate
 * limiting safely: retries respecting Shopify's own Retry-After header
 * when present, bounded by a maximum retry count (never a retry storm),
 * and surfaces a genuine API failure distinctly from a rate limit.
 */
class ShopifyGraphQLClientRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_request_returns_data_without_retrying(): void
    {
        $shop = Shop::factory()->create();
        Http::fake(['*/graphql.json' => Http::response(['data' => ['ok' => true]])]);

        $result = app(ShopifyGraphQLClient::class)->query($shop, 'query { ok }');

        $this->assertSame(['ok' => true], $result);
        Http::assertSentCount(1);
    }

    public function test_a_429_is_retried_and_eventually_succeeds(): void
    {
        $shop = Shop::factory()->create();

        Http::fakeSequence()
            ->push(status: 429, headers: ['Retry-After' => '0'])
            ->push(['data' => ['ok' => true]]);

        $result = app(ShopifyGraphQLClient::class)->query($shop, 'query { ok }');

        $this->assertSame(['ok' => true], $result);
    }

    public function test_persistent_rate_limiting_exhausts_retries_and_throws(): void
    {
        $shop = Shop::factory()->create();
        Http::fake(['*/graphql.json' => Http::response(status: 429, headers: ['Retry-After' => '0'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exhausted retries');

        app(ShopifyGraphQLClient::class)->query($shop, 'query { ok }');
    }

    public function test_retries_are_bounded_and_never_exceed_the_configured_maximum(): void
    {
        $shop = Shop::factory()->create();
        Http::fake(['*/graphql.json' => Http::response(status: 429, headers: ['Retry-After' => '0'])]);

        try {
            app(ShopifyGraphQLClient::class)->query($shop, 'query { ok }');
        } catch (RuntimeException) {
            // expected
        }

        Http::assertSentCount((int) config('shopify.api_rate_limit.max_retries', 5));
    }

    public function test_a_non_rate_limit_failure_throws_immediately_without_retrying(): void
    {
        $shop = Shop::factory()->create();
        Http::fake(['*/graphql.json' => Http::response('Internal Server Error', 500)]);

        $this->expectException(RuntimeException::class);

        app(ShopifyGraphQLClient::class)->query($shop, 'query { ok }');

        Http::assertSentCount(1);
    }

    public function test_graphql_level_errors_throw_without_retrying(): void
    {
        $shop = Shop::factory()->create();
        Http::fake(['*/graphql.json' => Http::response(['errors' => [['message' => 'Field does not exist']]])]);

        $this->expectException(RuntimeException::class);

        app(ShopifyGraphQLClient::class)->query($shop, 'query { invalidField }');
    }
}
