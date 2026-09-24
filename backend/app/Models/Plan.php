<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BILLING: a purchasable plan (Starter, Professional, or any future
 * plan). Limits/features are data, never hard-coded — see
 * docs/ARCHITECTURE.md#entitlement-architecture.
 */
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'shopify_plan_handle', 'description',
        'price_monthly_cents', 'currency', 'max_active_customers',
        'max_active_point_rules', 'max_active_rewards', 'sort_order', 'is_active', 'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasUnlimitedCustomers(): bool
    {
        return is_null($this->max_active_customers);
    }

    public function hasUnlimitedPointRules(): bool
    {
        return is_null($this->max_active_point_rules);
    }

    public function hasUnlimitedRewards(): bool
    {
        return is_null($this->max_active_rewards);
    }
}
