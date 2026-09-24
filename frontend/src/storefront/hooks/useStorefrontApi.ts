import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * The storefront widget's own minimal data-fetching hook — deliberately
 * separate from the admin dashboard's `hooks/useApi.ts` (no zustand
 * notification-store dependency, no admin-specific error handling; a
 * theme embedding this widget shouldn't need to load any of the admin
 * bundle's state-management machinery). Same shape/contract otherwise,
 * so anyone familiar with the admin app recognizes the pattern.
 */
export function useStorefrontApi<T>(fetcher: () => Promise<T>, deps: unknown[] = []) {
  const [state, setState] = useState<{ data: T | null; error: Error | null; isLoading: boolean }>({
    data: null,
    error: null,
    isLoading: true,
  });
  const fetcherRef = useRef(fetcher);
  fetcherRef.current = fetcher;

  const load = useCallback(() => {
    let cancelled = false;
    setState((s) => ({ ...s, isLoading: true }));

    fetcherRef
      .current()
      .then((data) => {
        if (!cancelled) setState({ data, error: null, isLoading: false });
      })
      .catch((error) => {
        if (!cancelled) setState({ data: null, error, isLoading: false });
      });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  useEffect(() => load(), [load]);

  return { ...state, refetch: load };
}
