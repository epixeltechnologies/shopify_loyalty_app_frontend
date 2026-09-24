import type { ClientApplication } from '@shopify/app-bridge';

/**
 * Shared shape of the App Bridge context — extracted here (rather than
 * left inline in contexts/ShopifyBridgeProvider.tsx) so any file that
 * needs to describe "something that depends on an App Bridge session"
 * (a hook, a test mock, a future embedded-extension bridge) can import
 * the type without importing the provider implementation itself.
 */
export interface ShopifyBridgeContextValue {
  app: ClientApplication;
  shop: string;
  getToken: () => Promise<string>;
}

/** The two query params Shopify appends to every embedded app load. See ShopifyBridgeProvider. */
export interface ShopifyEmbeddedParams {
  host: string;
  shop: string;
}
