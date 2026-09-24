import { BlockStack, Text } from '@shopify/polaris';
import { UpgradeBanner } from './UpgradeBanner';
import { formatPoints } from '@/utils/formatters';

interface Props {
  resourceName: string;
  used: number;
  limit: number;
  requiredPlan?: string | null;
}

/**
 * Distinct from FeatureLockedState: this is for a resource the merchant
 * DOES have access to, but has hit the numeric ceiling of (e.g. "5 of 5
 * active reward campaigns") — the messaging is "you've used up your
 * allowance," not "this feature isn't available to you at all." Shown
 * inline where a "Create" action would normally be, in place of (or
 * disabling) that action.
 */
export function PlanLimitReached({ resourceName, used, limit, requiredPlan }: Props) {
  return (
    <BlockStack gap="300">
      <Text as="p" tone="subdued">
        You&apos;ve reached your plan&apos;s limit of {formatPoints(limit)} {resourceName}
        {' '}({formatPoints(used)} / {formatPoints(limit)} in use). Remove an existing one, or upgrade for more room.
      </Text>
      <UpgradeBanner
        title={`Limit reached: ${resourceName}`}
        message={`Upgrade${requiredPlan ? ` to ${requiredPlan}` : ''} for a higher limit on ${resourceName}.`}
        requiredPlan={requiredPlan}
      />
    </BlockStack>
  );
}
