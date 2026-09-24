import { useEffect, useState } from 'react';
import { Page, Layout, Card, BlockStack, Text, Button, InlineGrid, Badge, Banner, List } from '@shopify/polaris';
import { billingService } from '@/services/billingService';
import { useApi } from '@/hooks/useApi';
import { useNotifications } from '@/hooks/useNotifications';
import { LoadingState } from '@/components/common/LoadingState';
import { formatPoints } from '@/utils/formatters';
import type { DowngradeEligibility, Plan } from '@/types/billing';

/**
 * The actual plan-comparison/chooser — reached both as its own route
 * (/plans, for changing plans later) and embedded in
 * BillingRequiredPage (the no-subscription-yet gate) since choosing a
 * plan is exactly what that gate needs the merchant to do. See
 * docs/FRONTEND_ARCHITECTURE.md and docs/BILLING.md.
 */
export function PlansPage() {
  const { data: plans, isLoading } = useApi(billingService.plans, []);
  const { data: status } = useApi(billingService.status, []);
  const { notify } = useNotifications();

  const [subscribing, setSubscribing] = useState<string | null>(null);
  const [checks, setChecks] = useState<Record<string, DowngradeEligibility>>({});

  const currentPlanSlug = status?.plan?.slug ?? null;

  useEffect(() => {
    if (!plans || !status?.has_active_subscription) return;

    plans.forEach((plan) => {
      if (plan.slug === currentPlanSlug) return;

      billingService
        .downgradeCheck(plan.slug)
        .then((result) => setChecks((prev) => ({ ...prev, [plan.slug]: result })))
        .catch(() => undefined); // a failed preview check just means no inline warning shows — subscribe() still enforces it
    });
  }, [plans, status?.has_active_subscription, currentPlanSlug]);

  async function handleChoosePlan(plan: Plan) {
    setSubscribing(plan.slug);
    try {
      const { confirmation_url } = await billingService.subscribe(plan.slug);
      window.top!.location.href = confirmation_url; // Shopify billing confirmation must break out of the iframe
    } catch (error) {
      const blockers = (error as { response?: { data?: DowngradeEligibility } })?.response?.data?.blockers;
      if (blockers?.length) {
        notify(blockers[0].message, 'critical');
      }
    } finally {
      setSubscribing(null);
    }
  }

  function ctaLabel(plan: Plan, isCurrentPlan: boolean, isDowngrade: boolean): string {
    if (isCurrentPlan) return 'Current plan';
    if (!status?.has_active_subscription) return `Choose ${plan.name}`;

    return isDowngrade ? `Downgrade to ${plan.name}` : `Upgrade to ${plan.name}`;
  }

  return (
    <Page title="Plans" subtitle="Compare plans and choose the one that fits your store">
      <Layout>
        <Layout.Section>
          {isLoading || !plans ? (
            <LoadingState lines={6} />
          ) : (
            <InlineGrid columns={{ xs: 1, md: plans.length || 2 }} gap="400">
              {plans.map((plan) => {
                const isCurrentPlan = plan.slug === currentPlanSlug;
                const isDowngrade = Boolean(status?.plan) && plan.price_monthly < (status!.plan!.price_monthly ?? 0);
                const check = checks[plan.slug];

                return (
                  <Card key={plan.slug}>
                    <BlockStack gap="300">
                      <Text as="h2" variant="headingLg">
                        {plan.name}
                      </Text>
                      <Text as="p" variant="heading2xl">
                        ${plan.price_monthly}
                        <Text as="span" variant="bodySm" tone="subdued">
                          {' '}
                          / month
                        </Text>
                      </Text>
                      <Text as="p" tone="subdued">
                        {plan.description}
                      </Text>
                      <BlockStack gap="100">
                        <Text as="p">
                          Up to {plan.limits.max_active_customers === null ? 'unlimited' : formatPoints(plan.limits.max_active_customers)} active customers
                        </Text>
                        <Text as="p">
                          {plan.limits.max_active_point_rules === null ? 'Unlimited' : plan.limits.max_active_point_rules} reward campaigns
                        </Text>
                      </BlockStack>
                      {Object.entries(plan.features ?? {}).map(([group, items]) => (
                        <div key={group} style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                          {items.map((f) => (
                            <Badge key={f}>{f}</Badge>
                          ))}
                        </div>
                      ))}

                      {check && check.blockers.length > 0 && (
                        <Banner tone="critical" title="This downgrade is currently blocked">
                          <List>
                            {check.blockers.map((b) => (
                              <List.Item key={b.code}>{b.message}</List.Item>
                            ))}
                          </List>
                        </Banner>
                      )}
                      {check && check.eligible && check.warnings.length > 0 && (
                        <Banner tone="warning" title="Before you downgrade">
                          <List>
                            {check.warnings.map((w) => (
                              <List.Item key={w.code}>{w.message}</List.Item>
                            ))}
                          </List>
                        </Banner>
                      )}

                      <Button
                        variant={isCurrentPlan ? 'secondary' : 'primary'}
                        disabled={isCurrentPlan || (check ? !check.eligible : false)}
                        loading={subscribing === plan.slug}
                        onClick={() => handleChoosePlan(plan)}
                      >
                        {ctaLabel(plan, isCurrentPlan, isDowngrade)}
                      </Button>
                    </BlockStack>
                  </Card>
                );
              })}
            </InlineGrid>
          )}
        </Layout.Section>
      </Layout>
    </Page>
  );
}
