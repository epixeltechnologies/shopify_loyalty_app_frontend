import { useMemo, useState } from 'react';
import {
  Page,
  Card,
  IndexTable,
  useIndexResourceState,
  Text,
  Badge,
  Filters,
  ChoiceList,
  Pagination,
  BlockStack,
  InlineStack,
} from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';
import { customerService } from '@/services/customerService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { EmptyState } from '@/components/common/EmptyState';
import { formatDate, formatPoints } from '@/utils/formatters';
import type { Customer } from '@/types/domain';

const STATUS_TONE: Record<Customer['status'], 'success' | 'critical'> = {
  active: 'success',
  suspended: 'critical',
};

export function CustomerListPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<string[]>([]);

  const { data, isLoading, error } = useApi(
    () => customerService.list({ page, search: search || undefined, status: status[0] as Customer['status'] | undefined }),
    [page, search, status],
  );

  const customers = data?.data ?? [];
  const resourceStateItems = useMemo(() => (data?.data ?? []).map((c) => ({ id: String(c.id) })), [data]);
  const { selectedResources, allResourcesSelected, handleSelectionChange } = useIndexResourceState(resourceStateItems);

  const appliedFilters = useMemo(
    () => (status.length ? [{ key: 'status', label: `Status: ${status[0]}`, onRemove: () => setStatus([]) }] : []),
    [status],
  );

  return (
    <Page title="Customers" subtitle="Everyone enrolled in your loyalty program">
      <BlockStack gap="400">
        <Card padding="0">
          <div style={{ padding: '16px 16px 0' }}>
            <Filters
              queryValue={search}
              queryPlaceholder="Search by name, email, or Shopify ID"
              onQueryChange={(value) => {
                setPage(1);
                setSearch(value);
              }}
              onQueryClear={() => setSearch('')}
              onClearAll={() => {
                setSearch('');
                setStatus([]);
              }}
              appliedFilters={appliedFilters}
              filters={[
                {
                  key: 'status',
                  label: 'Status',
                  filter: (
                    <ChoiceList
                      title="Status"
                      titleHidden
                      choices={[
                        { label: 'Active', value: 'active' },
                        { label: 'Suspended', value: 'suspended' },
                      ]}
                      selected={status}
                      onChange={(value) => {
                        setPage(1);
                        setStatus(value);
                      }}
                    />
                  ),
                },
              ]}
            />
          </div>

          {isLoading ? (
            <LoadingState lines={6} />
          ) : error ? (
            <EmptyState heading="Couldn't load customers">
              <p>Something went wrong fetching your customers. Try refreshing the page.</p>
            </EmptyState>
          ) : customers.length === 0 ? (
            <EmptyState heading="No customers yet">
              <p>Customers enrolled in your loyalty program will appear here.</p>
            </EmptyState>
          ) : (
            <>
              <IndexTable
                resourceName={{ singular: 'customer', plural: 'customers' }}
                itemCount={customers.length}
                selectedItemsCount={allResourcesSelected ? 'All' : selectedResources.length}
                onSelectionChange={handleSelectionChange}
                headings={[
                  { title: 'Customer' },
                  { title: 'Points balance' },
                  { title: 'Lifetime earned' },
                  { title: 'VIP tier' },
                  { title: 'Status' },
                  { title: 'Enrolled' },
                ]}
              >
                {customers.map((customer, index) => (
                  <IndexTable.Row
                    id={String(customer.id)}
                    key={customer.id}
                    position={index}
                    selected={selectedResources.includes(String(customer.id))}
                    onClick={() => navigate(`/customers/${customer.id}`)}
                  >
                    <IndexTable.Cell>
                      <Text as="span" fontWeight="semibold">
                        {[customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email || 'Unnamed customer'}
                      </Text>
                      <div>
                        <Text as="span" tone="subdued">
                          {customer.email ?? '—'}
                        </Text>
                      </div>
                    </IndexTable.Cell>
                    <IndexTable.Cell>{formatPoints(customer.points_balance)}</IndexTable.Cell>
                    <IndexTable.Cell>{formatPoints(customer.lifetime_points_earned)}</IndexTable.Cell>
                    <IndexTable.Cell>
                      {customer.vip_tier ? <Badge tone="info">{customer.vip_tier.name}</Badge> : '—'}
                    </IndexTable.Cell>
                    <IndexTable.Cell>
                      <Badge tone={STATUS_TONE[customer.status]}>{customer.status}</Badge>
                    </IndexTable.Cell>
                    <IndexTable.Cell>{formatDate(customer.enrolled_at)}</IndexTable.Cell>
                  </IndexTable.Row>
                ))}
              </IndexTable>
              {data?.meta && data.meta.last_page > 1 && (
                <div style={{ padding: 16 }}>
                  <InlineStack align="center">
                    <Pagination
                      hasPrevious={page > 1}
                      onPrevious={() => setPage((p) => p - 1)}
                      hasNext={page < data.meta!.last_page}
                      onNext={() => setPage((p) => p + 1)}
                    />
                  </InlineStack>
                </div>
              )}
            </>
          )}
        </Card>
      </BlockStack>
    </Page>
  );
}
