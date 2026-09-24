<?php

namespace App\Exceptions\Rewards;

use RuntimeException;

/** Thrown by ShopifyDiscountService when Shopify's discount API rejects or fails a discount-creation request — caught by FulfillRewardRedemptionJob to trigger the points refund + `failed` status. */
class DiscountCreationFailedException extends RuntimeException {}
