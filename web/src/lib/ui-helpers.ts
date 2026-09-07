import { formatRupiahFromCents } from './format-money';

export { formatRupiahFromCents };

export function formatMoney(cents: number | null | undefined): string {
  return formatRupiahFromCents(cents ?? 0);
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

export function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export type Tone = 'blue' | 'green' | 'amber' | 'red' | 'gray' | 'purple';

export const toneClass: Record<Tone, string> = {
  blue: 'badge-blue',
  green: 'badge-green',
  amber: 'badge-amber',
  red: 'badge-red',
  gray: 'badge-gray',
  purple: 'badge-purple',
};

export function invoiceTone(status: string): Tone {
  switch (status) {
    case 'PAID': return 'green';
    case 'VOID': return 'gray';
    default: return status === 'OPEN' ? 'blue' : 'purple';
  }
}

export function paymentTone(status: string): Tone {
  switch (status) {
    case 'SETTLED': return 'green';
    case 'FAILED': return 'red';
    case 'REFUNDED': return 'gray';
    default: return 'amber';
  }
}

export function humanize(value: string): string {
  return value
    .toLowerCase()
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (ch) => ch.toUpperCase());
}