export function formatRupiahFromCents(amountCents: number | string): string {
  const numericAmount = typeof amountCents === 'string' ? Number(amountCents) : amountCents;

  if (!Number.isInteger(numericAmount)) {
    throw new Error(`amountCents harus berupa integer (diterima: ${String(amountCents)}). Jangan gunakan float untuk uang.`);
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(numericAmount / 100);
}
