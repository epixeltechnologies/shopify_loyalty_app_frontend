import { storefrontClient } from './storefrontClient';
import type { LoyaltyProfile, PointsBalance, StorefrontReward, VipProgress } from './storefrontTypes';

/** One file, one service — mirrors the admin dashboard's `services/` convention (docs/FRONTEND_ARCHITECTURE.md), just pointed at the storefront client instead. */
export const storefrontService = {
  profile: () => storefrontClient.get<{ data: LoyaltyProfile }>('/profile').then((r) => r.data),
  pointsBalance: () => storefrontClient.get<{ data: PointsBalance }>('/points/balance').then((r) => r.data),
  pointsHistory: () => storefrontClient.get<{ data: unknown[] }>('/points/history').then((r) => r.data),

  availableRewards: () => storefrontClient.get<{ data: StorefrontReward[] }>('/rewards/available').then((r) => r.data),
  eligibleRewards: () => storefrontClient.get<{ data: StorefrontReward[] }>('/rewards/eligible').then((r) => r.data),
  redeemReward: (rewardId: number, idempotencyKey: string) =>
    storefrontClient.post(`/rewards/${rewardId}/redeem`, { idempotency_key: idempotencyKey }),
  redemptionHistory: () => storefrontClient.get<{ data: unknown[] }>('/rewards/redemptions').then((r) => r.data),

  referralCode: () => storefrontClient.get<{ data: { referral_code: string } }>('/referral/code').then((r) => r.data),
  referralLink: () => storefrontClient.get<{ data: { referral_code: string; referral_url: string } }>('/referral/link').then((r) => r.data),
  referralStatistics: () =>
    storefrontClient
      .get<{ data: { total_referrals: number; successful_referrals: number; pending_referrals: number } }>('/referral/statistics')
      .then((r) => r.data),

  vipCurrent: () => storefrontClient.get<{ data: LoyaltyProfile['current_vip_tier'] }>('/vip/current').then((r) => r.data),
  vipProgress: () => storefrontClient.get<{ data: VipProgress }>('/vip/progress').then((r) => r.data),
  vipBenefits: () => storefrontClient.get<{ data: Record<string, unknown> }>('/vip/benefits').then((r) => r.data),
};
