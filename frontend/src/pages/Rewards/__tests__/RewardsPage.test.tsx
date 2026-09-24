import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { RewardsPage } from '@/pages/Rewards/RewardsPage';
import { entitlementService } from '@/services/entitlementService';

vi.mock('@/services/rewardService', () => ({
  rewardService: {
    listRewards: vi.fn(() => Promise.resolve({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 } })),
    redemptionHistory: vi.fn(() => Promise.resolve({ data: [] })),
    statistics: vi.fn(() => Promise.resolve({ total_redemptions: 0, completed_count: 0, pending_count: 0, failed_count: 0, cancelled_count: 0, total_points_redeemed: 0, customers_who_redeemed: 0 })),
  },
}));
vi.mock('@/services/entitlementService', () => ({
  entitlementService: { summary: vi.fn() },
}));

describe('RewardsPage — plan limits', () => {
  beforeEach(() => vi.clearAllMocks());

  it('disables reward creation and explains why when the plan limit is reached', async () => {
    vi.mocked(entitlementService.summary).mockResolvedValue({
      plan: 'starter',
      features: [],
      limits: {
        active_customers: { used: 10, limit: 500, remaining: 490 },
        active_reward_campaigns: { used: 5, limit: 5, remaining: 0 },
        active_rewards: { used: 5, limit: 5, remaining: 0 },
      },
    });

    renderWithProviders(<RewardsPage />);

    await waitFor(() => expect(screen.getByText(/reached your plan's limit/i)).toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Create reward' })).toHaveAttribute('aria-disabled', 'true');
  });

  it('allows reward creation when under the limit', async () => {
    vi.mocked(entitlementService.summary).mockResolvedValue({
      plan: 'starter',
      features: [],
      limits: {
        active_customers: { used: 10, limit: 500, remaining: 490 },
        active_reward_campaigns: { used: 2, limit: 5, remaining: 3 },
        active_rewards: { used: 2, limit: 5, remaining: 3 },
      },
    });

    renderWithProviders(<RewardsPage />);

    await waitFor(() => expect(screen.getByRole('button', { name: 'Create reward' })).not.toBeDisabled());
  });
});
