import { useMemo, useState } from 'react';
import { Page, Card, BlockStack, InlineStack, Select, DataTable } from '@shopify/polaris';
import { analyticsService } from '@/services/analyticsService';
import { useApi } from '@/hooks/useApi';
import { useEntitlements } from '@/hooks/useEntitlements';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { FeatureLockedState } from '@/components/common/FeatureLockedState';
import { formatDate, formatPoints } from '@/utils/formatters';

const METRICS = [
  { value: 'points_issued', label: 'Points issued' },
  { value: 'points_redeemed', label: 'Points redeemed' },
  { value: 'active_members', label: 'Active members' },
  { value: 'referrals_completed', label: 'Referrals completed' },
];

const RANGES = [
  { value: 'today', label: 'Today' },
  { value: '7', label: 'Last 7 days' },
  { value: '30', label: 'Last 30 days' },
  { value: '90', label: 'Last 90 days' },
];

/**
 * The one dashboard-style page in this app backed by a genuinely
 * date-scoped backend endpoint (`analytics_snapshots` via
 * AnalyticsService::series()) — see DashboardPage's docblock for why
 * the main Dashboard's totals are all-time rather than range-filtered:
 * those other domains have no date-scoped aggregate endpoint yet, and
 * a control that doesn't actually filter anything would be worse than
 * no control. This page's range selector is real.
 */
export function AnalyticsPage() {
  const { hasFeature, isLoading: entitlementsLoading } = useEntitlements();
  const [metric, setMetric] = useState('points_issued');
  const [range, setRange] = useState('30');

  const { from, to } = useMemo(() => {
    const to = new Date();
    const from = new Date();
    if (range === 'today') {
      from.setHours(0, 0, 0, 0);
    } else {
      from.setDate(from.getDate() - Number(range));
    }

    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) };
  }, [range]);

  const { data, isLoading } = useApi(() => analyticsService.series(metric, from, to), [metric, from, to]);

  if (!entitlementsLoading && !hasFeature('analytics.basic')) {
    return (
      <Page title="Analytics">
        <Card>
          <FeatureLockedState
            featureName="Analytics"
            requiredPlan="Starter"
            description="Analytics is included on every plan — this usually means your subscription isn't active yet."
          />
        </Card>
      </Page>
    );
  }

  return (
    <Page title="Analytics" subtitle="Trends across your loyalty program">
      <Card>
        <BlockStack gap="400">
          <InlineStack gap="400">
            <div style={{ minWidth: 220 }}>
              <Select label="Metric" options={METRICS} value={metric} onChange={setMetric} />
            </div>
            <div style={{ minWidth: 200 }}>
              <Select label="Date range" options={RANGES} value={range} onChange={setRange} />
            </div>
          </InlineStack>

          {isLoading ? (
            <LoadingState lines={4} />
          ) : !data || data.length === 0 ? (
            <EmptyState heading="No data for this range"><p>Try a wider date range, or check back once your program has more activity.</p></EmptyState>
          ) : (
            <DataTable
              columnContentTypes={['text', 'numeric']}
              headings={['Date', 'Value']}
              rows={data.map((point) => [formatDate(point.date), formatPoints(point.value)])}
            />
          )}
        </BlockStack>
      </Card>
    </Page>
  );
}
