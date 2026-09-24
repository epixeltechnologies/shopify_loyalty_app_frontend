import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { PointsPage } from '@/pages/Points/PointsPage';
import { pointsService } from '@/services/pointsService';

vi.mock('@/services/pointsService', () => ({
  pointsService: {
    statistics: vi.fn(() =>
      Promise.resolve({ total_points_earned: 5000, total_points_redeemed: 1200, total_points_expired: 300, total_points_adjusted: 0, customers_with_activity: 42, transaction_count: 300 }),
    ),
  },
}));
vi.mock('@/services/pointRuleService', () => ({
  pointRuleService: { list: vi.fn(() => Promise.resolve({ data: [] })) },
}));

describe('PointsPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows real points statistics on the overview tab', async () => {
    renderWithProviders(<PointsPage />);

    await waitFor(() => expect(screen.getByText('5,000')).toBeInTheDocument());
    expect(screen.getByText('1,200')).toBeInTheDocument();
    // outstanding = earned - redeemed - expired = 5000 - 1200 - 300 = 3500
    expect(screen.getByText('3,500')).toBeInTheDocument();
  });

  it('never fabricates data when the API call fails', async () => {
    vi.mocked(pointsService.statistics).mockRejectedValueOnce(new Error('network error'));

    renderWithProviders(<PointsPage />);

    await waitFor(() => expect(screen.queryByText('Loading…')).not.toBeInTheDocument());
    expect(screen.queryByText('5,000')).not.toBeInTheDocument();
  });
});
