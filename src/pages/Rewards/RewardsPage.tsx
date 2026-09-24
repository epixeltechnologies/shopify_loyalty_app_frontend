import { useState } from 'react';
import {
  Page, Card, Tabs, BlockStack, InlineStack, Text, DataTable, Badge, Button,
  Modal, FormLayout, TextField, Select,
} from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';
import { rewardService } from '@/services/rewardService';
import { useApi } from '@/hooks/useApi';
import { useEntitlements } from '@/hooks/useEntitlements';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { PlanLimitReached } from '@/components/common/PlanLimitReached';
import { useNotifications } from '@/hooks/useNotifications';
import { formatDate, formatPoints } from '@/utils/formatters';
import type { RewardType } from '@/types/domain';

const STATUS_TONE: Record<string, 'success' | 'critical' | undefined> = { active: 'success', archived: 'critical', draft: undefined };

export function RewardsPage() {
  const navigate = useNavigate();
  const [tab, setTab] = useState(0);
  const { notify } = useNotifications();
  const { limits, isLoading: entitlementsLoading, refetch: refetchEntitlements } = useEntitlements();

  const { data: rewards, isLoading: rewardsLoading, refetch } = useApi(() => rewardService.listRewards(), [tab]);
  const { data: redemptions, isLoading: redemptionsLoading } = useApi(() => rewardService.redemptionHistory(), [tab]);
  const { data: stats, isLoading: statsLoading } = useApi(rewardService.statistics, [tab]);

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [type, setType] = useState<RewardType>('percentage_discount');
  const [pointsCost, setPointsCost] = useState('500');
  const [value, setValue] = useState('10');
  const [saving, setSaving] = useState(false);

  const rewardLimit = limits?.active_rewards;
  const limitReached = rewardLimit && rewardLimit.limit !== null && rewardLimit.remaining === 0;

  async function createReward() {
    if (!name.trim()) {
      notify('Name is required.', 'critical');
      return;
    }

    setSaving(true);
    try {
      const rewardValue =
        type === 'fixed_discount' ? { amount_cents: Math.round(Number(value) * 100) }
        : type === 'percentage_discount' ? { percentage: Number(value) }
        : {};

      await rewardService.createReward({ name: name.trim(), type, points_cost: Number(pointsCost), value: rewardValue, status: 'active' });
      notify('Reward created.');
      setCreateOpen(false);
      setName('');
      refetch();
      refetchEntitlements();
    } catch {
      // handled globally by apiClient (including LIMIT_REACHED as a 402 toast)
    } finally {
      setSaving(false);
    }
  }

  const tabs = [
    { id: 'catalog', content: 'Rewards' },
    { id: 'history', content: 'Redemption history' },
    { id: 'stats', content: 'Statistics' },
  ];

  return (
    <Page title="Rewards" subtitle="What customers can redeem their points for">
      <Card padding="0">
        <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
        <div style={{ padding: 16 }}>
          {tab === 0 && (
            <BlockStack gap="400">
              <InlineStack align="space-between" blockAlign="center">
                {rewardLimit && rewardLimit.limit !== null && (
                  <Text as="span" tone="subdued">
                    {rewardLimit.used} / {rewardLimit.limit} active reward campaigns used
                  </Text>
                )}
                <Button variant="primary" disabled={!!limitReached} onClick={() => setCreateOpen(true)}>
                  Create reward
                </Button>
              </InlineStack>

              {limitReached && !entitlementsLoading && (
                <PlanLimitReached resourceName="reward campaigns" used={rewardLimit!.used} limit={rewardLimit!.limit!} requiredPlan="Professional" />
              )}

              {rewardsLoading ? <LoadingState lines={4} /> : !rewards || rewards.data.length === 0 ? (
                <EmptyState heading="No rewards yet" action={limitReached ? undefined : { content: 'Create reward', onAction: () => setCreateOpen(true) }}>
                  <p>Create a reward customers can redeem their points for — a discount, free shipping, or more.</p>
                </EmptyState>
              ) : (
                <DataTable
                  columnContentTypes={['text', 'text', 'numeric', 'text']}
                  headings={['Name', 'Type', 'Points cost', 'Status']}
                  rows={rewards.data.map((reward) => [
                    <Text as="span" key={reward.id}>
                      <a onClick={() => navigate(`/rewards/${reward.id}`)} style={{ cursor: 'pointer' }}>{reward.name}</a>
                    </Text>,
                    reward.type,
                    formatPoints(reward.points_cost),
                    <Badge key={`${reward.id}-status`} tone={STATUS_TONE[reward.status]}>{reward.status}</Badge>,
                  ])}
                />
              )}
            </BlockStack>
          )}

          {tab === 1 && (
            redemptionsLoading ? <LoadingState lines={4} /> : !redemptions || redemptions.data.length === 0 ? (
              <EmptyState heading="No redemptions yet"><p>When customers redeem rewards, they'll show up here.</p></EmptyState>
            ) : (
              <DataTable
                columnContentTypes={['text', 'text', 'numeric', 'text', 'text']}
                headings={['Customer', 'Reward', 'Points spent', 'Status', 'Date']}
                rows={redemptions.data.map((r) => [
                  r.customer ? [r.customer.first_name, r.customer.last_name].filter(Boolean).join(' ') || r.customer.email || '—' : '—',
                  r.reward.name,
                  formatPoints(r.points_spent),
                  r.status,
                  formatDate(r.created_at),
                ])}
              />
            )
          )}

          {tab === 2 && (
            statsLoading || !stats ? <LoadingState lines={4} /> : (
              <InlineStack gap="600" wrap>
                <BlockStack gap="100"><Text as="span" tone="subdued">Total redemptions</Text><Text as="span" variant="headingLg">{stats.total_redemptions}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Completed</Text><Text as="span" variant="headingLg">{stats.completed_count}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Failed</Text><Text as="span" variant="headingLg">{stats.failed_count}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Points redeemed</Text><Text as="span" variant="headingLg">{formatPoints(stats.total_points_redeemed)}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Customers who redeemed</Text><Text as="span" variant="headingLg">{stats.customers_who_redeemed}</Text></BlockStack>
              </InlineStack>
            )
          )}
        </div>
      </Card>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Create reward" primaryAction={{ content: 'Create', loading: saving, onAction: createReward }} secondaryActions={[{ content: 'Cancel', onAction: () => setCreateOpen(false) }]}>
        <Modal.Section>
          <FormLayout>
            <TextField label="Name" value={name} onChange={setName} autoComplete="off" />
            <Select
              label="Type"
              value={type}
              onChange={(v) => setType(v as RewardType)}
              options={[
                { value: 'percentage_discount', label: 'Percentage discount' },
                { value: 'fixed_discount', label: 'Fixed amount discount' },
                { value: 'free_shipping', label: 'Free shipping' },
              ]}
            />
            <TextField label="Points cost" type="number" value={pointsCost} onChange={setPointsCost} autoComplete="off" />
            {type !== 'free_shipping' && (
              <TextField
                label={type === 'fixed_discount' ? 'Discount amount ($)' : 'Discount percentage (%)'}
                type="number"
                value={value}
                onChange={setValue}
                autoComplete="off"
              />
            )}
          </FormLayout>
        </Modal.Section>
      </Modal>
    </Page>
  );
}
