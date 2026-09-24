import { useStorefrontApi } from '../../hooks/useStorefrontApi';
import { storefrontService } from '../../api/storefrontService';

export function VipView() {
  const { data: current, isLoading: currentLoading } = useStorefrontApi(storefrontService.vipCurrent, []);
  const { data: progress, isLoading: progressLoading } = useStorefrontApi(storefrontService.vipProgress, []);
  const { data: benefits } = useStorefrontApi(storefrontService.vipBenefits, []);

  if (currentLoading || progressLoading) return <p className="loyalty-widget__subdued">Loading your VIP status…</p>;

  return (
    <div className="loyalty-widget__section-stack">
      <div>
        <div className="loyalty-widget__balance" style={{ fontSize: 22 }}>
          {current ? current.name : 'Not yet a VIP member'}
        </div>
        {current?.description && <div className="loyalty-widget__subdued">{current.description}</div>}
      </div>

      {benefits && Object.keys(benefits).length > 0 && (
        <div>
          <div>Your benefits</div>
          <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
            {Object.entries(benefits).map(([key, value]) => (
              <li key={key} className="loyalty-widget__subdued">
                {key.replace(/_/g, ' ')}: {String(value)}
              </li>
            ))}
          </ul>
        </div>
      )}

      {progress?.next_tier ? (
        <div>
          <div className="loyalty-widget__subdued">
            {progress.remaining?.toLocaleString()} more to reach {progress.next_tier.name}
          </div>
          <div style={{ background: 'var(--loyalty-color-border)', borderRadius: 999, height: 8, marginTop: 6, overflow: 'hidden' }}>
            <div
              style={{
                width: `${progress.percent_complete ?? 0}%`,
                background: 'var(--loyalty-color-primary)',
                height: '100%',
              }}
            />
          </div>
        </div>
      ) : (
        progress?.message && <div className="loyalty-widget__subdued">{progress.message}</div>
      )}
    </div>
  );
}
