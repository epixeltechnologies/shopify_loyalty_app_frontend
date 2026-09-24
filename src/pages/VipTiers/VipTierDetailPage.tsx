import { useParams, useNavigate } from 'react-router-dom';
import { Page, Card, BlockStack, InlineStack, Text, Badge, DataTable } from '@shopify/polaris';
import { vipTierService } from '@/services/vipTierService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';

export function VipTierDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const tierId = Number(id);

  const { data: tier, isLoading } = useApi(() => vipTierService.get(tierId), [tierId]);
  const { data: customers, isLoading: customersLoading } = useApi(() => vipTierService.customersInTier(tierId), [tierId]);

  if (isLoading || !tier) {
    return (
      <Page title="VIP tier" backAction={{ onAction: () => navigate('/vip-tiers') }}>
        <LoadingState lines={5} />
      </Page>
    );
  }

  return (
    <Page
      title={tier.name}
      backAction={{ onAction: () => navigate('/vip-tiers') }}
      titleMetadata={<Badge tone={tier.is_active ? 'success' : 'critical'}>{tier.is_active ? 'active' : 'inactive'}</Badge>}
    >
      <BlockStack gap="400">
        <Card>
          <BlockStack gap="300">
            {tier.description && <Text as="p">{tier.description}</Text>}
            <InlineStack gap="600" wrap>
              <BlockStack gap="100"><Text as="span" tone="subdued">Qualification method</Text><Text as="span">{tier.qualification_method}</Text></BlockStack>
              <BlockStack gap="100"><Text as="span" tone="subdued">Evaluation period</Text><Text as="span">{tier.evaluation_period}</Text></BlockStack>
              <BlockStack gap="100"><Text as="span" tone="subdued">Customers</Text><Text as="span">{tier.customer_count ?? 0}</Text></BlockStack>
            </InlineStack>
            {tier.perks && Object.keys(tier.perks).length > 0 && (
              <BlockStack gap="100">
                <Text as="span" fontWeight="semibold">Benefits</Text>
                {Object.entries(tier.perks).map(([key, value]) => (
                  <Text as="p" key={key} tone="subdued">{key}: {String(value)}</Text>
                ))}
              </BlockStack>
            )}
          </BlockStack>
        </Card>

        <Card padding="0">
          <div style={{ padding: 16 }}>
            <Text as="h3" variant="headingSm">Customers in this tier</Text>
          </div>
          {customersLoading ? <LoadingState lines={3} /> : !customers || customers.data.length === 0 ? (
            <EmptyState heading="No customers in this tier yet"><p>Customers who qualify will appear here after the next evaluation.</p></EmptyState>
          ) : (
            <DataTable
              columnContentTypes={['text', 'text']}
              headings={['Customer', 'Email']}
              rows={customers.data.map((c) => [[c.first_name, c.last_name].filter(Boolean).join(' ') || '—', c.email ?? '—'])}
            />
          )}
        </Card>
      </BlockStack>
    </Page>
  );
}
