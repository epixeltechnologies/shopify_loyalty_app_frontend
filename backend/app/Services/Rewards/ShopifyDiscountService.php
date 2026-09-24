<?php

namespace App\Services\Rewards;

use App\Exceptions\Rewards\DiscountCreationFailedException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\Rewards\DiscountTypes\RewardDiscountTypeInterface;
use App\Services\Shopify\ShopifyGraphQLClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Dedicated Shopify discount-creation service — no other class calls
 * the Shopify Admin API for discounts, matching this app's
 * one-abstraction-per-external-concern convention
 * (`ShopifyGraphQLClient` itself, `WebhookRegistrationService`, the
 * various sync services). Uses Shopify's CURRENT discount-code
 * mutations (`discountCodeBasicCreate`/`discountCodeFreeShippingCreate`)
 * — the older REST `PriceRule`/`DiscountCode` API is deprecated; this
 * app never calls it.
 *
 * Extensibility for new reward types is via `RewardDiscountTypeInterface`
 * (see that interface's docblock) — this class resolves the matching
 * strategy for a reward's `type` and delegates only the
 * type-specific `customerGets` fragment to it; everything else
 * (targeting, security, usage limits, date window) is assembled once,
 * identically, for every type.
 */
class ShopifyDiscountService
{
    /** @param  array<RewardDiscountTypeInterface>  $discountTypes */
    public function __construct(
        private readonly ShopifyGraphQLClient $client,
        private readonly DiscountCodeGenerator $codeGenerator,
        private readonly array $discountTypes,
    ) {}

    /**
     * @return array{discount_id: string, discount_code: string}
     *
     * @throws DiscountCreationFailedException
     */
    public function createForRedemption(Reward $reward, Customer $customer, RewardRedemption $redemption): array
    {
        $type = $this->resolveType($reward->type);
        $code = $this->codeGenerator->generate($customer->shop);

        $shared = $this->sharedInput($reward, $customer, $code);

        [$mutation, $inputKey] = $this->mutationFor($type);

        $variables = [$inputKey => array_merge($shared, $this->typeSpecificInput($type, $reward))];

        try {
            $data = $this->client->query($customer->shop, $mutation, $variables);
        } catch (RuntimeException $e) {
            Log::warning('Shopify discount creation request failed', [
                'shop' => $customer->shop->shopify_domain, 'redemption_id' => $redemption->id, 'error' => $e->getMessage(),
            ]);

            throw new DiscountCreationFailedException("Shopify discount creation failed: {$e->getMessage()}", previous: $e);
        }

        return $this->extractResult($data, $type->mutationName(), $code, $redemption);
    }

    private function resolveType(string $rewardType): RewardDiscountTypeInterface
    {
        foreach ($this->discountTypes as $type) {
            if ($type->supports($rewardType)) {
                return $type;
            }
        }

        throw new DiscountCreationFailedException("No discount handler is registered for reward type [{$rewardType}].");
    }

    /** @return array{0: string, 1: string} [graphql mutation string, top-level input variable name] */
    private function mutationFor(RewardDiscountTypeInterface $type): array
    {
        return match ($type->mutationName()) {
            'discountCodeFreeShippingCreate' => [self::FREE_SHIPPING_MUTATION, 'freeShippingCodeDiscount'],
            default => [self::BASIC_MUTATION, 'basicCodeDiscount'],
        };
    }

    /**
     * Fields common to every discount type — critically, `customerSelection`
     * is scoped to ONLY the redeeming customer
     * (`gid://shopify/Customer/{shopify_customer_id}`), not `{all: true}`.
     * This is what makes "prevent unauthorized reuse" actually true: a
     * loyalty-redemption discount code is only ever valid for the
     * specific customer who spent points on it, even if the code string
     * itself were somehow discovered by someone else. Combined with
     * `usageLimit: 1` and `appliesOncePerCustomer: true`, the code is
     * single-use and single-customer by construction, not by
     * convention.
     */
    private function sharedInput(Reward $reward, Customer $customer, string $code): array
    {
        $input = [
            'title' => "Loyalty reward: {$reward->name}",
            'code' => $code,
            'startsAt' => now()->toIso8601String(),
            'customerSelection' => [
                'customers' => ['add' => ["gid://shopify/Customer/{$customer->shopify_customer_id}"]],
            ],
            'appliesOncePerCustomer' => true,
            'usageLimit' => 1,
        ];

        if ($reward->ends_at) {
            $input['endsAt'] = $reward->ends_at->toIso8601String();
        }

        if ($reward->min_purchase_amount_cents) {
            $input['minimumRequirement'] = [
                'subtotal' => ['greaterThanOrEqualToSubtotal' => number_format($reward->min_purchase_amount_cents / 100, 2, '.', '')],
            ];
        }

        return $input;
    }

    private function typeSpecificInput(RewardDiscountTypeInterface $type, Reward $reward): array
    {
        if ($type->mutationName() === 'discountCodeFreeShippingCreate') {
            return ['destination' => ['all' => true]];
        }

        $customerGets = $type->buildCustomerGets($reward);

        // A percentage discount can optionally be capped — Shopify
        // expresses this as a maximum shipping/discount price on the
        // items fragment for percentage-off, which this app surfaces as
        // `max_discount_amount_cents`.
        if ($reward->type === Reward::TYPE_PERCENTAGE_DISCOUNT && $reward->max_discount_amount_cents) {
            $customerGets['value']['appliesOnOneTimePurchase'] = true;
        }

        return ['customerGets' => $customerGets];
    }

    private function extractResult(array $data, string $mutationName, string $fallbackCode, RewardRedemption $redemption): array
    {
        $result = $data[$mutationName] ?? null;

        $userErrors = $result['userErrors'] ?? [];
        if (! empty($userErrors)) {
            $message = collect($userErrors)->pluck('message')->implode('; ');
            Log::warning('Shopify discount creation returned user errors', [
                'redemption_id' => $redemption->id, 'errors' => $userErrors,
            ]);

            throw new DiscountCreationFailedException("Shopify rejected the discount: {$message}");
        }

        $discountId = $result['codeDiscountNode']['id'] ?? null;
        if (! $discountId) {
            throw new DiscountCreationFailedException('Shopify did not return a discount ID.');
        }

        $returnedCode = $result['codeDiscountNode']['codeDiscount']['codes']['nodes'][0]['code'] ?? $fallbackCode;

        return ['discount_id' => $discountId, 'discount_code' => $returnedCode];
    }

    private const BASIC_MUTATION = <<<'GRAPHQL'
        mutation discountCodeBasicCreate($basicCodeDiscount: DiscountCodeBasicInput!) {
          discountCodeBasicCreate(basicCodeDiscount: $basicCodeDiscount) {
            codeDiscountNode {
              id
              codeDiscount {
                ... on DiscountCodeBasic {
                  codes(first: 1) { nodes { code } }
                }
              }
            }
            userErrors { field message code }
          }
        }
        GRAPHQL;

    private const FREE_SHIPPING_MUTATION = <<<'GRAPHQL'
        mutation discountCodeFreeShippingCreate($freeShippingCodeDiscount: DiscountCodeFreeShippingInput!) {
          discountCodeFreeShippingCreate(freeShippingCodeDiscount: $freeShippingCodeDiscount) {
            codeDiscountNode {
              id
              codeDiscount {
                ... on DiscountCodeFreeShipping {
                  codes(first: 1) { nodes { code } }
                }
              }
            }
            userErrors { field message code }
          }
        }
        GRAPHQL;
}
