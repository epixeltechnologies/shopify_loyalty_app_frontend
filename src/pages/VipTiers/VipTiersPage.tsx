import { useState } from 'react';
import { Page, Card, Tabs, BlockStack, InlineStack, Text, DataTable, Badge, Button, Modal, FormLayout, TextField, Select, Checkbox } from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';
import { vipTierService, type VipSettings } from '@/services/vipTierService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { FeatureLockedState } from '@/components/common/FeatureLockedState';
import { useEntitlements } from '@/hooks/useEntitlements';
import { useNotifications } from '@/hooks/useNotifications';
import type { VipQualificationMethod } from '@/types/domain';

export function VipTiersPage() {
  const navigate = useNavigate();
  const [tab, setTab] = useState(0);
  const { notify } = useNotifications();
  const { hasFeature, isLoading: entitlementsLoading } = useEntitlements();

  const { data: tiers, isLoading: tiersLoading, refetch } = useApi(vipTierService.list, [tab]);
  const { data: stats, isLoading: statsLoading } = useApi(vipTierService.statistics, [tab]);

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState<'silver' | 'gold' | 'platinum'>('silver');
  const [method, setMethod] = useState<VipQualificationMethod>('points_earned');
  const [threshold, setThreshold] = useState('1000');
  const [saving, setSaving] = useState(false);

  const canCreatePlatinum = hasFeature('vip_tier.platinum');

  async function createTier() {
    if (!name.trim()) {
      notify('Name is required.', 'critical');
      return;
    }

    setSaving(true);
    try {
      await vipTierService.create({
        name: name.trim(),
        slug,
        qualification_method: method,
        threshold_points: method === 'points_earned' ? Number(threshold) : 0,
        minimum_spend_cents: method === 'total_spend' ? Math.round(Number(threshold) * 100) : undefined,
        minimum_orders: method === 'order_count' ? Number(threshold) : undefined,
        evaluation_period: 'lifetime',
        sort_order: slug === 'silver' ? 1 : slug === 'gold' ? 2 : 3,
        is_active: true,
      });
      notify('VIP tier created.');
      setCreateOpen(false);
      setName('');
      refetch();
    } catch {
      // handled globally (including the 403 when Platinum isn't on this plan)
    } finally {
      setSaving(false);
    }
  }

  async function evaluateNow() {
    await vipTierService.evaluateNow();
    notify('VIP tier evaluation has been queued — changes may take a few minutes to appear.');
  }

  const tabs = [{ id: 'overview', content: 'Overview' }, { id: 'tiers', content: 'Tiers' }, { id: 'settings', content: 'Settings' }];

  return (
    <Page title="VIP Tiers" subtitle="Reward your best customers with tier-based benefits" primaryAction={{ content: 'Evaluate now', onAction: evaluateNow }}>
      <Card padding="0">
        <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
        <div style={{ padding: 16 }}>
          {tab === 0 && (
            statsLoading || !stats ? <LoadingState lines={4} /> : (
              <BlockStack gap="400">
                <InlineStack gap="600" wrap>
                  <BlockStack gap="100"><Text as="span" tone="subdued">Upgrades (30 days)</Text><Text as="span" variant="headingLg">{stats.upgrades_last_30_days}</Text></BlockStack>
                  <BlockStack gap="100"><Text as="span" tone="subdued">Downgrades (30 days)</Text><Text as="span" variant="headingLg">{stats.downgrades_last_30_days}</Text></BlockStack>
                </InlineStack>
                <DataTable
                  columnContentTypes={['text', 'numeric']}
                  headings={['Tier', 'Customers']}
                  rows={stats.tiers.map((t) => [t.name, t.customer_count])}
                />
              </BlockStack>
            )
          )}

          {tab === 1 && (
            <BlockStack gap="400">
              <InlineStack align="end">
                <Button variant="primary" onClick={() => setCreateOpen(true)}>Create tier</Button>
              </InlineStack>
              {tiersLoading ? <LoadingState lines={4} /> : !tiers || tiers.length === 0 ? (
                <EmptyState heading="No VIP tiers yet" action={{ content: 'Create tier', onAction: () => setCreateOpen(true) }}>
                  <p>Silver and Gold are available on every plan; Platinum requires Professional.</p>
                </EmptyState>
              ) : (
                <DataTable
                  columnContentTypes={['text', 'text', 'numeric', 'text']}
                  headings={['Name', 'Qualification', 'Customers', 'Status']}
                  rows={tiers.map((tier) => [
                    <a key={tier.id} onClick={() => navigate(`/vip-tiers/${tier.id}`)} style={{ cursor: 'pointer' }}>{tier.name}</a>,
                    tier.qualification_method,
                    tier.customer_count ?? 0,
                    <Badge key={`${tier.id}-status`} tone={tier.is_active && tier.is_available_to_shop ? 'success' : 'critical'}>
                      {!tier.is_available_to_shop ? 'unavailable on plan' : tier.is_active ? 'active' : 'inactive'}
                    </Badge>,
                  ])}
                />
              )}
            </BlockStack>
          )}

          {tab === 2 && <VipSettingsTab />}
        </div>
      </Card>

      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="Create VIP tier" primaryAction={{ content: 'Create', loading: saving, onAction: createTier }} secondaryActions={[{ content: 'Cancel', onAction: () => setCreateOpen(false) }]}>
        <Modal.Section>
          {slug === 'platinum' && !canCreatePlatinum && !entitlementsLoading ? (
            <FeatureLockedState featureName="Platinum tier" requiredPlan="Professional" description="Platinum is available on the Professional plan." />
          ) : (
            <FormLayout>
              <TextField label="Name" value={name} onChange={setName} autoComplete="off" />
              <Select
                label="Slug"
                value={slug}
                onChange={(v) => setSlug(v as typeof slug)}
                options={[{ value: 'silver', label: 'Silver' }, { value: 'gold', label: 'Gold' }, { value: 'platinum', label: 'Platinum' }]}
              />
              <Select
                label="Qualification method"
                value={method}
                onChange={(v) => setMethod(v as VipQualificationMethod)}
                options={[
                  { value: 'points_earned', label: 'Points earned' },
                  { value: 'total_spend', label: 'Total spend' },
                  { value: 'order_count', label: 'Number of orders' },
                ]}
              />
              <TextField
                label={method === 'total_spend' ? 'Minimum spend ($)' : method === 'order_count' ? 'Minimum orders' : 'Minimum points earned'}
                type="number"
                value={threshold}
                onChange={setThreshold}
                autoComplete="off"
              />
            </FormLayout>
          )}
        </Modal.Section>
      </Modal>
    </Page>
  );
}

