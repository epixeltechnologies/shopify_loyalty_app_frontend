import { apiClient } from '@/services/apiClient';
import type { Paginated, PointRule } from '@/types/domain';

/** How points are earned (points-per-dollar, signup bonus, ...) — see services/rewardService.ts for the redeemable catalog. */
export const pointRuleService = {
  list: (page = 1) =>
    apiClient.get<Paginated<PointRule>>('/point-rules', { params: { page } }).then((r) => r.data),

  get: (id: number) => apiClient.get<{ data: PointRule }>(`/point-rules/${id}`).then((r) => r.data.data),

  create: (payload: Partial<PointRule>) =>
    apiClient.post<{ data: PointRule }>('/point-rules', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<PointRule>) =>
    apiClient.patch<{ data: PointRule }>(`/point-rules/${id}`, payload).then((r) => r.data.data),
};
