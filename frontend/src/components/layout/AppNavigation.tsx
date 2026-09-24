import { Navigation } from '@shopify/polaris';
import {
  HomeIcon,
  PersonIcon,
  CashDollarIcon,
  GiftCardIcon,
  ShareIcon,
  StarIcon,
  ChartVerticalIcon,
  SettingsIcon,
  CreditCardIcon,
} from '@shopify/polaris-icons';
import { useLocation, useNavigate } from 'react-router-dom';

/**
 * The nine top-level sections the task requires. Every item here is a
 * FRONTEND convenience only — visiting a route whose feature the
 * shop's plan doesn't include still renders that page's own
 * FeatureLockedState/PlanLimitReached (driven by useEntitlements), and
 * every backing API call is independently enforced server-side
 * regardless of what this nav shows. See docs/ENTITLEMENTS.md.
 */
export function AppNavigation() {
  const location = useLocation();
  const navigate = useNavigate();

  const go = (url: string) => () => navigate(url);

  return (
    <Navigation location={location.pathname}>
      <Navigation.Section
        items={[
          { url: '/', label: 'Dashboard', icon: HomeIcon, onClick: go('/'), selected: location.pathname === '/' },
          { url: '/customers', label: 'Customers', icon: PersonIcon, onClick: go('/customers') },
          { url: '/points', label: 'Points', icon: CashDollarIcon, onClick: go('/points') },
          { url: '/rewards', label: 'Rewards', icon: GiftCardIcon, onClick: go('/rewards') },
          { url: '/referrals', label: 'Referrals', icon: ShareIcon, onClick: go('/referrals') },
          { url: '/vip-tiers', label: 'VIP Tiers', icon: StarIcon, onClick: go('/vip-tiers') },
          { url: '/analytics', label: 'Analytics', icon: ChartVerticalIcon, onClick: go('/analytics') },
        ]}
      />
      <Navigation.Section
        title="Account"
        items={[
          { url: '/billing', label: 'Billing', icon: CreditCardIcon, onClick: go('/billing') },
          { url: '/settings', label: 'Settings', icon: SettingsIcon, onClick: go('/settings') },
        ]}
      />
    </Navigation>
  );
}
