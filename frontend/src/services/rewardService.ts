import { apiClient } from '@/services/apiClient';
import type { Paginated, Reward, RewardRedemption, RewardStatistics } from '@/types/domain';

/** Rewards catalog + redemption — see services/pointRuleService.ts for how points are earned. */
export const rewardService = {
  listRewards: (page = 1) =>
    apiClient.get<Paginated<Reward>>('/rewards', { params: { page } }).then((r) => r.data),

  availableRewards: () => apiClient.get<{ data: Reward[] }>('/rewards/available').then((r) => r.data.data),

  getReward: (id: number) => apiClient.get<{ data: Reward }>(`/rewards/${id}`).then((r) => r.data.data),

  createReward: (payload: Partial<Reward>) =>
    apiClient.post<{ data: Reward }>('/rewards', payload).then((r) => r.data.data),

  updateReward: (id: number, payload: Partial<Reward>) =>
    apiClient.patch<{ data: Reward }>(`/rewards/${id}`, payload).then((r) => r.data.data),

  redeem: (customerId: number, rewardId: number, idempotencyKey?: string) =>
    apiClient
      .post<{ data: RewardRedemption }>(`/customers/${customerId}/rewards/${rewardId}/redeem`, {
        idempotency_key: idempotencyKey,
      })
      .then((r) => r.data.data),

  redemptionHistory: (page = 1) =>
    apiClient.get<Paginated<RewardRedemption>>('/reward-redemptions', { params: { page } }).then((r) => r.data),

  statistics: () => apiClient.get<{ data: RewardStatistics }>('/rewards/statistics').then((r) => r.data.data),

  customerEligibleRewards: (customerId: number) =>
    apiClient.get<{ data: Reward[] }>(`/customers/${customerId}/rewards/eligible`).then((r) => r.data.data),

  customerRedemptionHistory: (customerId: number, page = 1) =>
    apiClient
      .get<Paginated<RewardRedemption>>(`/customers/${customerId}/rewards/redemptions`, { params: { page } })
      .then((r) => r.data),
};
