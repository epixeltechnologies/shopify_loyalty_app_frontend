import { apiClient } from '@/services/apiClient';

export interface AnalyticsSnapshot {
  date: string;
  metric: string;
  value: number;
}

export const analyticsService = {
  series: (metric: string, from: string, to: string) =>
    apiClient
      .get<{ data: AnalyticsSnapshot[] }>('/analytics/series', { params: { metric, from, to } })
      .then((r) => r.data.data),
};
