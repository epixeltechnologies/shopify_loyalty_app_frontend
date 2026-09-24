import { create } from 'zustand';

interface AuthState {
  shopDomain: string | null;
  hasActiveSubscription: boolean | null; // null = not yet checked
  setShopDomain: (domain: string) => void;
  setSubscriptionStatus: (active: boolean) => void;
}

/**
 * Small piece of global auth-adjacent state derived from `/auth/me` and
 * `/billing/status` (see hooks/useAuth.ts). The source of truth for
 * *authentication* itself remains the App Bridge session token
 * (ShopifyBridgeProvider) — this store only caches the two facts pages
 * across the app need to read without re-fetching.
 */
export const useAuthStore = create<AuthState>((set) => ({
  shopDomain: null,
  hasActiveSubscription: null,
  setShopDomain: (domain) => set({ shopDomain: domain }),
  setSubscriptionStatus: (active) => set({ hasActiveSubscription: active }),
}));
