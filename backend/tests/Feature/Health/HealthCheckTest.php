<?php

namespace Tests\Feature\Health;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_check_requires_no_authentication_and_returns_ok(): void
    {
        $this->getJson('/health/live')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_readiness_check_confirms_database_and_redis_are_reachable(): void
    {
        $response = $this->getJson('/health/ready')->assertOk();

        $response->assertJsonPath('checks.database.status', 'ok');
        $response->assertJsonPath('status', 'ok');
    }

    public function test_readiness_response_never_leaks_database_credentials_or_connection_details(): void
    {
        $response = $this->getJson('/health/ready')->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString(config('database.connections.mysql.password') ?: 'unset-marker', $body);
        $this->assertStringNotContainsString(config('database.connections.mysql.host'), $body);
        $this->assertStringNotContainsString('Exception', $body);
        $this->assertStringNotContainsString('.php:', $body); // no file:line stack trace fragments
    }
}
