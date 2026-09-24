import type { ReactElement, ReactNode } from 'react';
import { render } from '@testing-library/react';
import { AppProvider } from '@shopify/polaris';
import { MemoryRouter } from 'react-router-dom';
import enTranslations from '@shopify/polaris/locales/en.json';

/** Every Polaris component needs AppProvider's i18n context; every page using useNavigate/useParams needs a Router. Shared here so individual test files don't repeat this boilerplate. */
function AllProviders({ children }: { children: ReactNode }) {
  return (
    <AppProvider i18n={enTranslations}>
      <MemoryRouter>{children}</MemoryRouter>
    </AppProvider>
  );
}

export function renderWithProviders(ui: ReactElement) {
  return render(ui, { wrapper: AllProviders });
}

export * from '@testing-library/react';
