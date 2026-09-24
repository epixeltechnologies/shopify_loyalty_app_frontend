export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number; per_page: number };
  links?: { first: string | null; last: string | null; prev: string | null; next: string | null };
}

export interface Customer {
  id: number;
  shopify_customer_id: string;
  email: string | null;
  first_name: string | null;
  last_name: string | null;
  points_balance: number;
  lifetime_points_earned: number;
  vip_tier: { id: number; name: string; slug: string } | null;
  referral_code: string;
  status: 'active' | 'suspended';
  enrolled_at: string | null;
}

export interface PointAccountSummary {
  balance: number;
  lifetime_earned: number;
  lifetime_redeemed: number;
  lifetime_expired: number;
  lifetime_adjusted: number;
  pending_expiry_amount: number;
  next_expiry_date: string | null;
  last_transaction_at: string | null;
}

export type PointDirection = 'earn' | 'redeem' | 'expire' | 'adjust';

export interface PointTransaction {
  id: number;
  direction: PointDirection;
  points: number;
  balance_after: number;
  source: string;
  note: string | null;
  metadata: Record<string, unknown> | null;
  expires_at: string | null;
  created_at: string;
}

export interface PointStatistics {
  total_points_earned: number;
  total_points_redeemed: number;
  total_points_expired: number;
  total_points_adjusted: number;
  customers_with_activity: number;
  transaction_count: number;
}

export type PointRuleType = 'points_per_dollar' | 'signup_bonus' | 'referral_bonus' | 'birthday_bonus' | 'review_reward' | 'custom';
export type PointRuleStatus = 'draft' | 'active' | 'paused' | 'archived';

export interface PointRule {
  id: number;
  name: string;
  type: PointRuleType;
  config: Record<string, unknown>;
  status: PointRuleStatus;
  starts_at: string | null;
  ends_at: string | null;
}

export type RewardType = 'percentage_discount' | 'fixed_discount' | 'free_shipping' | 'gift';
export type RewardStatus = 'draft' | 'active' | 'archived';

export interface CustomerEligibilityRule {
  type: 'all' | 'vip_tier_minimum' | 'specific_customers';
  vip_tier_id?: number;
  customer_ids?: number[];
}

export interface Reward {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  type: RewardType;
  points_cost: number;
  value: Record<string, unknown>;
  min_purchase_amount_cents: number | null;
  max_discount_amount_cents: number | null;
  included_product_ids: string[] | null;
  excluded_product_ids: string[] | null;
  included_collection_ids: string[] | null;
  excluded_collection_ids: string[] | null;
  customer_eligibility: CustomerEligibilityRule | null;
  max_total_redemptions: number | null;
  max_redemptions_per_customer: number | null;
  stock_limit: number | null;
  starts_at: string | null;
  ends_at: string | null;
  status: RewardStatus;
}

export type RedemptionStatus = 'pending' | 'completed' | 'failed' | 'cancelled' | 'expired';

export interface RewardRedemption {
  id: number;
  customer?: Customer;
  reward: Reward;
  points_spent: number;
  balance_before: number;
  balance_after: number;
  status: RedemptionStatus;
  shopify_discount_code: string | null;
  failure_reason: string | null;
  fulfilled_at: string | null;
  cancelled_at: string | null;
  created_at: string;
}

export interface RewardStatistics {
  total_redemptions: number;
  completed_count: number;
  pending_count: number;
  failed_count: number;
  cancelled_count: number;
  total_points_redeemed: number;
  customers_who_redeemed: number;
}

export type ReferralStatus =
  | 'created' | 'clicked' | 'registered' | 'pending' | 'qualified'
  | 'rewarded' | 'rejected' | 'fraud_detected' | 'cancelled' | 'expired';
export type FraudStatus = 'none' | 'flagged' | 'cleared' | 'confirmed';

export interface Referral {
  id: number;
  referral_code: string;
  status: ReferralStatus;
  fraud_status: FraudStatus;
  fraud_reasons: string[] | null;
  referrer: Customer | null;
  referred: Customer | null;
  qualifying_order_id: string | null;
  qualifying_order_value_cents: number | null;
  rejection_reason: string | null;
  clicked_at: string | null;
  registered_at: string | null;
  qualified_at: string | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  reward_scheduled_at: string | null;
  rewarded_at: string | null;
  created_at: string;
}

export interface ReferralSettings {
  enabled: boolean;
  referrer_reward_points: number | null;
  referee_reward_points: number | null;
  minimum_qualifying_order_cents: number | null;
  require_first_purchase: boolean;
  reward_delay_days: number;
  attribution_window_days: number;
  max_referrals_per_customer: number | null;
}

export interface ReferralStatistics {
  total_referrals: number;
  clicked_count: number;
  registered_count: number;
  qualified_count: number;
  rewarded_count: number;
  rejected_count: number;
  flagged_count: number;
  unique_referrers: number;
  registration_to_reward_rate: number;
}

export type VipQualificationMethod = 'points_earned' | 'total_spend' | 'order_count';
export type VipEvaluationPeriod = 'lifetime' | 'calendar_year' | 'rolling';

export interface VipTier {
  id: number;
  name: string;
  description: string | null;
  slug: string;
  qualification_method: VipQualificationMethod;
  threshold_points: number;
  minimum_spend_cents: number | null;
  minimum_orders: number | null;
  evaluation_period: VipEvaluationPeriod;
  rolling_period_days: number | null;
  starts_at: string | null;
  ends_at: string | null;
  sort_order: number;
  perks: Record<string, unknown> | null;
  is_active: boolean;
  is_available_to_shop: boolean;
  customer_count?: number;
}

export interface VipTierProgress {
  next_tier: { id: number; name: string; description: string | null; benefits: Record<string, unknown> | null } | null;
  qualification_method?: VipQualificationMethod;
  current_progress?: number;
  required?: number;
  remaining?: number;
  percent_complete?: number;
  message?: string;
}

export interface VipHistoryEntry {
  from_tier: string | null;
  to_tier: string | null;
  direction: 'upgrade' | 'downgrade' | 'initial';
  effective_date: string | null;
}

export interface VipStatistics {
  tiers: Array<{ id: number; name: string; sort_order: number; customer_count: number }>;
  upgrades_last_30_days: number;
  downgrades_last_30_days: number;
}
