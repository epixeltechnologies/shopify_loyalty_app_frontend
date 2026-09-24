import { Page, Layout, Card, BlockStack, InlineStack, Text } from '@shopify/polaris';
import { dashboardService } from '@/services/dashboardService';
import { billingService } from '@/services/billingService';
import { customerService } from '@/services/customerService';
import { pointsService } from '@/services/pointsService';
import { rewardService } from '@/services/rewardService';
import { referralService } from '@/services/referralService';
import { vipTierService } from '@/services/vipTierService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { PlanLimitIndicator } from '@/components/common/PlanLimitIndicator';
import { PlanBadge } from '@/components/common/PlanBadge';
import { formatPoints } from '@/utils/formatters';

/**
 * Every figure here comes from a real, existing all-time aggregate
 * endpoint (points/rewards/referrals statistics, billing usage,
 * customer counts) — no mock data. A per-range (Today/7/30/90/custom)
 * breakdown of these same figures would need new date-scoped backend
 * endpoints that don't exist yet; rather than build a date-range
 * control that silently does nothing (or worse, fakes filtering
 * client-side over already-aggregated totals), this page presents
 * accurate all-time totals and leaves date-range dashboard filtering
 * as a documented follow-up — see docs/FRONTEND_ARCHITECTURE.md.
 */
export function DashboardPage() {
  const { data: summary, isLoading: summaryLoading } = useApi(dashboardService.summary, []);
  const { data: subscription, isLoading: subLoading } = useApi(billingService.status, []);
  const { data: usage, isLoading: usageLoading } = useApi(billingService.usage, []);
  const { data: allCustomers, isLoading: customersLoading } = useApi(() => customerService.list({ page: 1 }), []);
  const { data: activeCustomers } = useApi(() => customerService.list({ page: 1, status: 'active' }), []);
  const { data: pointStats, isLoading: pointsLoading } = useApi(pointsService.statistics, []);
  const { data: rewardStats, isLoading: rewardsLoading } = useApi(rewardService.statistics, []);
  const { data: referralStats, isLoading: referralsLoading } = useApi(referralService.statistics, []);
  const { data: vipStats, isLoading: vipLoading } = useApi(vipTierService.statistics, []);

  const outstandingPoints = pointStats
    ? pointStats.total_points_earned - pointStats.total_points_redeemed - pointStats.total_points_expired
    : null;
  const vipCustomerCount = vipStats ? vipStats.tiers.reduce((sum, t) => sum + t.customer_count, 0) : null;

  const isLoading = summaryLoading || subLoading || usageLoading || customersLoading || pointsLoading || rewardsLoading || referralsLoading || vipLoading;

  return (
    <Page title="Dashboard" subtitle="Your loyalty program at a glance">
      <Layout>
        <Layout.Section>
          {isLoading ? (
            <LoadingState lines={6} />
          ) : (
            <BlockStack gap="400">
              <Card>
                <InlineStack align="space-between" blockAlign="center">
                  <Text as="h2" variant="headingMd">Subscription</Text>
                  <PlanBadge plan={summary?.plan ?? null} />
                </InlineStack>
                <Text as="p" tone="subdued">
                  {subscription?.has_active_subscription ? `Status: ${subscription.subscription?.status}` : 'No active subscription'}
                </Text>
              </Card>

              <Card>
                <BlockStack gap="300">
                  <Text as="h2" variant="headingMd">Customers</Text>
                  <InlineStack gap="600" wrap>
                    <Metric label="Total customers" value={allCustomers?.meta?.total ?? 0} />
                    <Metric label="Active loyalty members" value={activeCustomers?.meta?.total ?? 0} />
                    <Metric label="VIP customers" value={vipCustomerCount ?? 0} />
                  </InlineStack>
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="300">
                  <Text as="h2" variant="headingMd">Points</Text>
                  <InlineStack gap="600" wrap>
                    <Metric label="Points issued" value={pointStats?.total_points_earned ?? 0} />
                    <Metric label="Points redeemed" value={pointStats?.total_points_redeemed ?? 0} />
                    <Metric label="Points outstanding" value={outstandingPoints ?? 0} />
                  </InlineStack>
                </BlockStack>
              </Card>

              <Card>
                <BlockStack gap="300">
                  <Text as="h2" variant="headingMd">Rewards &amp; referrals</Text>
                  <InlineStack gap="600" wrap>
                    <Metric label="Rewards redeemed" value={rewardStats?.completed_count ?? 0} />
                    <Metric label="Referral conversions" value={referralStats?.rewarded_count ?? 0} />
                  </InlineStack>
                </BlockStack>
              </Card>

              {usage && (
                <Card>
                  <BlockStack gap="300">
                    <Text as="h2" variant="headingMd">Usage limits</Text>
                    <PlanLimitIndicator label="Active customers" used={usage.active_customers.used} limit={usage.active_customers.limit} />
                    <PlanLimitIndicator label="Active point rules" used={usage.active_point_rules.used} limit={usage.active_point_rules.limit} />
                  </BlockStack>
                </Card>
              )}
            </BlockStack>
          )}
        </Layout.Section>
      </Layout>
    </Page>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <BlockStack gap="100">
      <Text as="span" tone="subdued">{label}</Text>
      <Text as="span" variant="headingLg">{formatPoints(value)}</Text>
    </BlockStack>
  );
}
