<?php

namespace App\Models;

use App\Services\Billing\EntitlementService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'shopify_domain', 'shopify_id', 'name', 'email', 'owner_name',
        'country_code', 'currency', 'timezone', 'plan_display_name',
        'access_token', 'scopes', 'is_installed', 'installed_at',
        'uninstalled_at', 'last_activity_at', 'onboarding_completed', 'trial_ends_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_installed' => 'boolean',
            'onboarding_completed' => 'boolean',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop) {
            $shop->uuid ??= (string) Str::uuid();
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->latestOfMany();
    }

    public function usageCounters(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(ShopSetting::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function entitlements(): EntitlementService
    {
        return app(EntitlementService::class)->forShop($this);
    }

    protected function scopeHandle(): Attribute
    {
        return Attribute::get(fn () => Str::before($this->shopify_domain, '.myshopify.com'));
    }
}
