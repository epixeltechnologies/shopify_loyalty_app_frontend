import { useEffect } from 'react';
import { useShopifyBridge } from '@/contexts/ShopifyBridgeProvider';
import { useAuthStore } from '@/stores/authStore';
import { shopService } from '@/services/shopService';
import { billingService } from '@/services/billingService';

/**
 * Composes the two things "auth" means in this app: a resolved App
 * Bridge session (ShopifyBridgeProvider) and an active subscription
 * (billing/status) — see ProtectedRoute, which is the enforcement
 * point. This hook is for pages/components that just need to *read*
 * that state (e.g. showing the shop domain in a header).
 */
export function useAuth() {
  const { shop } = useShopifyBridge();
  const { shopDomain, hasActiveSubscription, setShopDomain, setSubscriptionStatus } = useAuthStore();

  useEffect(() => {
    if (shop) setShopDomain(shop);
  }, [shop, setShopDomain]);

  useEffect(() => {
    shopService
      .current()
      .then((current) => setSubscriptionStatus(current.has_active_subscription))
      .catch(() => setSubscriptionStatus(false));
  }, [setSubscriptionStatus]);

  return {
    shopDomain,
    hasActiveSubscription,
    refreshSubscriptionStatus: () =>
      billingService.status().then((s) => setSubscriptionStatus(s.has_active_subscription)),
  };
}
