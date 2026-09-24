import { apiClient } from '@/services/apiClient';

export type NotificationType =
  | 'welcome' | 'points_earned' | 'reward_redeemed' | 'birthday_reward' | 'referral_reward'
  | 'vip_upgraded' | 'vip_downgraded' | 'points_expiring' | 'reward_expiring';

export interface NotificationSetting {
  type: NotificationType;
  enabled: boolean;
  subject: string | null;
  message: string | null;
}

export const NOTIFICATION_TYPE_LABELS: Record<NotificationType, string> = {
  welcome: 'Welcome',
  points_earned: 'Points earned',
  reward_redeemed: 'Reward redemption',
  birthday_reward: 'Birthday reward',
  referral_reward: 'Referral reward',
  vip_upgraded: 'VIP upgrade',
  vip_downgraded: 'VIP downgrade',
  points_expiring: 'Points expiration',
  reward_expiring: 'Reward expiration',
};

export const notificationSettingsService = {
  list: () => apiClient.get<{ data: NotificationSetting[] }>('/notification-settings').then((r) => r.data.data),

  get: (type: NotificationType) =>
    apiClient.get<{ data: NotificationSetting }>(`/notification-settings/${type}`).then((r) => r.data.data),

  update: (type: NotificationType, payload: Partial<Pick<NotificationSetting, 'enabled' | 'subject' | 'message'>>) =>
    apiClient.patch<{ data: NotificationSetting }>(`/notification-settings/${type}`, payload).then((r) => r.data.data),

  preview: (type: NotificationType, subject?: string | null, message?: string | null) =>
    apiClient
      .post<{ data: { subject: string; message: string } }>(`/notification-settings/${type}/preview`, { subject, message })
      .then((r) => r.data.data),
};
