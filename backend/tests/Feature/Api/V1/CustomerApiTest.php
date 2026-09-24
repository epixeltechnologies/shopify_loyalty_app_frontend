<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_a_single_customer(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['first_name' => 'Ada']);

        $this->actingAsShop($shop)
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ada');
    }

    public function test_show_for_a_customer_in_another_shop_is_404(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $otherCustomer = Customer::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)
            ->getJson("/api/v1/customers/{$otherCustomer->id}")
            ->assertStatus(404);
    }

    public function test_search_filters_by_name_and_email(): void
    {
        $shop = Shop::factory()->create();
        Customer::factory()->for($shop)->create(['first_name' => 'Ada', 'email' => 'ada@example.com']);
        Customer::factory()->for($shop)->create(['first_name' => 'Grace', 'email' => 'grace@example.com']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/customers?search=Ada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Ada');
    }

    public function test_status_filter_returns_only_matching_customers(): void
    {
        $shop = Shop::factory()->create();
        Customer::factory()->for($shop)->create(['status' => 'active']);
        Customer::factory()->for($shop)->create(['status' => 'suspended']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/customers?status=suspended')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
