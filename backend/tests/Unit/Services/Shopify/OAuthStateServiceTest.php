<?php

namespace Tests\Unit\Services\Shopify;

use App\Exceptions\Shopify\OAuthStateException;
use App\Services\Shopify\OAuthStateService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OAuthStateServiceTest extends TestCase
{
    private OAuthStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OAuthStateService;
    }

    public function test_a_freshly_generated_state_validates_successfully(): void
    {
        $state = $this->service->generate('my-store.myshopify.com');

        $this->service->consume('my-store.myshopify.com', $state);

        $this->addToAssertionCount(1); // no exception thrown = pass
    }

    public function test_consuming_with_no_state_stored_throws_expired_or_missing(): void
    {
        $this->expectException(OAuthStateException::class);

        try {
            $this->service->consume('never-generated.myshopify.com', 'some-state');
        } catch (OAuthStateException $e) {
            $this->assertSame('expired_or_missing', $e->reason);

            throw $e;
        }
    }

    public function test_consuming_with_a_mismatched_state_throws_mismatch(): void
    {
        $this->service->generate('my-store.myshopify.com');

        $this->expectException(OAuthStateException::class);

        try {
            $this->service->consume('my-store.myshopify.com', 'wrong-state-value');
        } catch (OAuthStateException $e) {
            $this->assertSame('mismatch', $e->reason);

            throw $e;
        }
    }

    public function test_consuming_with_no_provided_state_throws_mismatch(): void
    {
        $this->service->generate('my-store.myshopify.com');

        $this->expectException(OAuthStateException::class);
        $this->service->consume('my-store.myshopify.com', null);
    }

    public function test_a_state_cannot_be_consumed_twice_replay_protection(): void
    {
        $state = $this->service->generate('my-store.myshopify.com');

        $this->service->consume('my-store.myshopify.com', $state);

        $this->expectException(OAuthStateException::class);
        $this->service->consume('my-store.myshopify.com', $state);
    }

    public function test_state_is_scoped_per_shop(): void
    {
        $state = $this->service->generate('shop-a.myshopify.com');

        $this->expectException(OAuthStateException::class);
        $this->service->consume('shop-b.myshopify.com', $state);
    }

    public function test_an_expired_state_is_rejected(): void
    {
        Cache::put('shopify_oauth_state:my-store.myshopify.com', 'old-state', -1); // already expired

        $this->expectException(OAuthStateException::class);
        $this->service->consume('my-store.myshopify.com', 'old-state');
    }
}
