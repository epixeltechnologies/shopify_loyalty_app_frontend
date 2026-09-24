<?php

namespace App\Repositories\Contracts;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanRepositoryInterface
{
    public function all(): Collection;

    public function findBySlug(string $slug): ?Plan;

    public function findByShopifyHandle(string $handle): ?Plan;

    public function default(): ?Plan;
}
