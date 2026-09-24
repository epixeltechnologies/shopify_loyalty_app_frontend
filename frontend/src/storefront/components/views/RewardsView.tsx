import { useState } from 'react';
import { useStorefrontApi } from '../../hooks/useStorefrontApi';
import { storefrontService } from '../../api/storefrontService';

/** Generates one UUID per redemption attempt and holds it until success — see StorefrontRedeemRewardRequest's docblock on why this matters for a tappable "Redeem" button. */
function newIdempotencyKey(): string {
  return crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

export function RewardsView() {
  const { data: rewards, isLoading, error, refetch } = useStorefrontApi(storefrontService.eligibleRewards, []);
  const [redeemingId, setRedeemingId] = useState<number | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [idempotencyKey] = useState(newIdempotencyKey);

  async function redeem(rewardId: number) {
    setRedeemingId(rewardId);
    setMessage(null);
    try {
      await storefrontService.redeemReward(rewardId, idempotencyKey);
      setMessage("Redeemed! We're preparing your discount code.");
      refetch();
    } catch (e) {
      setMessage(e instanceof Error ? e.message : 'Something went wrong.');
    } finally {
      setRedeemingId(null);
    }
  }

  if (isLoading) return <p className="loyalty-widget__subdued">Loading rewards…</p>;
  if (error) return <p className="loyalty-widget__subdued">Couldn't load rewards. Please try again.</p>;
  if (!rewards || rewards.length === 0) return <p className="loyalty-widget__subdued">No rewards available to redeem right now.</p>;

  return (
    <div className="loyalty-widget__reward-list">
      {message && <p className="loyalty-widget__subdued">{message}</p>}
      {rewards.map((reward) => (
        <div className="loyalty-widget__reward-card" key={reward.id}>
          <div>
            <div>{reward.name}</div>
            <div className="loyalty-widget__subdued">{reward.points_cost.toLocaleString()} points</div>
          </div>
          <button
            className="loyalty-widget__button"
            disabled={redeemingId === reward.id}
            onClick={() => redeem(reward.id)}
          >
            {redeemingId === reward.id ? 'Redeeming…' : 'Redeem'}
          </button>
        </div>
      ))}
    </div>
  );
}
