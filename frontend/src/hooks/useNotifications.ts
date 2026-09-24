import { useNotificationStore } from '@/stores/notificationStore';

/**
 * Thin hook wrapper over notificationStore so components don't import
 * zustand directly — keeps the state-management library an
 * implementation detail of `stores/`, swappable without touching call sites.
 */
export function useNotifications() {
  const notify = useNotificationStore((s) => s.notify);
  const dismiss = useNotificationStore((s) => s.dismiss);
  const notifications = useNotificationStore((s) => s.notifications);

  return { notify, dismiss, notifications };
}
