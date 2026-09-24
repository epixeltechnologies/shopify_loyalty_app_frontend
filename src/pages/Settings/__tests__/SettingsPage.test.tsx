import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { SettingsPage } from '@/pages/Settings/SettingsPage';
import { settingsService } from '@/services/settingsService';

vi.mock('@/services/settingsService', () => ({
  settingsService: {
    get: vi.fn(() => Promise.resolve({ program_name: 'Acme Rewards', program_status: 'active', widget_primary_color: '#5C6AC4' })),
    update: vi.fn((payload) => Promise.resolve(payload)),
  },
}));

// The Notifications tab's own template list/toggle/preview behavior is
// covered thoroughly at the backend (NotificationSettingsApiTest,
// NotificationServiceTest, NotificationTemplateServiceTest) and via
// notificationSettingsService's typed contract here. Deep interactive
// testing of switching INTO that tab hits the same Polaris Tabs/jsdom
// limitation documented in docs/FRONTEND_ARCHITECTURE.md (Tabs
// measures available width to decide how many tabs fit before
// collapsing into a "More views" overflow control; jsdom's zero-width
// layout makes this unreliable regardless of how the click is
// dispatched) — not chased a second time for the same reason it wasn't
// the first, per that doc's testing section.
describe('SettingsPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('loads and displays the general settings form', async () => {
    renderWithProviders(<SettingsPage />);

    await waitFor(() => expect(screen.getByDisplayValue('Acme Rewards')).toBeInTheDocument());
  });

  it('saves general settings changes', async () => {
    renderWithProviders(<SettingsPage />);
    await waitFor(() => expect(screen.getByDisplayValue('Acme Rewards')).toBeInTheDocument());

    screen.getByRole('button', { name: 'Save' }).click();

    await waitFor(() => expect(vi.mocked(settingsService.update)).toHaveBeenCalled());
  });
});
