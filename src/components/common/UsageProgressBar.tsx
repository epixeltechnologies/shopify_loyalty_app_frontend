import { ProgressBar } from '@shopify/polaris';

interface Props {
  used: number;
  limit: number | null;
  /** Shown at >=90% of limit, to flag "you're about to hit this" before it actually happens. */
  warnThreshold?: number;
}

/**
 * The bar primitive — UsageCard/PlanLimitIndicator compose this with
 * labels/numbers around it. Renders nothing (returns null) when the
 * limit is unlimited, since a 0%-filled bar for "unlimited" is
 * misleading, not just uninformative — see docs/ENTITLEMENTS.md's note
 * on avoiding misleading usage data.
 */
export function UsageProgressBar({ used, limit, warnThreshold = 0.9 }: Props) {
  if (limit === null) {
    return null;
  }

  const percent = limit === 0 ? 100 : Math.min(100, (used / limit) * 100);
  const isNearLimit = percent / 100 >= warnThreshold;

  return <ProgressBar progress={percent} tone={isNearLimit ? 'critical' : 'primary'} size="small" />;
}
