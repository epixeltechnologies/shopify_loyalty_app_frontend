import { Toast } from '@shopify/polaris';
import { useNotifications } from '@/hooks/useNotifications';

/**
 * Renders the global notification queue (stores/notificationStore.ts) as
 * stacked Polaris Toasts. Mounted once in AppLayout — nothing else needs
 * to render a Toast directly; call `useNotifications().notify(...)` from
 * anywhere instead.
 */
export function Notifications() {
  const { notifications, dismiss } = useNotifications();

  return (
    <>
      {notifications.map((n) => (
        <Toast
          key={n.id}
          content={n.message}
          error={n.tone === 'critical'}
          onDismiss={() => dismiss(n.id)}
          duration={4500}
        />
      ))}
    </>
  );
}
