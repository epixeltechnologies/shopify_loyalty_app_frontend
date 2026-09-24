export interface FeatureEntitlement {
  key: string;
  name: string;
  group: string | null;
  available: boolean;
}

export interface LimitEntitlement {
  used: number;
  limit: number | null;
  remaining: number | null;
}

export interface EntitlementsSummary {
  plan: string | null;
  features: FeatureEntitlement[];
  limits: {
    active_customers: LimitEntitlement;
    active_reward_campaigns: LimitEntitlement;
    active_rewards?: LimitEntitlement;
  };
}

/**
 * Matches the backend's structured `LIMIT_REACHED` error payload — see
 * App\Exceptions\Billing\LimitReachedException::toResponsePayload() and
 * docs/ENTITLEMENTS.md. Read this off an axios error's
 * `error.response.data` when `error_code === 'LIMIT_REACHED'`.
 */
export interface LimitReachedError {
  message: string;
  error_code: 'LIMIT_REACHED';
  feature: string;
  current_usage: number;
  limit: number;
  required_plan?: string;
  upgrade_action: string;
}
