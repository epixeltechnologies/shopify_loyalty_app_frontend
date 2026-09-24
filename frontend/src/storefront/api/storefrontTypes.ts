export interface LoyaltyProfile {
  first_name: string | null;
  points_balance: number;
  lifetime_points_earned: number;
  lifetime_points_redeemed: number;
  current_vip_tier: { id: number; name: string; description: string | null; benefits: Record<string, unknown> | null } | null;
  referral_code: string;
}

export interface PointsBalance {
  balance: number;
  lifetime_earned: number;
  lifetime_redeemed: number;
  next_expiry_date: string | null;
}

export interface StorefrontReward {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  type: string;
  points_cost: number;
}

export interface VipProgress {
  next_tier: { id: number; name: string; description: string | null } | null;
  qualification_method?: string;
  current_progress?: number;
  required?: number;
  remaining?: number;
  percent_complete?: number;
  message?: string;
}
