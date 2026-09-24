import { Badge } from '@shopify/polaris';

interface Props {
  plan: string | null;
}

/** Small, consistent "which plan is this" badge — used in the nav/top bar and on Billing/Plans pages so the current plan is always visible at a glance. */
export function PlanBadge({ plan }: Props) {
  if (!plan) {
    return <Badge tone="critical">No active plan</Badge>;
  }

  const tone = plan.toLowerCase() === 'professional' ? 'success' : 'info';

  return (
    <Badge tone={tone}>{plan.charAt(0).toUpperCase() + plan.slice(1)}</Badge>
  );
}
