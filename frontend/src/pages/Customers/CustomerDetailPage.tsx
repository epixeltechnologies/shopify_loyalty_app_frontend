import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Page,
  Layout,
  Card,
  BlockStack,
  InlineStack,
  Text,
  Badge,
  Tabs,
  DataTable,
  TextField,
  Modal,
  FormLayout,
} from '@shopify/polaris';
import { customerService } from '@/services/customerService';
import { pointsService } from '@/services/pointsService';
import { rewardService } from '@/services/rewardService';
import { referralService } from '@/services/referralService';
import { vipTierService } from '@/services/vipTierService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { formatDate, formatPoints } from '@/utils/formatters';
import { useNotifications } from '@/hooks/useNotifications';

export function CustomerDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const customerId = Number(id);
  const { notify } = useNotifications();
  const [tab, setTab] = useState(0);
  const [adjustOpen, setAdjustOpen] = useState(false);
  const [adjustPoints, setAdjustPoints] = useState('');
  const [adjustNote, setAdjustNote] = useState('');
  const [adjusting, setAdjusting] = useState(false);

  const { data: customer, isLoading, refetch } = useApi(() => customerService.get(customerId), [customerId]);
  const { data: pointsHistory } = useApi(() => pointsService.history(customerId), [customerId, tab]);
  const { data: redemptions } = useApi(() => rewardService.customerRedemptionHistory(customerId), [customerId, tab]);
  const { data: referralStats } = useApi(() => referralService.customerStatistics(customerId), [customerId, tab]);
  const { data: vipProgress } = useApi(() => vipTierService.customerProgress(customerId), [customerId, tab]);

  async function submitAdjustment() {
    const points = Number(adjustPoints);
    if (!points || !adjustNote.trim()) {
      notify('Enter a non-zero amount and a reason.', 'critical');
      return;
    }

    setAdjusting(true);
    try {
      await pointsService.adjust(customerId, points, adjustNote.trim());
      notify('Points adjusted successfully.');
      setAdjustOpen(false);
      setAdjustPoints('');
      setAdjustNote('');
      refetch();
    } catch {
      // apiClient's interceptor already surfaces a toast for this
    } finally {
      setAdjusting(false);
    }
  }

  if (isLoading || !customer) {
    return (
      <Page title="Customer" backAction={{ onAction: () => navigate('/customers') }}>
        <LoadingState lines={6} />
      </Page>
    );
  }

  const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email || 'Unnamed customer';

  const tabs = [
    { id: 'overview', content: 'Overview' },
    { id: 'points', content: 'Points history' },
    { id: 'rewards', content: 'Rewards' },
    { id: 'referrals', content: 'Referrals' },
    { id: 'vip', content: 'VIP tier' },
  ];

  return (
    <Page
      title={name}
      subtitle={customer.email ?? undefined}
      backAction={{ onAction: () => navigate('/customers') }}
      titleMetadata={<Badge tone={customer.status === 'active' ? 'success' : 'critical'}>{customer.status}</Badge>}
      primaryAction={{ content: 'Adjust points', onAction: () => setAdjustOpen(true) }}
    >
      <Layout>
        <Layout.Section>
          <Card padding="0">
            <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
            <div style={{ padding: 16 }}>
              {tab === 0 && (
                <BlockStack gap="400">
                  <InlineStack gap="600">
                    <BlockStack gap="100">
                      <Text as="span" tone="subdued">Points balance</Text>
                      <Text as="span" variant="headingLg">{formatPoints(customer.points_balance)}</Text>
                    </BlockStack>
                    <BlockStack gap="100">
                      <Text as="span" tone="subdued">Lifetime earned</Text>
                      <Text as="span" variant="headingLg">{formatPoints(customer.lifetime_points_earned)}</Text>
                    </BlockStack>
                    <BlockStack gap="100">
                      <Text as="span" tone="subdued">Referral code</Text>
                      <Text as="span" variant="headingLg">{customer.referral_code}</Text>
                    </BlockStack>
                  </InlineStack>
                  <Text as="p" tone="subdued">Enrolled {formatDate(customer.enrolled_at)}</Text>
                </BlockStack>
              )}

              {tab === 1 && (
                pointsHistory && pointsHistory.data.length > 0 ? (
                  <DataTable
                    columnContentTypes={['text', 'numeric', 'numeric', 'text', 'text']}
                    headings={['Type', 'Points', 'Balance after', 'Source', 'Date']}
                    rows={pointsHistory.data.map((t) => [
                      t.direction,
                      (t.points > 0 ? '+' : '') + formatPoints(t.points),
                      formatPoints(t.balance_after),
                      t.source,
                      formatDate(t.created_at),
                    ])}
                  />
                ) : (
                  <EmptyState heading="No point activity yet"><p>Transactions will appear here once this customer starts earning or spending points.</p></EmptyState>
                )
              )}

              {tab === 2 && (
                redemptions && redemptions.data.length > 0 ? (
                  <DataTable
                    columnContentTypes={['text', 'numeric', 'text', 'text']}
                    headings={['Reward', 'Points spent', 'Status', 'Redeemed']}
                    rows={redemptions.data.map((r) => [r.reward.name, formatPoints(r.points_spent), r.status, formatDate(r.created_at)])}
                  />
                ) : (
                  <EmptyState heading="No redemptions yet"><p>Rewards this customer redeems will appear here.</p></EmptyState>
                )
              )}

              {tab === 3 && referralStats && (
                <InlineStack gap="600">
                  <BlockStack gap="100">
                    <Text as="span" tone="subdued">Total referrals</Text>
                    <Text as="span" variant="headingLg">{referralStats.total_referrals}</Text>
                  </BlockStack>
                  <BlockStack gap="100">
                    <Text as="span" tone="subdued">Successful</Text>
                    <Text as="span" variant="headingLg">{referralStats.successful_referrals}</Text>
                  </BlockStack>
                  <BlockStack gap="100">
                    <Text as="span" tone="subdued">Pending</Text>
                    <Text as="span" variant="headingLg">{referralStats.pending_referrals}</Text>
                  </BlockStack>
                </InlineStack>
              )}

              {tab === 4 && (
                <BlockStack gap="300">
                  {customer.vip_tier ? (
                    <Badge tone="info">{customer.vip_tier.name}</Badge>
                  ) : (
                    <Text as="p" tone="subdued">Not yet in a VIP tier.</Text>
                  )}
                  {vipProgress?.next_tier && (
                    <Text as="p" tone="subdued">
                      {vipProgress.remaining} more to reach {vipProgress.next_tier.name} ({vipProgress.percent_complete}% there)
                    </Text>
                  )}
                </BlockStack>
              )}
            </div>
          </Card>
        </Layout.Section>
      </Layout>

      <Modal open={adjustOpen} onClose={() => setAdjustOpen(false)} title="Adjust points" primaryAction={{ content: 'Save adjustment', loading: adjusting, onAction: submitAdjustment }} secondaryActions={[{ content: 'Cancel', onAction: () => setAdjustOpen(false) }]}>
        <Modal.Section>
          <FormLayout>
            <TextField
              label="Points (use a negative number to remove points)"
              type="number"
              value={adjustPoints}
              onChange={setAdjustPoints}
              autoComplete="off"
            />
            <TextField label="Reason" value={adjustNote} onChange={setAdjustNote} autoComplete="off" multiline={2} />
          </FormLayout>
        </Modal.Section>
      </Modal>
    </Page>
  );
}
