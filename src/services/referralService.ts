import { apiClient } from '@/services/apiClient';
import type { Paginated, Referral, ReferralSettings, ReferralStatistics } from '@/types/domain';

export const referralService = {
  list: (page = 1, status?: string) =>
    apiClient.get<Paginated<Referral>>('/referrals', { params: { page, status } }).then((r) => r.data),

  get: (id: number) => apiClient.get<{ data: Referral }>(`/referrals/${id}`).then((r) => r.data.data),

  fraudQueue: () => apiClient.get<Paginated<Referral>>('/referrals/fraud-queue').then((r) => r.data),

  clearFraud: (id: number) => apiClient.post<{ data: Referral }>(`/referrals/${id}/clear-fraud`).then((r) => r.data.data),

  confirmFraud: (id: number) =>
    apiClient.post<{ data: Referral }>(`/referrals/${id}/confirm-fraud`).then((r) => r.data.data),

  statistics: () => apiClient.get<{ data: ReferralStatistics }>('/referrals/statistics').then((r) => r.data.data),

  settings: () => apiClient.get<{ data: ReferralSettings }>('/referrals/settings').then((r) => r.data.data),

  updateSettings: (payload: Partial<ReferralSettings>) =>
    apiClient.patch<{ data: ReferralSettings }>('/referrals/settings', payload).then((r) => r.data.data),

  customerCode: (customerId: number) =>
    apiClient.get<{ data: { referral_code: string } }>(`/customers/${customerId}/referral/code`).then((r) => r.data.data),

  customerLink: (customerId: number) =>
    apiClient
      .get<{ data: { referral_code: string; referral_url: string } }>(`/customers/${customerId}/referral/link`)
      .then((r) => r.data.data),

  customerStatistics: (customerId: number) =>
    apiClient
      .get<{ data: { total_referrals: number; successful_referrals: number; pending_referrals: number } }>(
        `/customers/${customerId}/referral/statistics`,
      )
      .then((r) => r.data.data),

  customerHistory: (customerId: number, page = 1) =>
    apiClient
      .get<Paginated<{ id: number; status: string; created_at: string; rewarded_at: string | null }>>(
        `/customers/${customerId}/referral/history`,
        { params: { page } },
      )
      .then((r) => r.data),
};
