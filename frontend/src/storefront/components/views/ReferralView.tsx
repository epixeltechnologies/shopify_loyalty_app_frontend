import { useState } from 'react';
import { useStorefrontApi } from '../../hooks/useStorefrontApi';
import { storefrontService } from '../../api/storefrontService';

export function ReferralView() {
  const { data: link, isLoading: linkLoading } = useStorefrontApi(storefrontService.referralLink, []);
  const { data: stats, isLoading: statsLoading } = useStorefrontApi(storefrontService.referralStatistics, []);
  const [copied, setCopied] = useState(false);

  async function copyLink() {
    if (!link) return;
    try {
      await navigator.clipboard.writeText(link.referral_url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard API unavailable (e.g. insecure context) — the link is still visible to select/copy manually
    }
  }

  if (linkLoading || statsLoading) return <p className="loyalty-widget__subdued">Loading your referral details…</p>;

  return (
    <div className="loyalty-widget__section-stack">
      <div className="loyalty-widget__subdued">Share your link — you and your friend both earn rewards.</div>

      {link && (
        <div className="loyalty-widget__reward-card">
          <div style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{link.referral_url}</div>
          <button className="loyalty-widget__button" onClick={copyLink}>
            {copied ? 'Copied!' : 'Copy'}
          </button>
        </div>
      )}

      {stats && (
        <div className="loyalty-widget__section-stack" style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <div>
            <div className="loyalty-widget__balance" style={{ fontSize: 20 }}>{stats.successful_referrals}</div>
            <div className="loyalty-widget__subdued">successful referrals</div>
          </div>
          <div>
            <div className="loyalty-widget__balance" style={{ fontSize: 20 }}>{stats.pending_referrals}</div>
            <div className="loyalty-widget__subdued">pending</div>
          </div>
        </div>
      )}
    </div>
  );
}
