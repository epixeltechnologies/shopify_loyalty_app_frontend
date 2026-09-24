<?php

namespace Tests\Unit\Services\Shopify;

use App\Services\Shopify\ShopifyAppProxyVerifier;
use Tests\TestCase;

class ShopifyAppProxyVerifierTest extends TestCase
{
    private function verifier(string $secret = 'test-secret'): ShopifyAppProxyVerifier
    {
        return new ShopifyAppProxyVerifier($secret);
    }

    public function test_a_correctly_signed_request_is_valid(): void
    {
        $params = ['shop' => 'test-shop.myshopify.com', 'path_prefix' => '/apps/loyalty', 'timestamp' => '1700000000'];
        $canonical = 'path_prefix=/apps/loyaltyshop=test-shop.myshopify.comtimestamp=1700000000';
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        $this->assertTrue($this->verifier()->isValid([...$params, 'signature' => $signature]));
    }

    public function test_a_tampered_parameter_invalidates_the_signature(): void
    {
        $params = ['shop' => 'test-shop.myshopify.com', 'timestamp' => '1700000000'];
        $canonical = 'shop=test-shop.myshopify.comtimestamp=1700000000';
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        // Attacker changes the shop after the signature was computed.
        $tampered = ['shop' => 'attacker-shop.myshopify.com', 'timestamp' => '1700000000', 'signature' => $signature];

        $this->assertFalse($this->verifier()->isValid($tampered));
    }

    public function test_a_signature_computed_with_the_wrong_secret_is_invalid(): void
    {
        $params = ['shop' => 'test-shop.myshopify.com'];
        $wrongSignature = hash_hmac('sha256', 'shop=test-shop.myshopify.com', 'wrong-secret');

        $this->assertFalse($this->verifier()->isValid([...$params, 'signature' => $wrongSignature]));
    }

    public function test_a_missing_signature_is_invalid(): void
    {
        $this->assertFalse($this->verifier()->isValid(['shop' => 'test-shop.myshopify.com']));
    }

    public function test_an_extra_injected_parameter_invalidates_the_signature(): void
    {
        $canonical = 'shop=test-shop.myshopify.com';
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        // Attacker adds logged_in_customer_id that was never part of what Shopify actually signed.
        $tampered = ['shop' => 'test-shop.myshopify.com', 'logged_in_customer_id' => '999', 'signature' => $signature];

        $this->assertFalse($this->verifier()->isValid($tampered));
    }
}
