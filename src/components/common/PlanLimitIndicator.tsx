import { BlockStack, InlineStack, Text } from '@shopify/polaris';
import { UsageProgressBar } from './UsageProgressBar';
import { formatPoints } from '@/utils/formatters';

interface Props {
  label: string;
  used: number;
  limit: number | null;
}

/**
 * One metric's full usage display — label, "used / limit" text, and the
 * bar (see UsageProgressBar). "Unlimited" is spelled out explicitly
 * rather than showing a full/empty bar, so a Professional shop's
 * unlimited reward-campaign count never LOOKS like a ceiling that
 * happens to be wide, which is exactly the kind of misleading usage
 * display docs/ENTITLEMENTS.md calls out to avoid.
 */
export function PlanLimitIndicator({ label, used, limit }: Props) {
  return (
    <BlockStack gap="100">
      <InlineStack align="space-between">
        <Text as="span">{label}</Text>
        <Text as="span" tone="subdued">
          {limit === null ? `${formatPoints(used)} (unlimited)` : `${formatPoints(used)} / ${formatPoints(limit)}`}
        </Text>
      </InlineStack>
      <UsageProgressBar used={used} limit={limit} />
    </BlockStack>
  );
}
