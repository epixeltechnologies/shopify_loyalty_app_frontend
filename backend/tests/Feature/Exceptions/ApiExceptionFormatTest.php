<?php

namespace Tests\Feature\Exceptions;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

class ApiExceptionFormatTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    public function test_a_missing_route_returns_the_standard_error_envelope(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertJson(['error_code' => 'NOT_FOUND'])
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_a_validation_failure_returns_field_level_errors(): void
    {
        $shop = Shop::factory()->create();
        $plan = \App\Models\Plan::factory()->starter()->create();
        \App\Models\Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson('/api/v1/customers', []) // missing required shopify_customer_id
            ->assertStatus(422)
            ->assertJson(['error_code' => 'VALIDATION_FAILED'])
            ->assertJsonStructure(['message', 'error_code', 'errors' => ['shopify_customer_id']]);
    }

    public function test_every_invalid_field_is_reported_together_not_just_the_first(): void
    {
        $shop = Shop::factory()->create();
        $plan = \App\Models\Plan::factory()->starter()->create();
        \App\Models\Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        // AdjustPointsRequest requires both `points` (non-zero) and `note`.
        $customer = \App\Models\Customer::factory()->for($shop)->create();

        $this->actingAsShop($shop)
            ->postJson("/api/v1/customers/{$customer->id}/points/adjust", ['points' => 0])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['points', 'note']]);
    }

    public function test_every_response_carries_a_request_id_header(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertHeader('X-Request-Id');
    }
}
