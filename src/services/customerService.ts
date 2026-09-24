import { apiClient } from '@/services/apiClient';
import type { Customer, Paginated } from '@/types/domain';

export interface CustomerListParams {
  page?: number;
  search?: string;
  status?: 'active' | 'suspended';
  vip_tier_id?: number;
  sort?: string;
}

/**
 * One file per API domain, mirroring the backend's route grouping
 * (routes/api.php: Customers, Points, Rewards, Referrals, VIP Tiers,
 * Analytics, Settings, Billing). Pages call these functions rather than
 * `apiClient` directly, so a backend route change only touches one file.
 */
export const customerService = {
  list: (params: CustomerListParams = {}) =>
    apiClient.get<Paginated<Customer>>('/customers', { params }).then((r) => r.data),

  get: (id: number) => apiClient.get<{ data: Customer }>(`/customers/${id}`).then((r) => r.data.data),

  enroll: (payload: { shopify_customer_id: string; email?: string; first_name?: string; last_name?: string }) =>
    apiClient.post<{ data: Customer }>('/customers', payload).then((r) => r.data.data),
};
