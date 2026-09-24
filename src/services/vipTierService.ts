import { apiClient } from '@/services/apiClient';
import type { Customer, Paginated, VipStatistics, VipTier, VipTierProgress } from '@/types/domain';

export interface VipSettings {
  enabled: boolean;
  notify_on_upgrade: boolean;
  notify_on_downgrade: boolean;
}

export const vipTierService = {
  list: () => apiClient.get<{ data: VipTier[] }>('/vip-tiers').then((r) => r.data.data),

  settings: () => apiClient.get<{ data: VipSettings }>('/vip-tiers/settings').then((r) => r.data.data),

  updateSettings: (payload: Partial<VipSettings>) =>
    apiClient.patch<{ data: VipSettings }>('/vip-tiers/settings', payload).then((r) => r.data.data),

  get: (id: number) => apiClient.get<{ data: VipTier }>(`/vip-tiers/${id}`).then((r) => r.data.data),

  create: (payload: Partial<VipTier>) =>
    apiClient.post<{ data: VipTier }>('/vip-tiers', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<VipTier>) =>
    apiClient.patch<{ data: VipTier }>(`/vip-tiers/${id}`, payload).then((r) => r.data.data),

  reorder: (orderedIds: number[]) =>
    apiClient.post<{ data: VipTier[] }>('/vip-tiers/reorder', { order: orderedIds }).then((r) => r.data.data),

  evaluateNow: () => apiClient.post<{ data: { message: string } }>('/vip-tiers/evaluate').then((r) => r.data.data),

  statistics: () => apiClient.get<{ data: VipStatistics }>('/vip-tiers/statistics').then((r) => r.data.data),

  downgradeWarnings: () =>
    apiClient.get<{ data: Customer[] }>('/vip-tiers/downgrade-warnings').then((r) => r.data.data),

  customersInTier: (tierId: number, page = 1) =>
    apiClient.get<Paginated<Customer>>(`/vip-tiers/${tierId}/customers`, { params: { page } }).then((r) => r.data),

  customerCurrentTier: (customerId: number) =>
    apiClient
      .get<{ data: { id: number; name: string; description: string | null; benefits: Record<string, unknown> | null } | null }>(
        `/customers/${customerId}/vip/current`,
      )
      .then((r) => r.data.data),

  customerProgress: (customerId: number) =>
    apiClient.get<{ data: VipTierProgress }>(`/customers/${customerId}/vip/progress`).then((r) => r.data.data),

  customerBenefits: (customerId: number) =>
    apiClient
      .get<{ data: Record<string, unknown> }>(`/customers/${customerId}/vip/benefits`)
      .then((r) => r.data.data),

  customerHistory: (customerId: number, page = 1) =>
    apiClient
      .get<{ data: Array<{ from_tier: string | null; to_tier: string | null; direction: string; effective_date: string | null }>; meta?: { current_page: number; last_page: number; total: number } }>(
        `/customers/${customerId}/vip/history`,
        { params: { page } },
      )
      .then((r) => r.data),
};
