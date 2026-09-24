import type { PropsWithChildren } from 'react';
import { AppLayout } from '@/components/layout/AppLayout';
import { Notifications } from '@/components/common/Notifications';

/**
 * The full embedded-admin chrome (Polaris Frame + nav + top bar, from
 * components/layout/) plus the app-wide concerns every authenticated
 * page needs: the global notification toast queue. `layouts/` composes
 * `components/` into page-level shells; it never defines new chrome of
 * its own.
 */
export function AdminLayout({ children }: PropsWithChildren) {
  return (
    <AppLayout>
      {children}
      <Notifications />
    </AppLayout>
  );
}
