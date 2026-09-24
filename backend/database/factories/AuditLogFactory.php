<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'actor_type' => 'user',
            'action' => $this->faker->randomElement(['points.manual_adjustment', 'settings.updated', 'reward.created']),
            'changes' => ['before' => [], 'after' => []],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
