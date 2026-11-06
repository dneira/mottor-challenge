import type { Currency } from './types';

const integerFormat = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 });
export function formatMoney(value: string | null, currency: Currency): string {
  if (value === null) return 'Sin tipo de cambio';
  if (!/^-?\d+(?:\.\d{1,2})?$/.test(value)) throw new Error('Importe monetario inválido.');
  const [whole, fraction = ''] = value.split('.');
  const negative = value.startsWith('-');
  const grouped = integerFormat.format(BigInt(whole.replace('-', '')));
  return `${negative ? '−' : ''}${currency} ${grouped},${fraction.padEnd(2, '0')}`;
}
