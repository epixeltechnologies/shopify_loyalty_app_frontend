import { EmptyState as PolarisEmptyState } from '@shopify/polaris';
import type { ReactNode } from 'react';

interface Props {
  heading: string;
  action?: { content: string; onAction: () => void };
  children?: ReactNode;
}

/** Standard "nothing here yet" state, used across list pages (customers, rewards, referrals). */
export function EmptyState({ heading, action, children }: Props) {
  return (
    <PolarisEmptyState heading={heading} action={action} image="">
      {children}
    </PolarisEmptyState>
  );
}
