import { BlockStack, Icon, Text } from '@shopify/polaris';
import { LockIcon } from '@shopify/polaris-icons';
import { UpgradeBanner } from './UpgradeBanner';

interface Props {
  featureName: string;
  requiredPlan: string;
  description?: string;
}

/**
 * Renders in place of a feature's real UI when `useEntitlements().hasFeature()`
 * is false — a lock icon, plain-language explanation of what's missing
 * and which plan unlocks it, and an upgrade CTA (via UpgradeBanner).
 *
 * This is presentation only — see useEntitlements' docblock. The
 * component that would otherwise render the real feature UI should
 * check `hasFeature()` and render THIS instead, but the backend
 * endpoint behind that feature enforces its own gate independently
 * regardless (EnsureFeatureEntitlement middleware / a policy /
 * LimitReachedException) — a manipulated frontend can make this
 * component fail to appear, but can never grant real access.
 */
export function FeatureLockedState({ featureName, requiredPlan, description }: Props) {
  return (
    <BlockStack gap="400" inlineAlign="center">
      <div style={{ opacity: 0.6 }}>
        <Icon source={LockIcon} tone="subdued" />
      </div>
      <BlockStack gap="100" inlineAlign="center">
        <Text as="h3" variant="headingSm" alignment="center">
          {featureName} is a {requiredPlan} feature
        </Text>
        {description && (
          <Text as="p" tone="subdued" alignment="center">
            {description}
          </Text>
        )}
      </BlockStack>
      <UpgradeBanner
        title={`Unlock ${featureName}`}
        message={`Upgrade to ${requiredPlan} to use this feature.`}
        requiredPlan={requiredPlan}
      />
    </BlockStack>
  );
}
