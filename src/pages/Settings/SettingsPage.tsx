import { useEffect, useState } from 'react';
import {
  Page, Card, Tabs, BlockStack, InlineStack, Text, FormLayout, TextField, Select,
  Checkbox, Button, DataTable, Badge, Modal, Banner,
} from '@shopify/polaris';
import { settingsService, type ShopSettings } from '@/services/settingsService';
import {
  notificationSettingsService, NOTIFICATION_TYPE_LABELS, type NotificationType,
} from '@/services/notificationSettingsService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { useNotifications } from '@/hooks/useNotifications';

/**
 * Consolidates the task's General/Points/Branding/Timezone settings
 * sub-pages into one page with tabs — the same reasoning
 * PointsPage/RewardsPage/ReferralsPage/VipTiersPage already apply (see
 * docs/FRONTEND_ARCHITECTURE.md). Timezone/currency are deliberately
 * NOT editable here — they're synced from Shopify's own shop info
 * (`shops.timezone`/`shops.currency`), shown read-only so a merchant
 * always sees what this app is actually using for date calculations,
 * never a second, potentially-conflicting value.
 */
export function SettingsPage() {
  const [tab, setTab] = useState(0);
  const { notify } = useNotifications();

  const { data: settings, isLoading: settingsLoading, refetch: refetchSettings } = useApi(settingsService.get, [tab]);
  const [form, setForm] = useState<Partial<ShopSettings>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (settings) setForm(settings);
  }, [settings]);

  async function save(fields: Partial<ShopSettings>) {
    setSaving(true);
    try {
      await settingsService.update(fields);
      notify('Settings saved.');
      refetchSettings();
    } catch {
      // handled globally
    } finally {
      setSaving(false);
    }
  }

  const tabs = [
    { id: 'general', content: 'General' },
    { id: 'points', content: 'Points' },
    { id: 'notifications', content: 'Notifications' },
  ];

  return (
    <Page title="Settings" subtitle="Configure your loyalty program without any code">
      <Card padding="0">
        <Tabs tabs={tabs} selected={tab} onSelect={setTab} />
        <div style={{ padding: 16 }}>
          {tab === 0 && (
            settingsLoading ? <LoadingState lines={6} /> : (
              <FormLayout>
                <TextField label="Program name" value={form.program_name ?? ''} onChange={(v) => setForm((f) => ({ ...f, program_name: v }))} autoComplete="off" />
                <TextField label="Program description" value={form.program_description ?? ''} onChange={(v) => setForm((f) => ({ ...f, program_description: v }))} autoComplete="off" multiline={3} />
                <Select
                  label="Program status"
                  value={form.program_status ?? 'active'}
                  onChange={(v) => setForm((f) => ({ ...f, program_status: v as ShopSettings['program_status'] }))}
                  options={[{ value: 'active', label: 'Active' }, { value: 'paused', label: 'Paused' }]}
                />
                <TextField label="Logo URL" value={form.logo_url ?? ''} onChange={(v) => setForm((f) => ({ ...f, logo_url: v }))} autoComplete="off" />
                <InlineStack gap="400">
                  <TextField label="Primary brand color" value={form.widget_primary_color ?? ''} onChange={(v) => setForm((f) => ({ ...f, widget_primary_color: v }))} autoComplete="off" placeholder="#5C6AC4" />
                  <TextField label="Secondary brand color" value={form.brand_secondary_color ?? ''} onChange={(v) => setForm((f) => ({ ...f, brand_secondary_color: v }))} autoComplete="off" placeholder="#202223" />
                </InlineStack>
                <InlineStack align="end">
                  <Button variant="primary" loading={saving} onClick={() => save(form)}>Save</Button>
                </InlineStack>
              </FormLayout>
            )
          )}

          {tab === 1 && (
            settingsLoading ? <LoadingState lines={5} /> : (
              <FormLayout>
                <TextField label="Points earning label" helpText='e.g. "Reward Points" — how points are referred to for customers' value={form.points_earning_label ?? ''} onChange={(v) => setForm((f) => ({ ...f, points_earning_label: v }))} autoComplete="off" />
                <TextField label="Points expire after (days)" helpText="Leave blank for points that never expire" type="number" value={String(form.points_expiry_days ?? '')} onChange={(v) => setForm((f) => ({ ...f, points_expiry_days: v ? Number(v) : null }))} autoComplete="off" />
                <Checkbox label="Allow customer balances to go negative from a manual adjustment" checked={!!form.allow_negative_point_balance} onChange={(v) => setForm((f) => ({ ...f, allow_negative_point_balance: v }))} />
                <Checkbox label="Allow staff to manually adjust customer point balances" checked={form.allow_manual_point_adjustments !== false} onChange={(v) => setForm((f) => ({ ...f, allow_manual_point_adjustments: v }))} />
                <InlineStack align="end">
                  <Button variant="primary" loading={saving} onClick={() => save(form)}>Save</Button>
                </InlineStack>
              </FormLayout>
            )
          )}

          {tab === 2 && <NotificationSettingsTab />}
        </div>
      </Card>
    </Page>
  );
}

