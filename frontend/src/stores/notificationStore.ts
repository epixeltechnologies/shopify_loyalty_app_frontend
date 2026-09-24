import { create } from 'zustand';

export type NotificationTone = 'success' | 'critical' | 'info';

export interface AppNotification {
  id: string;
  message: string;
  tone: NotificationTone;
}

interface NotificationState {
  notifications: AppNotification[];
  notify: (message: string, tone?: NotificationTone) => void;
  dismiss: (id: string) => void;
}

/**
 * Global notification queue. Any service, hook, or component can call
 * `useNotificationStore.getState().notify(...)` (or the `useNotifications`
 * hook) without needing to be inside a specific provider tree — this is
 * the one piece of frontend state that legitimately needs to be global
 * rather than colocated with a page, since errors can originate from the
 * API client's response interceptor, outside of any component.
 */
export const useNotificationStore = create<NotificationState>((set) => ({
  notifications: [],
  notify: (message, tone = 'info') =>
    set((state) => ({
      notifications: [...state.notifications, { id: crypto.randomUUID(), message, tone }],
    })),
  dismiss: (id) =>
    set((state) => ({
      notifications: state.notifications.filter((n) => n.id !== id),
    })),
}));
