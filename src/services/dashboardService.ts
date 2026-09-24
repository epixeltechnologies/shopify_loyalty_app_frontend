import { apiClient } from '@/services/apiClient';

export interface DashboardSummary {
  plan: string;
  customer_slots_remaining: number | null;
  available_vip_tiers: string[];
  has_advanced_analytics: boolean;
}

export const dashboardService = {
  summary: () => apiClient.get<{ data: DashboardSummary }>('/dashboard').then((r) => r.data.data),
};
