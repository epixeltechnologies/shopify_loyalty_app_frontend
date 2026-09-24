import { useState } from 'react';
import './LoyaltyWidget.css';
import { PointsView } from './views/PointsView';
import { RewardsView } from './views/RewardsView';
import { VipView } from './views/VipView';
import { ReferralView } from './views/ReferralView';

type WidgetTab = 'points' | 'rewards' | 'vip' | 'referral';

interface LoyaltyWidgetProps {
  /** Which panels to show and in what order — a merchant/theme can drop any of these (e.g. hide VIP entirely if their plan doesn't include it). Defaults to all four. */
  panels?: WidgetTab[];
  /** Extra class applied to the root element, for a theme's own layout/positioning needs — never required for the widget to render correctly on its own. */
  className?: string;
}

const TAB_LABELS: Record<WidgetTab, string> = {
  points: 'Points',
  rewards: 'Rewards',
  vip: 'VIP',
  referral: 'Refer a friend',
};

/**
 * The reusable loyalty widget shell — the task's "Storefront Component"
 * requirement. Deliberately just a tab switcher over four independent
 * views (PointsView/RewardsView/VipView/ReferralView), each fetching
 * its own data via `storefrontService` (never props-drilled fake data)
 * and each usable standalone if a theme only wants one panel embedded
 * somewhere specific rather than the full tabbed widget.
 *
 * No inline styles, no hard-coded colors — every visual value comes
 * from LoyaltyWidget.css's CSS custom properties, overridable by
 * whatever theme embeds this component. See docs/STOREFRONT.md.
 */
export function LoyaltyWidget({ panels = ['points', 'rewards', 'vip', 'referral'], className }: LoyaltyWidgetProps) {
  const [tab, setTab] = useState<WidgetTab>(panels[0]);

  return (
    <div className={`loyalty-widget ${className ?? ''}`}>
      <div className="loyalty-widget__tabs" role="tablist">
        {panels.map((panel) => (
          <button
            key={panel}
            role="tab"
            aria-selected={tab === panel}
            className={`loyalty-widget__tab ${tab === panel ? 'loyalty-widget__tab--active' : ''}`}
            onClick={() => setTab(panel)}
          >
            {TAB_LABELS[panel]}
          </button>
        ))}
      </div>

      {tab === 'points' && <PointsView />}
      {tab === 'rewards' && <RewardsView />}
      {tab === 'vip' && <VipView />}
      {tab === 'referral' && <ReferralView />}
    </div>
  );
}
