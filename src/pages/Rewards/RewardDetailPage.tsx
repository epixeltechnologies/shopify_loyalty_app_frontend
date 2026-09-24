import { useParams, useNavigate } from 'react-router-dom';
import { Page, Card, BlockStack, InlineStack, Text, Badge } from '@shopify/polaris';
import { rewardService } from '@/services/rewardService';
import { useApi } from '@/hooks/useApi';
import { LoadingState } from '@/components/common/LoadingState';
import { formatDate, formatPoints } from '@/utils/formatters';

export function RewardDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { data: reward, isLoading } = useApi(() => rewardService.getReward(Number(id)), [id]);

  if (isLoading || !reward) {
    return (
      <Page title="Reward" backAction={{ onAction: () => navigate('/rewards') }}>
        <LoadingState lines={5} />
      </Page>
    );
  }

  return (
    <Page
      title={reward.name}
      backAction={{ onAction: () => navigate('/rewards') }}
      titleMetadata={<Badge tone={reward.status === 'active' ? 'success' : undefined}>{reward.status}</Badge>}
    >
      <Card>
        <BlockStack gap="400">
          {reward.description && <Text as="p">{reward.description}</Text>}
          <InlineStack gap="600" wrap>
            <BlockStack gap="100"><Text as="span" tone="subdued">Type</Text><Text as="span">{reward.type}</Text></BlockStack>
            <BlockStack gap="100"><Text as="span" tone="subdued">Points cost</Text><Text as="span">{formatPoints(reward.points_cost)}</Text></BlockStack>
            {reward.stock_limit !== null && (
              <BlockStack gap="100"><Text as="span" tone="subdued">Stock limit</Text><Text as="span">{reward.stock_limit}</Text></BlockStack>
            )}
            {reward.max_redemptions_per_customer !== null && (
              <BlockStack gap="100"><Text as="span" tone="subdued">Max per customer</Text><Text as="span">{reward.max_redemptions_per_customer}</Text></BlockStack>
            )}
            {reward.starts_at && (
              <BlockStack gap="100"><Text as="span" tone="subdued">Starts</Text><Text as="span">{formatDate(reward.starts_at)}</Text></BlockStack>
            )}
            {reward.ends_at && (
              <BlockStack gap="100"><Text as="span" tone="subdued">Ends</Text><Text as="span">{formatDate(reward.ends_at)}</Text></BlockStack>
            )}
          </InlineStack>
        </BlockStack>
      </Card>
    </Page>
  );
}
