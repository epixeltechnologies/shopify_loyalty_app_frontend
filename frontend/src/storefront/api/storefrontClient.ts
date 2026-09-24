/**
 * The storefront widget's own API client — deliberately NOT the admin
 * dashboard's `services/apiClient.ts`. The admin client attaches an App
 * Bridge session token (see `attachSessionTokenInterceptor`), which
 * doesn't exist on a storefront page at all; a storefront visitor has
 * no App Bridge context. Authentication here works completely
 * differently: this widget is served through a Shopify App Proxy
 * (`https://{shop}.myshopify.com/apps/{proxy-subpath}/*`), and Shopify
 * itself appends the signed `shop`/`logged_in_customer_id`/`signature`
 * query parameters to every request that reaches this path — this
 * client never constructs or even sees those values; it just calls
 * relative URLs and lets the proxy do its job. See docs/STOREFRONT.md.
 *
 * No credentials, secrets, or Admin API tokens ever exist in this
 * bundle — everything it can do is scoped to whatever the backend's
 * `verify.shopify.app_proxy` + `storefront.customer` middleware allows
 * for the one customer Shopify's signature vouches for.
 */
const BASE_PATH = '/apps/loyalty'; // the app proxy sub-path configured in the Partner Dashboard — see docs/STOREFRONT.md

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${BASE_PATH}${path}`, {
    ...options,
    headers: { 'Content-Type': 'application/json', ...options.headers },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({ message: 'Something went wrong.' }));
    throw new Error(body.message ?? `Request failed (${response.status})`);
  }

  return response.json();
}

export const storefrontClient = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
};
