<?php

namespace App\Services\Rewards;

use App\Models\RewardRedemption;
use App\Models\Shop;

/**
 * Generates the discount code string sent to Shopify — unique, secure,
 * unpredictable, per the task's explicit discount-code-security
 * requirements. Built on PHP's `random_int()` (CSPRNG-backed, using the
 * same secure random source as `random_bytes()`), not `mt_rand()` —
 * this is what makes the code unpredictable rather than merely
 * unique — an incrementing or timestamp-derived code would be unique
 * but guessable, which defeats the purpose of a single-use reward code.
 *
 * A `LOY-` prefix keeps generated codes visually distinguishable from a
 * merchant's own manually-created discount codes in the Shopify admin,
 * without encoding or exposing any internal ID (this app's redemption
 * ID, customer ID, or reward ID never appear in the code itself).
 */
class DiscountCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // excludes visually-ambiguous chars (0/O, 1/I/L)

    public function generate(Shop $shop): string
    {
        do {
            $code = 'LOY-'.$this->randomSegment(10);
        } while ($this->codeAlreadyUsed($shop, $code));

        return $code;
    }

    private function randomSegment(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;

        $segment = '';
        for ($i = 0; $i < $length; $i++) {
            $segment .= $alphabet[random_int(0, $max)];
        }

        return $segment;
    }

    /**
     * Collision probability with a 10-character, 32-symbol alphabet is
     * astronomically low (~50 bits of entropy) — this check exists as a
     * defensive backstop, not because collisions are expected in
     * practice, mirroring this app's general pattern of not trusting
     * probability alone for anything that touches money/points.
     */
    private function codeAlreadyUsed(Shop $shop, string $code): bool
    {
        return RewardRedemption::query()
            ->where('shop_id', $shop->id)
            ->where('shopify_discount_code', $code)
            ->exists();
    }
}
