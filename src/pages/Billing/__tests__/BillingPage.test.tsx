import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { BillingPage } from '@/pages/Billing/BillingPage';
import { billingService } from '@/services/billingService';

vi.mock('@/services/billingService', () => ({
  billingService: { status: vi.fn(), usage: vi.fn() },
}));

describe('BillingPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('never displays a free plan option', async () => {
    vi.mocked(billingService.status).mockResolvedValue({
      has_active_subscription: true,
      subscription: { status: 'active', current_period_end: null },
      plan: { slug: 'professional', name: 'Professional', description: null, price_monthly: 99, currency: 'USD', limits: { max_active_customers: 5000, max_active_point_rules: null }, features: {} },
    });
    vi.mocked(billingService.usage).mockResolvedValue({
      active_customers: { used: 100, limit: 5000 },
      active_point_rules: { used: 3, limit: null },
    });

    renderWithProviders(<BillingPage />);

    await waitFor(() => expect(screen.getByText('Professional')).toBeInTheDocument());
    expect(screen.queryByText(/free plan/i)).not.toBeInTheDocument();
  });

  it('shows an inactive-subscription message when there is no active plan', async () => {
    vi.mocked(billingService.status).mockResolvedValue({ has_active_subscription: false, subscription: null, plan: null });
    vi.mocked(billingService.usage).mockResolvedValue({
      active_customers: { used: 0, limit: null },
      active_point_rules: { used: 0, limit: null },
    });

    renderWithProviders(<BillingPage />);

    await waitFor(() => expect(screen.getByText('No active plan')).toBeInTheDocument());
  });
});
