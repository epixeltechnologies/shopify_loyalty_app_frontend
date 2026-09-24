import { useUiStore } from '@/stores/uiStore';

/** Named global loading flag — see stores/uiStore.ts for when to use this vs. local state. */
export function useLoading(key: string) {
  const isLoading = useUiStore((s) => s.isLoading(key));
  const setLoading = useUiStore((s) => s.setLoading);

  return { isLoading, start: () => setLoading(key, true), stop: () => setLoading(key, false) };
}
