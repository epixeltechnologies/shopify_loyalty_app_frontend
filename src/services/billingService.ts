import { apiClient } from '@/services/apiClient';
import type {
  DowngradeEligibility,
  Plan,
  SubscriptionDetail,
  SubscriptionStatus,
  UsageSummary,
} from '@/types/billing';

export const billingService = {
  plans: () => apiClient.get<{ data: Plan[] }>('/billing/plans').then((r) => r.data.data),

  status: () => apiClient.get<SubscriptionStatus>('/billing/status').then((r) => r.data),

  /** Richer subscription detail (dates, status, recent history) for the /subscription page. */
  subscription: () =>
    apiClient.get<{ data: SubscriptionDetail }>('/billing/subscription').then((r) => r.data.data),

  /** Current usage vs. plan limits, for the usage bars on /billing and /plans. */
  usage: () => apiClient.get<{ data: UsageSummary }>('/billing/usage').then((r) => r.data.data),

  /**
   * Non-mutating preview of what would block a downgrade to `plan` —
   * called before the merchant commits, so /plans can warn inline.
   * `subscribe()` enforces the same check server-side regardless.
   */
  downgradeCheck: (plan: string) =>
    apiClient
      .get<DowngradeEligibility>('/billing/downgrade-check', { params: { plan } })
      .then((r) => r.data),

  subscribe: (plan: string) =>
    apiClient.post<{ confirmation_url: string }>('/billing/subscribe', { plan }).then((r) => r.data),
};
