import { Card, BlockStack, Text } from '@shopify/polaris';
import { PlanLimitIndicator } from './PlanLimitIndicator';
import { UpgradeBanner } from './UpgradeBanner';

interface Metric {
  label: string;
  data: { used: number; limit: number | null };
}

interface Props {
  title?: string;
  metrics: Metric[];
  /** Shown once any metric crosses 90% of its limit — a single nudge for the whole card, not one per metric. */
  nearLimitPlan?: string | null;
}

/**
 * A full "Usage" card — one or more PlanLimitIndicators plus an
 * upgrade nudge if anything is close to its limit. Used on /billing
 * (all metrics) and can be embedded standalone wherever a single
 * metric's usage matters in context (e.g. a customer-list page showing
 * "480 / 500 active customers").
 */
export function UsageCard({ title = 'Usage', metrics, nearLimitPlan }: Props) {
  const nearingLimit = metrics.some(
    (m) => m.data.limit !== null && m.data.limit > 0 && m.data.used / m.data.limit >= 0.9,
  );

  return (
    <Card>
      <BlockStack gap="400">
        <Text as="h3" variant="headingSm">
          {title}
        </Text>
        {metrics.map((metric) => (
          <PlanLimitIndicator key={metric.label} label={metric.label} used={metric.data.used} limit={metric.data.limit} />
        ))}
        {nearingLimit && nearLimitPlan && (
          <UpgradeBanner
            title="Approaching your plan limit"
            message={`You're close to a plan limit — upgrade to ${nearLimitPlan} for more room to grow.`}
            requiredPlan={nearLimitPlan}
          />
        )}
      </BlockStack>
    </Card>
  );
}
