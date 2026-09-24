import type { PropsWithChildren } from 'react';
import { Frame } from '@shopify/polaris';
import { Notifications } from '@/components/common/Notifications';

/**
 * Chrome-free shell (no nav/top bar) for states that precede a fully
 * authenticated, subscribed session: the loading splash and the
 * billing-required screen (see routes/ProtectedRoute.tsx).
 */
export function MinimalLayout({ children }: PropsWithChildren) {
  return (
    <Frame>
      {children}
      <Notifications />
    </Frame>
  );
}
