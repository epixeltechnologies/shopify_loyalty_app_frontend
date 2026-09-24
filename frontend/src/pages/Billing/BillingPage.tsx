import { Page, Layout, Card, BlockStack, Text, Button, InlineStack } from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';
import { billingService } from '@/services/billingService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { UsageCard } from '@/components/common/UsageCard';

/**
 * Billing overview — current plan, usage at a glance, and entry points
 * into /plans (compare/change) and /subscription (status/history
 * detail). See docs/BILLING.md, docs/ENTITLEMENTS.md, and
 * docs/FRONTEND_ARCHITECTURE.md.
 */
export function BillingPage() {
  const { data: status, isLoading: statusLoading } = useApi(billingService.status, []);
  const { data: usage, isLoading: usageLoading } = useApi(billingService.usage, []);
  const navigate = useNavigate();

  return (
    <Page
      title="Billing"
      subtitle="Your plan and usage at a glance"
      primaryAction={{ content: 'Compare plans', onAction: () => navigate('/plans') }}
    >
      <Layout>
        <Layout.Section>
          <Card>
            {statusLoading || !status ? (
              <LoadingState lines={3} />
            ) : (
              <BlockStack gap="300">
                <InlineStack align="space-between">
                  <Text as="h2" variant="headingMd">
                    {status.plan?.name ?? 'No active plan'}
                  </Text>
                  <Button onClick={() => navigate('/subscription')}>View subscription details</Button>
                </InlineStack>
                <Text as="p" tone="subdued">
                  {status.has_active_subscription
                    ? 'Your subscription is active.'
                    : 'A subscription is required to use this app — choose a plan to get started.'}
                </Text>
              </BlockStack>
            )}
          </Card>
        </Layout.Section>

        <Layout.Section>
          {usageLoading || !usage ? (
            <Card>
              <LoadingState lines={2} />
            </Card>
          ) : (
            <UsageCard
              metrics={[
                { label: 'Active customers', data: usage.active_customers },
                { label: 'Reward campaigns', data: usage.active_point_rules },
              ]}
              nearLimitPlan={status?.plan?.slug === 'starter' ? 'Professional' : null}
            />
          )}
        </Layout.Section>
      </Layout>
    </Page>
  );
}
