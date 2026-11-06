import { describe, expect, it } from 'vitest';
import fixtures from '../../fixtures/ticket.json';
import { ticketLines } from '../src/lib/ticket';
import { formatMoney } from '../src/lib/money';

describe('shared PHP/browser ticket fixtures', () => {
  for (const fixture of fixtures) it(fixture.name, () => expect(ticketLines(fixture.input)).toEqual(fixture.expected));
  it('handles Unicode next-line whitespace like PHP', () => expect(ticketLines('Hola\u0085mundo')).toEqual(['Hola mundo']));
});
describe('exact monetary display', () => {
  it('keeps cents beyond Number precision', () => expect(formatMoney('9007199254740993.01', 'ARS')).toBe('ARS 9.007.199.254.740.993,01'));
  it('formats discounts and zero', () => {
    expect(formatMoney('-119500.00', 'ARS')).toBe('−ARS 119.500,00');
    expect(formatMoney('0.00', 'USD')).toBe('USD 0,00');
  });
  it('does not represent a missing conversion as zero', () => expect(formatMoney(null, 'EUR')).toBe('Sin tipo de cambio'));
});
