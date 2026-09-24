import axios, { type InternalAxiosRequestConfig } from 'axios';
import { useNotificationStore } from '@/stores/notificationStore';

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL as string,
});

/**
 * Injected once, from ShopifyBridgeProvider, so every request carries a
 * fresh App Bridge session token without every call site knowing about
 * App Bridge. See App.tsx / ShopifyBridgeProvider.
 */
export function attachSessionTokenInterceptor(getToken: () => Promise<string>) {
  apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
    const token = await getToken();
    config.headers.Authorization = `Bearer ${token}`;

    return config;
  });
}

/**
 * Centralized error handling for every API call in the app: the
 * subscription-required short-circuit, validation errors surfaced as a
 * notification, rate-limit backoff messaging, and a generic fallback —
 * so individual pages only need to handle the *happy* path and let
 * unexpected failures surface consistently via the global notification
 * queue (see stores/notificationStore.ts, components/common/Notifications.tsx).
 */
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const errorCode = error.response?.data?.error_code;
    const notify = useNotificationStore.getState().notify;

    if (errorCode === 'SUBSCRIPTION_REQUIRED') {
      window.location.assign('/billing');

      return Promise.reject(error);
    }

    if (status === 422) {
      const firstError = Object.values(error.response?.data?.errors ?? {})[0];
      notify(Array.isArray(firstError) ? String(firstError[0]) : 'Please check the form and try again.', 'critical');
    } else if (status === 403) {
      notify(error.response?.data?.message ?? 'Your current plan does not include this feature.', 'critical');
    } else if (status === 429) {
      notify('Too many requests — please wait a moment and try again.', 'critical');
    } else if (!error.response) {
      notify('Network error — please check your connection.', 'critical');
    } else if (status >= 500) {
      notify('Something went wrong on our end. Please try again.', 'critical');
    }

    return Promise.reject(error);
  },
);

