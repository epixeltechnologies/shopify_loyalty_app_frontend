import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { ReferralsPage } from '@/pages/Referrals/ReferralsPage';

vi.mock('@/services/referralService', () => ({
  referralService: {
    statistics: vi.fn(() => Promise.resolve({ total_referrals: 10, clicked_count: 10, registered_count: 8, qualified_count: 6, rewarded_count: 5, rejected_count: 1, flagged_count: 2, unique_referrers: 4, registration_to_reward_rate: 0.625 })),
    settings: vi.fn(() => Promise.resolve({ enabled: true, referrer_reward_points: 500, referee_reward_points: 250, minimum_qualifying_order_cents: null, require_first_purchase: true, reward_delay_days: 3, attribution_window_days: 30, max_referrals_per_customer: null })),
    list: vi.fn(() => Promise.resolve({ data: [] })),
    fraudQueue: vi.fn(() => Promise.resolve({ data: [] })),
  },
}));

describe('ReferralsPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows conversion statistics on the overview tab', async () => {
    renderWithProviders(<ReferralsPage />);

    await waitFor(() => expect(screen.getByText('63%')).toBeInTheDocument());
    expect(screen.getByText('5')).toBeInTheDocument(); // rewarded_count
  });
});
