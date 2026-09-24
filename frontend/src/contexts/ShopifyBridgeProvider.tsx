import { createContext, useContext, useMemo, type PropsWithChildren } from 'react';
import createApp from '@shopify/app-bridge';
import { getSessionToken } from '@shopify/app-bridge/utilities';
import type { ShopifyBridgeContextValue, ShopifyEmbeddedParams } from '@/types/shopify';

const ShopifyBridgeContext = createContext<ShopifyBridgeContextValue | null>(null);

function readEmbeddedParams(): ShopifyEmbeddedParams {
  const params = new URLSearchParams(window.location.search);

  return { host: params.get('host') ?? '', shop: params.get('shop') ?? '' };
}

/**
 * Boots the Shopify App Bridge client using the `shop` + `host` query
 * params Shopify appends to the embedded app's iframe URL, and exposes a
 * `getToken()` helper used by the axios client to attach a fresh session
 * token to every API request (tokens are short-lived by design — see
 * services/apiClient.ts's attachSessionTokenInterceptor, wired up in
 * routes/ProtectedRoute.tsx).
 */
export function ShopifyBridgeProvider({ children }: PropsWithChildren) {
  const value = useMemo<ShopifyBridgeContextValue>(() => {
    const { host, shop } = readEmbeddedParams();

    const app = createApp({
      apiKey: import.meta.env.VITE_SHOPIFY_API_KEY as string,
      host,
      forceRedirect: true,
    });

    return {
      app,
      shop,
      getToken: () => getSessionToken(app),
    };
  }, []);

  return <ShopifyBridgeContext.Provider value={value}>{children}</ShopifyBridgeContext.Provider>;
}

export function useShopifyBridge(): ShopifyBridgeContextValue {
  const ctx = useContext(ShopifyBridgeContext);
  if (!ctx) throw new Error('useShopifyBridge must be used within ShopifyBridgeProvider');

  return ctx;
}
