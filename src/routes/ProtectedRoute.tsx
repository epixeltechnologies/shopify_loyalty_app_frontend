import { useEffect, useState, type PropsWithChildren } from 'react';
import { Loading } from '@shopify/polaris';
import { useShopifyBridge } from '@/contexts/ShopifyBridgeProvider';
import { attachSessionTokenInterceptor, apiClient } from '@/services/apiClient';
import { BillingRequiredPage } from '@/pages/Billing/BillingRequiredPage';
import { MinimalLayout } from '@/layouts/MinimalLayout';

type Status = 'checking' | 'active' | 'required';

/**
 * Wraps every route that requires an active subscription — which, per
 * this product's no-free-plan model, is every route (see App.tsx: this
 * wraps the whole router, not individual <Route> elements, since there
 * is currently no route a shop without a subscription should reach).
 * Nothing behind this gate renders until `GET /billing/status` confirms
 * an active or trialing subscription. The API enforces the same rule
 * server-side via RequireActiveSubscription middleware — this is a UX
 * short-circuit, not the security boundary.
 *
 * Also where the App Bridge session-token interceptor gets attached to
 * the shared apiClient (see attachSessionTokenInterceptor) — this is
 * the first component in the tree that actually needs to make an API
 * call, so it's the natural place to wire that up exactly once.
 */
export function ProtectedRoute({ children }: PropsWithChildren) {
  const { getToken } = useShopifyBridge();
  const [status, setStatus] = useState<Status>('checking');

  useEffect(() => {
    attachSessionTokenInterceptor(getToken);

    apiClient
      .get('/billing/status')
      .then(({ data }) => setStatus(data.has_active_subscription ? 'active' : 'required'))
      .catch(() => setStatus('required'));
  }, [getToken]);

  if (status === 'checking') {
    return (
      <MinimalLayout>
        <Loading />
      </MinimalLayout>
    );
  }

  if (status === 'required') {
    return <BillingRequiredPage />;
  }

  return <>{children}</>;
}
