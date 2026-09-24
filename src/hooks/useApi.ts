import { useCallback, useEffect, useRef, useState } from 'react';

interface UseApiState<T> {
  data: T | null;
  error: unknown;
  isLoading: boolean;
}

/**
 * Standard data-fetching hook used by every page (see pages/Dashboard,
 * pages/Billing) so loading/error/data handling is written once. Errors
 * are still thrown into the global notification queue by the apiClient
 * response interceptor — this hook's `error` is for page-level rendering
 * (e.g. an empty state), not for showing a toast a second time.
 */
export function useApi<T>(fetcher: () => Promise<T>, deps: unknown[] = []): UseApiState<T> & { refetch: () => void } {
  const [state, setState] = useState<UseApiState<T>>({ data: null, error: null, isLoading: true });
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
