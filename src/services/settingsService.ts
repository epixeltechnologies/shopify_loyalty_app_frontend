import { apiClient } from '@/services/apiClient';

export interface ShopSettings {
  program_name?: string | null;
  program_description?: string | null;
  program_status?: 'active' | 'paused';
  logo_url?: string | null;
  widget_enabled?: boolean;
  widget_primary_color?: string;
  brand_secondary_color?: string | null;
  points_expiry_days?: number | null;
  points_earning_label?: string | null;
  allow_negative_point_balance?: boolean;
  allow_manual_point_adjustments?: boolean;
  notification_email?: string | null;
  messaging?: Record<string, string> | null;
}

export const settingsService = {
  get: () => apiClient.get<{ data: ShopSettings }>('/settings').then((r) => r.data.data),
  update: (payload: Partial<ShopSettings>) =>
    apiClient.patch<{ data: ShopSettings }>('/settings', payload).then((r) => r.data.data),
};