function VipSettingsTab() {
  const { notify } = useNotifications();
  const { data: settings, isLoading, refetch } = useApi(vipTierService.settings, []);
  const [form, setForm] = useState<Partial<VipSettings>>({});
  const [saving, setSaving] = useState(false);

  if (isLoading || !settings) return <LoadingState lines={4} />;

  const current = { ...settings, ...form };

  async function save() {
    setSaving(true);
    try {
      await vipTierService.updateSettings(form);
      notify('VIP settings saved.');
      refetch();
    } catch {
      // handled globally
    } finally {
      setSaving(false);
    }
  }

  return (
    <FormLayout>
      <Checkbox label="Enable the VIP program" checked={current.enabled} onChange={(v) => setForm((f) => ({ ...f, enabled: v }))} />
      <Checkbox label="Notify customers when they're upgraded" checked={current.notify_on_upgrade} onChange={(v) => setForm((f) => ({ ...f, notify_on_upgrade: v }))} />
      <Checkbox label="Notify customers when they're downgraded" checked={current.notify_on_downgrade} onChange={(v) => setForm((f) => ({ ...f, notify_on_downgrade: v }))} />
      <InlineStack align="end">
        <Button variant="primary" loading={saving} onClick={save}>Save</Button>
      </InlineStack>
    </FormLayout>
  );
}
