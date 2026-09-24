import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { CustomerListPage } from '@/pages/Customers/CustomerListPage';
import { customerService } from '@/services/customerService';

vi.mock('@/services/customerService', () => ({
  customerService: { list: vi.fn() },
}));

describe('CustomerListPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows an empty state when there are no customers', async () => {
    vi.mocked(customerService.list).mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 } });

    renderWithProviders(<CustomerListPage />);

    await waitFor(() => expect(screen.getByText('No customers yet')).toBeInTheDocument());
  });

  it('renders a list of customers once loaded', async () => {
    vi.mocked(customerService.list).mockResolvedValue({
      data: [
        {
          id: 1, shopify_customer_id: '123', email: 'ada@example.com', first_name: 'Ada', last_name: 'Lovelace',
          points_balance: 500, lifetime_points_earned: 1200, vip_tier: null, referral_code: 'ABC123', status: 'active', enrolled_at: '2026-01-01',
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 1, per_page: 25 },
    });

    renderWithProviders(<CustomerListPage />);

    await waitFor(() => expect(screen.getByText('Ada Lovelace')).toBeInTheDocument());
    expect(screen.getByText('500')).toBeInTheDocument();
  });

  it('shows an error-friendly state when the request fails', async () => {
    vi.mocked(customerService.list).mockRejectedValue(new Error('network error'));

    renderWithProviders(<CustomerListPage />);

    await waitFor(() => expect(screen.getByText("Couldn't load customers")).toBeInTheDocument());
  });
});
