<?php

namespace Tests\Unit\Services\Shopify;

use App\Exceptions\Shopify\InvalidShopDomainException;
use App\Services\Shopify\ShopDomainValidator;
use Tests\TestCase;

class ShopDomainValidatorTest extends TestCase
{
    private ShopDomainValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ShopDomainValidator;
    }

    public function test_accepts_a_well_formed_shop_domain(): void
    {
        $this->assertSame(
            'my-store.myshopify.com',
            $this->validator->validateAndNormalize('my-store.myshopify.com'),
        );
    }

    public function test_normalizes_case(): void
    {
        $this->assertSame(
            'my-store.myshopify.com',
            $this->validator->validateAndNormalize('My-Store.MyShopify.Com'),
        );
    }

    public function test_strips_a_scheme_and_path_if_present(): void
    {
        $this->assertSame(
            'my-store.myshopify.com',
            $this->validator->validateAndNormalize('https://my-store.myshopify.com/admin'),
        );
    }

    /** @dataProvider invalidDomainProvider */
    public function test_rejects_invalid_or_malicious_domains(string $input): void
    {
        $this->expectException(InvalidShopDomainException::class);
        $this->validator->validateAndNormalize($input);
    }

    public static function invalidDomainProvider(): array
    {
        return [
            'not a shopify domain at all' => ['attacker.com'],
            'suffix-spoofing attack' => ['my-store.myshopify.com.attacker.com'],
            'prefix-spoofing attack' => ['attacker.com/my-store.myshopify.com'],
            'internal/SSRF target' => ['169.254.169.254'],
            'localhost SSRF target' => ['localhost'],
            'empty string' => [''],
            'missing tld' => ['my-store.myshopify'],
            'garbage characters' => ['not_a_valid_domain!!'],
            'double dot' => ['my..store.myshopify.com'],
            'wrong tld entirely' => ['my-store.myshopify.net'],
        ];
    }

    public function test_is_valid_returns_boolean_without_throwing(): void
    {
        $this->assertTrue($this->validator->isValid('my-store.myshopify.com'));
        $this->assertFalse($this->validator->isValid('attacker.com'));
        $this->assertFalse($this->validator->isValid(null));
    }
}
