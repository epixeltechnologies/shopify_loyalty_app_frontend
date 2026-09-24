export const VIP_TIER_SLUGS = ['silver', 'gold', 'platinum'] as const;
export type VipTierSlug = (typeof VIP_TIER_SLUGS)[number];

export const POINTS_DIRECTIONS = ['earn', 'redeem', 'expire', 'adjust'] as const;

export const DEFAULT_PAGE_SIZE = 25;

/** Mirrors backend `error_code` values from ApiExceptionHandler / middleware — see docs/API_ARCHITECTURE.md. */
export const API_ERROR_CODES = {
  SUBSCRIPTION_REQUIRED: 'SUBSCRIPTION_REQUIRED',
  FEATURE_NOT_AVAILABLE: 'FEATURE_NOT_AVAILABLE',
} as const;
