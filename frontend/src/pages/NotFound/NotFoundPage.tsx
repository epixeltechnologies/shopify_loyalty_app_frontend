import { Page, Layout, Card } from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';
import { EmptyState } from '@/components/common/EmptyState';

/** Catch-all for any path that doesn't match a defined route (see routes/AppRoutes.tsx's wildcard). */
export function NotFoundPage() {
  const navigate = useNavigate();

  return (
    <Page title="Page not found">
      <Layout>
        <Layout.Section>
          <Card>
            <EmptyState
              heading="We couldn't find that page"
              action={{ content: 'Back to dashboard', onAction: () => navigate('/') }}
            >
              <p>The page you're looking for doesn't exist or may have moved.</p>
            </EmptyState>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}
