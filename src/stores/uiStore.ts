import { create } from 'zustand';

interface UiState {
  /** Keyed by an arbitrary operation name so unrelated loading states never collide. */
  loadingKeys: Record<string, boolean>;
  setLoading: (key: string, isLoading: boolean) => void;
  isLoading: (key: string) => boolean;
}

/**
 * Global UI state (currently: named loading flags for cross-cutting
 * operations like "subscribing to a plan", used by components that
 * aren't the ones that triggered the request — e.g. a top-level page
 * spinner while a nested action is in flight). Page-local loading state
 * should stay in the page's own useState/useApi call, not here.
 */
export const useUiStore = create<UiState>((set, get) => ({
  loadingKeys: {},
  setLoading: (key, isLoading) =>
    set((state) => ({ loadingKeys: { ...state.loadingKeys, [key]: isLoading } })),
  isLoading: (key) => Boolean(get().loadingKeys[key]),
}));
