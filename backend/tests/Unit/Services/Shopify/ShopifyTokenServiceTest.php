<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Shop;
use App\Services\Shopify\ShopifyTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopifyTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopifyTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopifyTokenService;
    }

    public function test_storing_a_token_persists_it_and_the_scopes(): void
    {
        $shop = Shop::factory()->create(['access_token' => null, 'scopes' => null]);

        $updated = $this->service->store($shop, 'shpat_real_token_value', 'read_customers,write_customers');

        $this->assertSame('shpat_real_token_value', $updated->access_token);
        $this->assertSame('read_customers,write_customers', $updated->scopes);
    }

    public function test_the_token_is_encrypted_at_rest_in_the_database(): void
    {
        $shop = Shop::factory()->create();
        $this->service->store($shop, 'shpat_super_secret_value', 'read_customers');

        $rawColumnValue = DB::table('shops')->where('id', $shop->id)->value('access_token');

        // The raw DB column must never contain the plaintext token — it
        // should be Laravel's encrypted-cast ciphertext.
        $this->assertStringNotContainsString('shpat_super_secret_value', (string) $rawColumnValue);
    }

    public function test_the_token_is_hidden_from_model_serialization(): void
    {
        $shop = Shop::factory()->create();
        $this->service->store($shop, 'shpat_super_secret_value', 'read_customers');

        $array = $shop->fresh()->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
    }

    public function test_revoking_clears_the_token(): void
    {
        $shop = Shop::factory()->create();
        $this->service->store($shop, 'shpat_value', 'read_customers');

        $revoked = $this->service->revoke($shop);

        $this->assertNull($revoked->access_token);
        $this->assertFalse($this->service->hasToken($revoked));
    }

    public function test_fingerprint_never_reveals_the_token_itself(): void
    {
        $shop = Shop::factory()->create();
        $this->service->store($shop, 'shpat_super_secret_value', 'read_customers');

        $fingerprint = $this->service->fingerprint($shop->fresh());

        $this->assertNotNull($fingerprint);
        $this->assertStringNotContainsString('shpat_super_secret_value', $fingerprint);
        $this->assertLessThan(strlen('shpat_super_secret_value'), strlen($fingerprint));
    }
}
