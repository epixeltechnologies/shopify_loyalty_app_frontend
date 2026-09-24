import { useEffect, useState } from 'react';
import { Page, Card, Tabs, BlockStack, InlineStack, Text, DataTable, Badge, FormLayout, TextField, Checkbox, Button } from '@shopify/polaris';
import { referralService } from '@/services/referralService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { useNotifications } from '@/hooks/useNotifications';
import { formatDate } from '@/utils/formatters';
import type { ReferralSettings } from '@/types/domain';

const STATUS_TONE: Record<string, 'success' | 'critical' | 'warning' | undefined> = {
  rewarded: 'success',
  qualified: 'success',
  rejected: 'critical',
  fraud_detected: 'critical',
  cancelled: 'critical',
};

export function ReferralsPage() {
  const [tab, setTab] = useState(0);
  const { notify } = useNotifications();

  const { data: stats, isLoading: statsLoading } = useApi(referralService.statistics, [tab]);
  const { data: settings, isLoading: settingsLoading, refetch: refetchSettings } = useApi(referralService.settings, [tab]);
  const { data: history, isLoading: historyLoading } = useApi(() => referralService.list(), [tab]);
  const { data: fraudQueue, isLoading: fraudLoading, refetch: refetchFraud } = useApi(referralService.fraudQueue, [tab]);

  const [form, setForm] = useState<Partial<ReferralSettings>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (settings) setForm(settings);
  }, [settings]);

  async function saveSettings() {
    setSaving(true);
    try {
      await referralService.updateSettings(form);
      notify('Referral settings saved.');
      refetchSettings();
    } catch {
      // handled globally
    } finally {
      setSaving(false);
    }
  }

  async function clearFraud(id: number) {
    await referralService.clearFraud(id);
    notify('Referral cleared — it will be rewarded on the next scheduled run.');
    refetchFraud();
  }

  async function confirmFraud(id: number) {
    await referralService.confirmFraud(id);
    notify('Referral confirmed as fraudulent and permanently blocked.');
    refetchFraud();
  }

  const tabs = [
    { id: 'overview', content: 'Overview' },
    { id: 'settings', content: 'Settings' },
    { id: 'history', content: 'History' },
    { id: 'fraud', content: 'Fraud review' },
  ];

  return (
    <Page title="Referrals" subtitle="Turn happy customers into new ones">
      <Card padding="0">
        <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
        <div style={{ padding: 16 }}>
          {tab === 0 && (
            statsLoading || !stats ? <LoadingState lines={4} /> : (
              <InlineStack gap="600" wrap>
                <BlockStack gap="100"><Text as="span" tone="subdued">Total referrals</Text><Text as="span" variant="headingLg">{stats.total_referrals}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Successful (rewarded)</Text><Text as="span" variant="headingLg">{stats.rewarded_count}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Conversion rate</Text><Text as="span" variant="headingLg">{Math.round(stats.registration_to_reward_rate * 100)}%</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Flagged for review</Text><Text as="span" variant="headingLg">{stats.flagged_count}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Unique referrers</Text><Text as="span" variant="headingLg">{stats.unique_referrers}</Text></BlockStack>
              </InlineStack>
            )
          )}

          {tab === 1 && (
            settingsLoading ? <LoadingState lines={5} /> : (
              <FormLayout>
                <Checkbox label="Enable referral program" checked={!!form.enabled} onChange={(v) => setForm((f) => ({ ...f, enabled: v }))} />
                <TextField
                  label="Referrer reward (points)"
                  type="number"
                  value={String(form.referrer_reward_points ?? '')}
                  onChange={(v) => setForm((f) => ({ ...f, referrer_reward_points: v ? Number(v) : null }))}
                  autoComplete="off"
                />
                <TextField
                  label="Referred customer reward (points)"
                  type="number"
                  value={String(form.referee_reward_points ?? '')}
                  onChange={(v) => setForm((f) => ({ ...f, referee_reward_points: v ? Number(v) : null }))}
                  autoComplete="off"
                />
                <TextField
                  label="Reward delay (days)"
                  helpText="Time to wait after a qualifying order before paying out, to allow for refunds and cancellations."
                  type="number"
                  value={String(form.reward_delay_days ?? 0)}
                  onChange={(v) => setForm((f) => ({ ...f, reward_delay_days: Number(v) }))}
                  autoComplete="off"
                />
                <TextField
                  label="Attribution window (days)"
                  helpText="How long a click stays credited before it expires."
                  type="number"
                  value={String(form.attribution_window_days ?? 30)}
                  onChange={(v) => setForm((f) => ({ ...f, attribution_window_days: Number(v) }))}
                  autoComplete="off"
                />
                <Checkbox
                  label="Require the referred customer's first purchase to qualify"
                  checked={!!form.require_first_purchase}
                  onChange={(v) => setForm((f) => ({ ...f, require_first_purchase: v }))}
                />
                <InlineStack align="end">
                  <Button variant="primary" loading={saving} onClick={saveSettings}>Save settings</Button>
                </InlineStack>
              </FormLayout>
            )
          )}

          {tab === 2 && (
            historyLoading ? <LoadingState lines={4} /> : !history || history.data.length === 0 ? (
              <EmptyState heading="No referrals yet"><p>Once customers start sharing their referral link, activity will show up here.</p></EmptyState>
            ) : (
              <DataTable
                columnContentTypes={['text', 'text', 'text', 'text']}
                headings={['Referrer', 'Referred customer', 'Status', 'Date']}
                rows={history.data.map((ref) => [
                  ref.referrer ? [ref.referrer.first_name, ref.referrer.last_name].filter(Boolean).join(' ') || ref.referrer.email || '—' : '—',
                  ref.referred ? [ref.referred.first_name, ref.referred.last_name].filter(Boolean).join(' ') || ref.referred.email || 'Not yet registered' : 'Not yet registered',
                  <Badge key={ref.id} tone={STATUS_TONE[ref.status]}>{ref.status}</Badge>,
                  formatDate(ref.created_at),
                ])}
              />
            )
          )}

          {tab === 3 && (
            fraudLoading ? <LoadingState lines={4} /> : !fraudQueue || fraudQueue.data.length === 0 ? (
              <EmptyState heading="Nothing flagged"><p>Referrals with suspicious signals (not a single one alone — always two or more) will appear here for review.</p></EmptyState>
            ) : (
              <BlockStack gap="300">
                {fraudQueue.data.map((ref) => (
                  <Card key={ref.id}>
                    <BlockStack gap="200">
                      <InlineStack align="space-between">
                        <Text as="span" fontWeight="semibold">
                          {ref.referrer ? [ref.referrer.first_name, ref.referrer.last_name].filter(Boolean).join(' ') : 'Unknown referrer'}
                          {' → '}
                          {ref.referred ? [ref.referred.first_name, ref.referred.last_name].filter(Boolean).join(' ') : 'Unknown'}
                        </Text>
                        <Badge tone="warning">flagged</Badge>
                      </InlineStack>
                      {ref.fraud_reasons && (
                        <Text as="p" tone="subdued">Signals: {ref.fraud_reasons.join(', ')}</Text>
                      )}
                      <InlineStack gap="200">
                        <Button onClick={() => clearFraud(ref.id)}>Clear — legitimate</Button>
                        <Button tone="critical" onClick={() => confirmFraud(ref.id)}>Confirm fraud</Button>
                      </InlineStack>
                    </BlockStack>
                  </Card>
                ))}
              </BlockStack>
            )
          )}
        </div>
      </Card>
    </Page>
  );
}
