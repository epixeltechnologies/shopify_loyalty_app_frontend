import { useState } from 'react';
import { Page, Card, Tabs, BlockStack, InlineStack, Text, DataTable, Badge, Button, Modal, FormLayout, TextField, Select } from '@shopify/polaris';
import { pointsService } from '@/services/pointsService';
import { pointRuleService } from '@/services/pointRuleService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { useNotifications } from '@/hooks/useNotifications';
import { formatPoints } from '@/utils/formatters';
import type { PointRuleType } from '@/types/domain';

const RULE_TYPE_LABEL: Record<PointRuleType, string> = {
  points_per_dollar: 'Purchase points',
  signup_bonus: 'Account creation reward',
  referral_bonus: 'Referral bonus',
  birthday_bonus: 'Birthday reward',
  review_reward: 'Review reward',
  custom: 'Custom',
};

/**
 * Consolidates the task's many point sub-pages (Purchase points
 * settings, Account creation reward, Birthday reward, Review reward)
 * into ONE earning-rules list/form — every one of those is just a
 * `PointRule` row with a different `type`, so a single CRUD surface
 * covers all of them without duplicating near-identical forms per
 * type. See docs/POINTS_ENGINE.md.
 */
export function PointsPage() {
  const [tab, setTab] = useState(0);
  const { notify } = useNotifications();
  const { data: stats, isLoading: statsLoading } = useApi(pointsService.statistics, []);
  const { data: rules, isLoading: rulesLoading, refetch } = useApi(() => pointRuleService.list(), [tab]);

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [type, setType] = useState<PointRuleType>('points_per_dollar');
  const [pointsPerDollar, setPointsPerDollar] = useState('1');
  const [bonusPoints, setBonusPoints] = useState('100');
  const [saving, setSaving] = useState(false);

  async function createRule() {
    if (!name.trim()) {
      notify('Name is required.', 'critical');
      return;
    }

    setSaving(true);
    try {
      const config = type === 'points_per_dollar' ? { points_per_dollar: Number(pointsPerDollar) } : { bonus_points: Number(bonusPoints) };
      await pointRuleService.create({ name: name.trim(), type, config, status: 'active' });
      notify('Earning rule created.');
      setCreateOpen(false);
      setName('');
      refetch();
    } catch {
      // handled globally
    } finally {
      setSaving(false);
    }
  }

  const tabs = [{ id: 'overview', content: 'Overview' }, { id: 'rules', content: 'Earning rules' }];

  return (
    <Page title="Points" subtitle="How points are earned and spent across your program">
      <Card padding="0">
        <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
        <div style={{ padding: 16 }}>
          {tab === 0 && (
            statsLoading || !stats ? <LoadingState lines={4} /> : (
              <InlineStack gap="600" wrap>
                <BlockStack gap="100"><Text as="span" tone="subdued">Total earned</Text><Text as="span" variant="headingLg">{formatPoints(stats.total_points_earned)}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Total redeemed</Text><Text as="span" variant="headingLg">{formatPoints(stats.total_points_redeemed)}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Total expired</Text><Text as="span" variant="headingLg">{formatPoints(stats.total_points_expired)}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Outstanding</Text><Text as="span" variant="headingLg">{formatPoints(stats.total_points_earned - stats.total_points_redeemed - stats.total_points_expired)}</Text></BlockStack>
                <BlockStack gap="100"><Text as="span" tone="subdued">Customers with activity</Text><Text as="span" variant="headingLg">{formatPoints(stats.customers_with_activity)}</Text></BlockStack>
              </InlineStack>
            )
          )}

          {tab === 1 && (
            <BlockStack gap="400">
              <InlineStack align="end">
                <Button variant="primary" onClick={() => setCreateOpen(true)}>Create earning rule</Button>
              </InlineStack>
              {rulesLoading ? <LoadingState lines={4} /> : !rules || rules.data.length === 0 ? (
                <EmptyState heading="No earning rules yet" action={{ content: 'Create earning rule', onAction: () => setCreateOpen(true) }}>
                  <p>Earning rules decide how customers earn points — from purchases, signing up, birthdays, and more.</p>
                </EmptyState>
              ) : (
                <DataTable
                  columnContentTypes={['text', 'text', 'text']}
                  headings={['Name', 'Type', 'Status']}
                  rows={rules.data.map((rule) => [
                    rule.name,
                    RULE_TYPE_LABEL[rule.type] ?? rule.type,
                    <Badge key={rule.id} tone={rule.status === 'active' ? 'success' : undefined}>{rule.status}</Badge>,
                  ])}
                />
              )}
            </BlockStack>
          )}
        </div>
      </Card>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Create earning rule" primaryAction={{ content: 'Create', loading: saving, onAction: createRule }} secondaryActions={[{ content: 'Cancel', onAction: () => setCreateOpen(false) }]}>
        <Modal.Section>
          <FormLayout>
            <TextField label="Name" value={name} onChange={setName} autoComplete="off" />
            <Select
              label="Type"
              value={type}
              onChange={(v) => setType(v as PointRuleType)}
              options={Object.entries(RULE_TYPE_LABEL).map(([value, label]) => ({ value, label }))}
            />
            {type === 'points_per_dollar' ? (
              <TextField label="Points per dollar spent" type="number" value={pointsPerDollar} onChange={setPointsPerDollar} autoComplete="off" />
            ) : (
              <TextField label="Bonus points" type="number" value={bonusPoints} onChange={setBonusPoints} autoComplete="off" />
            )}
          </FormLayout>
        </Modal.Section>
      </Modal>
    </Page>
  );
}
