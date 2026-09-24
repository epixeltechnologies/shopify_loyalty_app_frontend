import { apiClient } from '@/services/apiClient';

export interface CurrentShop {
  domain: string;
  name: string | null;
  onboarding_completed: boolean;
  has_active_subscription: boolean;
}

export const shopService = {
  current: () => apiClient.get<{ data: CurrentShop }>('/shop').then((r) => r.data.data),
};
