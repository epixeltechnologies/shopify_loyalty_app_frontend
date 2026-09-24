import { useStorefrontApi } from '../../hooks/useStorefrontApi';
import { storefrontService } from '../../api/storefrontService';

export function PointsView() {
  const { data, isLoading, error } = useStorefrontApi(storefrontService.pointsBalance, []);

  if (isLoading) return <p className="loyalty-widget__subdued">Loading your points…</p>;
  if (error) return <p className="loyalty-widget__subdued">Couldn't load your points balance. Please try again.</p>;
  if (!data) return null;

  return (
    <div className="loyalty-widget__section-stack">
      <div>
        <div className="loyalty-widget__balance">{data.balance.toLocaleString()}</div>
        <div className="loyalty-widget__subdued">points available</div>
      </div>
      <div className="loyalty-widget__subdued">
        Lifetime earned: {data.lifetime_earned.toLocaleString()} · Redeemed: {data.lifetime_redeemed.toLocaleString()}
      </div>
      {data.next_expiry_date && (
        <div className="loyalty-widget__subdued">Some points expire on {new Date(data.next_expiry_date).toLocaleDateString()}</div>
      )}
    </div>
  );
}
