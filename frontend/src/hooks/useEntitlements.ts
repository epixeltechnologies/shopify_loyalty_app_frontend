import { useMemo } from 'react';
import { entitlementService } from '@/services/entitlementService';
import { useApi } from '@/hooks/useApi';

/**
 * The one hook every feature-gated page/component should use — wraps
 * `GET /entitlements` (see docs/ENTITLEMENTS.md) and exposes a simple
 * `hasFeature(key)` lookup plus the raw limits, so a component never
 * has to know the shape of the backend response.
 *
 * This is a UX convenience ONLY — every backend endpoint independently
 * re-enforces its own entitlement check regardless of what this hook
 * returns (see EnsureFeatureEntitlement middleware, policies, and
 * LimitReachedException). Never treat a `true` here as a security
 * boundary; it only controls what this hook renders.
 */
export function useEntitlements() {
  const { data, isLoading, refetch } = useApi(entitlementService.summary, []);

  const featureMap = useMemo(() => {
    const map = new Map<string, boolean>();
    data?.features.forEach((f) => map.set(f.key, f.available));

    return map;
  }, [data]);

  function hasFeature(key: string): boolean {
    return featureMap.get(key) ?? false;
  }

  return {
    plan: data?.plan ?? null,
    features: data?.features ?? [],
    limits: data?.limits ?? null,
    hasFeature,
    isLoading,
    refetch,
  };
}
