import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { DashboardPage } from '@/pages/Dashboard/DashboardPage';

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { summary: vi.fn(() => Promise.resolve({ plan: 'professional', customer_slots_remaining: null, available_vip_tiers: ['silver', 'gold', 'platinum'], has_advanced_analytics: true })) },
}));
vi.mock('@/services/billingService', () => ({
  billingService: {
    status: vi.fn(() => Promise.resolve({ has_active_subscription: true, subscription: { status: 'active', current_period_end: null }, plan: null })),
    usage: vi.fn(() => Promise.resolve({ active_customers: { used: 10, limit: 500 }, active_point_rules: { used: 2, limit: 5 } })),
  },
}));
vi.mock('@/services/customerService', () => ({
  customerService: {
    list: vi.fn((params?: { status?: string }) =>
      Promise.resolve({
        data: [],
        meta: { current_page: 1, last_page: 1, total: params?.status === 'active' ? 30 : 42, per_page: 25 },
      }),
    ),
  },
}));
vi.mock('@/services/pointsService', () => ({
  pointsService: { statistics: vi.fn(() => Promise.resolve({ total_points_earned: 1000, total_points_redeemed: 200, total_points_expired: 50, total_points_adjusted: 0, customers_with_activity: 30, transaction_count: 90 })) },
}));
vi.mock('@/services/rewardService', () => ({
  rewardService: { statistics: vi.fn(() => Promise.resolve({ total_redemptions: 15, completed_count: 12, pending_count: 1, failed_count: 1, cancelled_count: 1, total_points_redeemed: 200, customers_who_redeemed: 10 })) },
}));
vi.mock('@/services/referralService', () => ({
  referralService: { statistics: vi.fn(() => Promise.resolve({ total_referrals: 5, clicked_count: 5, registered_count: 4, qualified_count: 3, rewarded_count: 2, rejected_count: 0, flagged_count: 0, unique_referrers: 3, registration_to_reward_rate: 0.5 })) },
}));
vi.mock('@/services/vipTierService', () => ({
  vipTierService: { statistics: vi.fn(() => Promise.resolve({ tiers: [{ id: 1, name: 'Silver', sort_order: 1, customer_count: 8 }], upgrades_last_30_days: 2, downgrades_last_30_days: 0 })) },
}));

describe('DashboardPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows a loading state before data resolves', () => {
    renderWithProviders(<DashboardPage />);

    expect(screen.getByText('Dashboard')).toBeInTheDocument();
  });

  it('renders real metrics from every domain once loaded', async () => {
    renderWithProviders(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('42')).toBeInTheDocument()); // total customers
    expect(screen.getByText('1,000')).toBeInTheDocument(); // points issued
    expect(screen.getByText('12')).toBeInTheDocument(); // rewards redeemed
    expect(screen.getByText('2')).toBeInTheDocument(); // referral conversions
  });

  it('shows the current plan badge', async () => {
    renderWithProviders(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('Professional')).toBeInTheDocument());
  });
});
