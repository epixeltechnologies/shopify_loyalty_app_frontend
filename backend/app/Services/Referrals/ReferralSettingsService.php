<?php

namespace App\Services\Referrals;

use App\Models\ReferralSettings;
use App\Models\Shop;

/** REFERRALS: get-or-create + update for the shop's 1:1 referral-program configuration. */
class ReferralSettingsService
{
    public function getOrCreate(Shop $shop): ReferralSettings
    {
        return ReferralSettings::query()->firstOrCreate(['shop_id' => $shop->id]);
    }

    public function update(Shop $shop, array $attributes): ReferralSettings
    {
        $settings = $this->getOrCreate($shop);
        $settings->update($attributes);

        return $settings->fresh();
    }
}
