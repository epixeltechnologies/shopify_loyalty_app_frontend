/** Shared display formatters — kept framework-free so they're trivially unit-testable. */

export function formatPoints(points: number): string {
  return new Intl.NumberFormat('en-US').format(points);
}

export function formatCurrency(amount: number, currency = 'USD'): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';

  return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(new Date(iso));
}

export function formatRelativeToNow(iso: string): string {
  const diffMs = new Date(iso).getTime() - Date.now();
  const diffDays = Math.round(diffMs / 86_400_000);
  const rtf = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' });

  return rtf.format(diffDays, 'day');
}
