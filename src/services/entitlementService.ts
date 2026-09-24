import { apiClient } from '@/services/apiClient';
import type { EntitlementsSummary } from '@/types/entitlements';

export const entitlementService = {
  summary: () => apiClient.get<{ data: EntitlementsSummary }>('/entitlements').then((r) => r.data.data),
};
