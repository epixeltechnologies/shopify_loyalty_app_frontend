import { Page, Layout, Card, BlockStack, Text, Badge, DataTable } from '@shopify/polaris';
import { billingService } from '@/services/billingService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { formatDate } from '@/utils/formatters';
import { useNavigate } from 'react-router-dom';

const STATUS_TONE: Record<string, 'success' | 'warning' | 'critical' | 'info'> = {
  active: 'success',
  trialing: 'success',
  pending: 'info',
  past_due: 'warning',
  frozen: 'warning',
  cancelled: 'critical',
  expired: 'critical',
  declined: 'critical',
};

/** Subscription-specific detail: status, billing period, and recent history — see docs/BILLING.md. */
export function SubscriptionPage() {
  const { data, isLoading } = useApi(billingService.subscription, []);
  const navigate = useNavigate();

  if (isLoading || !data) {
    return (
      <Page title="Subscription">
        <Layout>
          <Layout.Section>
            <LoadingState lines={5} />
          </Layout.Section>
        </Layout>
      </Page>
    );
  }

  if (!data.status) {
    return (
      <Page title="Subscription">
        <Layout>
          <Layout.Section>
            <Card>
              <EmptyState heading="No subscription yet" action={{ content: 'Choose a plan', onAction: () => navigate('/plans') }}>
                <p>Choose a plan to get started — this app has no free tier.</p>
              </EmptyState>
            </Card>
          </Layout.Section>
        </Layout>
      </Page>
    );
  }

  return (
    <Page title="Subscription" subtitle="Your current billing status">
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Text as="h2" variant="headingMd">
                  {data.plan?.name ?? 'Unknown plan'}
                </Text>
                <Badge tone={STATUS_TONE[data.status] ?? 'info'}>{data.status}</Badge>
              </div>

              {data.current_period_start && data.current_period_end && (
                <Text as="p" tone="subdued">
                  Current period: {formatDate(data.current_period_start)} – {formatDate(data.current_period_end)}
                </Text>
              )}
              {data.trial_ends_at && <Text as="p" tone="subdued">Trial ends {formatDate(data.trial_ends_at)}</Text>}
              {data.cancelled_at && <Text as="p" tone="critical">Cancelled on {formatDate(data.cancelled_at)}</Text>}

              <Text as="p">
                To change or cancel your plan, visit{' '}
                <Text as="span" fontWeight="semibold">
                  Settings → Billing
                </Text>{' '}
                in your Shopify admin, or choose a different plan below.
              </Text>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h3" variant="headingSm">
                Recent activity
              </Text>
              {data.recent_events.length === 0 ? (
                <Text as="p" tone="subdued">
                  No subscription changes yet.
                </Text>
              ) : (
                <DataTable
                  columnContentTypes={['text', 'text', 'text', 'text']}
                  headings={['Date', 'Change', 'Plan', 'Triggered by']}
                  rows={data.recent_events.map((event) => [
                    formatDate(event.occurred_at),
                    `${event.from_status ?? '—'} → ${event.to_status}`,
                    event.to_plan ?? '—',
                    event.trigger,
                  ])}
                />
              )}
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
