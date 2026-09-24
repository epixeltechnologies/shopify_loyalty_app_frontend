import { Banner } from '@shopify/polaris';
import { useNavigate } from 'react-router-dom';

interface Props {
  title?: string;
  message: string;
  requiredPlan?: string | null;
}

/**
 * Standalone upgrade nudge — used both on its own (e.g. a usage-nearing-
 * limit warning on /billing) and embedded inside FeatureLockedState.
 * Always routes to /plans rather than starting a subscribe flow
 * directly from wherever the banner is shown, so the merchant sees the
 * full comparison (and any downgrade-safety warnings, if relevant)
 * before committing — see docs/BILLING.md.
 */
export function UpgradeBanner({ title = 'Upgrade to unlock this', message, requiredPlan }: Props) {
  const navigate = useNavigate();

  return (
    <Banner
      tone="info"
      title={title}
      action={{ content: requiredPlan ? `View ${requiredPlan} plan` : 'View plans', onAction: () => navigate('/plans') }}
    >
      <p>{message}</p>
    </Banner>
  );
}
