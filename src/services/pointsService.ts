import { apiClient } from '@/services/apiClient';
import type { Paginated, PointAccountSummary, PointStatistics, PointTransaction } from '@/types/domain';

export const pointsService = {
  history: (customerId: number, page = 1) =>
    apiClient
      .get<Paginated<PointTransaction>>(`/customers/${customerId}/points`, { params: { page } })
      .then((r) => r.data),

  balance: (customerId: number) =>
    apiClient.get<{ data: PointAccountSummary }>(`/customers/${customerId}/points/balance`).then((r) => r.data.data),

  adjust: (customerId: number, points: number, note: string, adminIdentity?: string) =>
    apiClient
      .post<{ data: PointTransaction }>(`/customers/${customerId}/points/adjust`, {
        points,
        note,
        admin_identity: adminIdentity,
      })
      .then((r) => r.data.data),

  statistics: () => apiClient.get<{ data: PointStatistics }>('/points/statistics').then((r) => r.data.data),
};
