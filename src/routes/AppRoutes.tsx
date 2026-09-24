import { Routes, Route } from 'react-router-dom';
import { DashboardPage } from '@/pages/Dashboard/DashboardPage';
import { CustomerListPage } from '@/pages/Customers/CustomerListPage';
import { CustomerDetailPage } from '@/pages/Customers/CustomerDetailPage';
import { PointsPage } from '@/pages/Points/PointsPage';
import { RewardsPage } from '@/pages/Rewards/RewardsPage';
import { RewardDetailPage } from '@/pages/Rewards/RewardDetailPage';
import { ReferralsPage } from '@/pages/Referrals/ReferralsPage';
import { VipTiersPage } from '@/pages/VipTiers/VipTiersPage';
import { VipTierDetailPage } from '@/pages/VipTiers/VipTierDetailPage';
import { AnalyticsPage } from '@/pages/Analytics/AnalyticsPage';
import { BillingPage } from '@/pages/Billing/BillingPage';
import { PlansPage } from '@/pages/Plans/PlansPage';
import { SubscriptionPage } from '@/pages/Subscription/SubscriptionPage';
import { SettingsPage } from '@/pages/Settings/SettingsPage';
import { NotFoundPage } from '@/pages/NotFound/NotFoundPage';

/**
 * The route table only — no layout, no data-fetching, no auth logic
 * here (that's ProtectedRoute, wrapping this whole tree in App.tsx).
 * Every route below is implicitly "protected"; a route that should be
 * reachable without an active subscription would need to be hoisted
 * above <ProtectedRoute> in App.tsx instead of listed here.
 *
 * Billing has three distinct routes (see docs/BILLING.md#billing-ui):
 * /billing (overview), /plans (comparison/change), /subscription
 * (status/history detail) — not one page wearing three hats.
 *
 * Points/Rewards/Referrals/VIP Tiers each consolidate several of the
 * task's requested sub-pages into one page with Polaris Tabs (see each
 * page's own docblock for why) — a plain object/list/detail entity
 * doesn't need a fully separate route+file per sub-view when a tab
 * cleanly groups them without hiding functionality.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<DashboardPage />} />
      <Route path="/customers" element={<CustomerListPage />} />
      <Route path="/customers/:id" element={<CustomerDetailPage />} />
      <Route path="/points" element={<PointsPage />} />
      <Route path="/rewards" element={<RewardsPage />} />
      <Route path="/rewards/:id" element={<RewardDetailPage />} />
      <Route path="/referrals" element={<ReferralsPage />} />
      <Route path="/vip-tiers" element={<VipTiersPage />} />
      <Route path="/vip-tiers/:id" element={<VipTierDetailPage />} />
      <Route path="/analytics" element={<AnalyticsPage />} />
      <Route path="/billing" element={<BillingPage />} />
      <Route path="/plans" element={<PlansPage />} />
      <Route path="/subscription" element={<SubscriptionPage />} />
      <Route path="/settings" element={<SettingsPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
