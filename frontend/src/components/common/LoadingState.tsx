import { Card, SkeletonBodyText, SkeletonDisplayText, BlockStack } from '@shopify/polaris';

/** Standard loading placeholder used by pages while useApi's `isLoading` is true. */
export function LoadingState({ lines = 4 }: { lines?: number }) {
  return (
    <Card>
      <BlockStack gap="300">
        <SkeletonDisplayText size="small" />
        <SkeletonBodyText lines={lines} />
      </BlockStack>
    </Card>
  );
}