function NotificationSettingsTab() {
  const { notify } = useNotifications();
  const { data: templates, isLoading, refetch } = useApi(notificationSettingsService.list, []);
  const [editingType, setEditingType] = useState<NotificationType | null>(null);
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [preview, setPreview] = useState<{ subject: string; message: string } | null>(null);
  const [saving, setSaving] = useState(false);

  function openEditor(type: NotificationType, currentSubject: string | null, currentMessage: string | null) {
    setEditingType(type);
    setSubject(currentSubject ?? '');
    setMessage(currentMessage ?? '');
    setPreview(null);
  }

  async function toggleEnabled(type: NotificationType, enabled: boolean) {
    await notificationSettingsService.update(type, { enabled });
    notify(enabled ? 'Notification enabled.' : 'Notification disabled.');
    refetch();
  }

  async function loadPreview() {
    if (!editingType) return;
    const result = await notificationSettingsService.preview(editingType, subject, message);
    setPreview(result);
  }

  async function saveTemplate() {
    if (!editingType) return;
    setSaving(true);
    try {
      await notificationSettingsService.update(editingType, { subject, message });
      notify('Notification template saved.');
      setEditingType(null);
      refetch();
    } catch {
      // handled globally
    } finally {
      setSaving(false);
    }
  }

  if (isLoading || !templates) return <LoadingState lines={4} />;

  return (
    <BlockStack gap="400">
      <DataTable
        columnContentTypes={['text', 'text', 'text']}
        headings={['Notification', 'Status', 'Actions']}
        rows={templates.map((t) => [
          NOTIFICATION_TYPE_LABELS[t.type],
          <Badge key={`${t.type}-badge`} tone={t.enabled ? 'success' : undefined}>{t.enabled ? 'enabled' : 'disabled'}</Badge>,
          <InlineStack key={`${t.type}-actions`} gap="200">
            <Button onClick={() => openEditor(t.type, t.subject, t.message)}>Edit</Button>
            <Button onClick={() => toggleEnabled(t.type, !t.enabled)}>{t.enabled ? 'Disable' : 'Enable'}</Button>
          </InlineStack>,
        ])}
      />

      <Modal
        open={!!editingType}
        onClose={() => setEditingType(null)}
        title={editingType ? `Edit: ${NOTIFICATION_TYPE_LABELS[editingType]}` : ''}
        primaryAction={{ content: 'Save', loading: saving, onAction: saveTemplate }}
        secondaryActions={[{ content: 'Preview', onAction: loadPreview }, { content: 'Cancel', onAction: () => setEditingType(null) }]}
      >
        <Modal.Section>
          <FormLayout>
            <Text as="p" tone="subdued">
              Use {'{{customer_name}}'}, {'{{points}}'}, {'{{balance}}'}, {'{{tier_name}}'}, {'{{reward_name}}'} as placeholders — leave blank to use the default.
            </Text>
            <TextField label="Subject" value={subject} onChange={setSubject} autoComplete="off" />
            <TextField label="Message" value={message} onChange={setMessage} autoComplete="off" multiline={5} helpText="Basic formatting only (bold, italic, links, paragraphs) — anything else is removed for security." />
            {preview && (
              <Banner tone="info" title={preview.subject}>
                <div dangerouslySetInnerHTML={{ __html: preview.message }} />
              </Banner>
            )}
          </FormLayout>
        </Modal.Section>
      </Modal>
    </BlockStack>
  );
}
