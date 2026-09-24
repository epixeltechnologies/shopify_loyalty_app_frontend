export interface PlanLimits {
  max_active_customers: number | null;
  max_active_point_rules: number | null;
}

export interface Plan {
  slug: string;
  name: string;
  description: string | null;
  price_monthly: number;
  currency: string;
  limits: PlanLimits;
  features: Record<string, string[]>;
}

export interface SubscriptionStatus {
  has_active_subscription: boolean;
  subscription: { status: string; current_period_end: string | null } | null;
  plan: Plan | null;
}

export interface SubscriptionEvent {
  from_status: string | null;
  to_status: string;
  from_plan: string | null;
  to_plan: string | null;
  trigger: 'merchant' | 'shopify_webhook' | 'system';
  occurred_at: string | null;
}

export interface SubscriptionDetail {
  status: string | null;
  plan: Plan | null;
  current_period_start: string | null;
  current_period_end: string | null;
  trial_ends_at: string | null;
  cancelled_at: string | null;
  recent_events: SubscriptionEvent[];
}

export interface UsageMetric {
  used: number;
  limit: number | null;
}

export interface UsageSummary {
  active_customers: UsageMetric;
  active_point_rules: UsageMetric;
}

export interface DowngradeBlocker {
  code: string;
  message: string;
}

export interface DowngradeEligibility {
  eligible: boolean;
  blockers: DowngradeBlocker[];
  warnings: DowngradeBlocker[];
}
