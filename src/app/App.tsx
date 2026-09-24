import { BrowserRouter } from 'react-router-dom';
import { AppProvider as PolarisProvider } from '@shopify/polaris';
import polarisEnTranslations from '@shopify/polaris/locales/en.json';
import { ShopifyBridgeProvider } from '@/contexts/ShopifyBridgeProvider';
import { ProtectedRoute } from '@/routes/ProtectedRoute';
import { AppRoutes } from '@/routes/AppRoutes';
import { AdminLayout } from '@/layouts/AdminLayout';
import { ErrorBoundary } from '@/components/common/ErrorBoundary';

/**
 * Root composition: ErrorBoundary (render-error safety net) -> Polaris
 * design system -> App Bridge session context -> ProtectedRoute
 * (blocks the app until an active plan exists, per this product's
 * no-free-plan model) -> routed page shell inside the admin layout.
 */
export function App() {
  return (
    <ErrorBoundary>
      <PolarisProvider i18n={polarisEnTranslations}>
        <ShopifyBridgeProvider>
          <BrowserRouter>
            <ProtectedRoute>
              <AdminLayout>
                <AppRoutes />
              </AdminLayout>
            </ProtectedRoute>
          </BrowserRouter>
        </ShopifyBridgeProvider>
      </PolarisProvider>
    </ErrorBoundary>
  );
}
