import { Banner } from '@shopify/polaris';
import { PlansPage } from '@/pages/Plans/PlansPage';
import { MinimalLayout } from '@/layouts/MinimalLayout';

/**
 * Rendered by ProtectedRoute instead of the app shell whenever the
 * shop has no active/trialing subscription. Reuses PlansPage (the
 * actual plan chooser) so the merchant can subscribe immediately
 * without navigating away.
 */
export function BillingRequiredPage() {
  return (
    <MinimalLayout>
      <div style={{ padding: '16px' }}>
        <Banner tone="warning" title="A subscription is required">
          Select a plan below to unlock the app.
        </Banner>
      </div>
      <PlansPage />
    </MinimalLayout>
  );
}
