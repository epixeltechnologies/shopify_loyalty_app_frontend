import { describe, it, expect } from 'vitest';
import { screen } from '@testing-library/react';
import { renderWithProviders } from '@/test/test-utils';
import { FeatureLockedState } from '@/components/common/FeatureLockedState';

describe('FeatureLockedState', () => {
  it('explains what the feature does, why it is unavailable, and which plan unlocks it', () => {
    renderWithProviders(
      <FeatureLockedState
        featureName="Advanced analytics"
        requiredPlan="Professional"
        description="See cohort retention and redemption trends over time."
      />,
    );

    expect(screen.getByText(/Advanced analytics is a Professional feature/i)).toBeInTheDocument();
    expect(screen.getByText('See cohort retention and redemption trends over time.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /View Professional plan/i })).toBeInTheDocument();
  });
});
