<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * USERS: a shop-staff dashboard account. Distinct from `Customer`
 * (Shopify storefront shoppers) — see migration comment. `shop_id` is
 * nullable to accommodate platform-operator accounts; every merchant
 * staff row has one.
 */
class User extends Authenticatable
{
    use BelongsToShop, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['shop_id', 'role_id', 'name', 'email', 'password', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->role?->hasPermission($slug) ?? false;
    }
}
