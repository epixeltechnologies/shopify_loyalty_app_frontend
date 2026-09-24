import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LoyaltyWidget } from '@/storefront/components/LoyaltyWidget';
import { storefrontService } from '@/storefront/api/storefrontService';

vi.mock('@/storefront/api/storefrontService', () => ({
  storefrontService: {
    pointsBalance: vi.fn(() => Promise.resolve({ balance: 500, lifetime_earned: 800, lifetime_redeemed: 300, next_expiry_date: null })),
    eligibleRewards: vi.fn(() => Promise.resolve([])),
    vipCurrent: vi.fn(() => Promise.resolve(null)),
    vipProgress: vi.fn(() => Promise.resolve({ next_tier: null, message: 'Already at the top.' })),
    vipBenefits: vi.fn(() => Promise.resolve({})),
    referralLink: vi.fn(() => Promise.resolve({ referral_code: 'ABC123', referral_url: 'https://shop.example.com/?ref=ABC123' })),
    referralStatistics: vi.fn(() => Promise.resolve({ total_referrals: 3, successful_referrals: 2, pending_referrals: 1 })),
  },
}));

/**
 * The widget renders without any admin-dashboard dependency (no Polaris
 * AppProvider, no MemoryRouter) — deliberately: it's meant to be
 * embeddable in a Shopify theme, not the merchant admin app, so this
 * test intentionally uses plain @testing-library/react render() rather
 * than the admin app's renderWithProviders wrapper.
 */
describe('LoyaltyWidget', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows the points balance on the default (first panel) tab', async () => {
    render(<LoyaltyWidget panels={['points', 'rewards']} />);

    await waitFor(() => expect(screen.getByText('500')).toBeInTheDocument());
  });

  it('switches to the rewards panel and shows an empty state when there are none', async () => {
    render(<LoyaltyWidget panels={['points', 'rewards']} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole('tab', { name: 'Rewards' }));

    await waitFor(() => expect(screen.getByText('No rewards available to redeem right now.')).toBeInTheDocument());
  });

  it('only renders the panels explicitly requested', async () => {
    render(<LoyaltyWidget panels={['points']} />);

    expect(screen.queryByRole('tab', { name: 'VIP' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Refer a friend' })).not.toBeInTheDocument();
  });

  it('shows the referral link and stats on the referral panel', async () => {
    render(<LoyaltyWidget panels={['points', 'referral']} />);
    const user = userEvent.setup();

    await user.click(screen.getByRole('tab', { name: 'Refer a friend' }));

    await waitFor(() => expect(screen.getByText('https://shop.example.com/?ref=ABC123')).toBeInTheDocument());
    expect(storefrontService.referralStatistics).toHaveBeenCalled();
  });
});
