import { fireEvent, render, screen, within } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { parse } from 'lossless-json';
import fixture from '../../fixtures/official.expected.json';
import { exactValues } from '../src/lib/api';
import type { QuoteDetail } from '../src/lib/types';
import { TicketPreview } from '../src/features/quotes/QuoteDetail';

it('updates ticket locally without HTTP and restores descriptions', () => {
  const fetcher = vi.fn(); vi.stubGlobal('fetch', fetcher);
  const quote = exactValues(parse(JSON.stringify(fixture))) as QuoteDetail;
  render(<TicketPreview quote={quote} />);
  fireEvent.change(screen.getByLabelText('Descripción'), { target: { value: 'Pantalla LED 6×3 m con operador técnico' } });
  const lines = within(screen.getByTestId('preview-lines'));
  expect(lines.getByText('Pantalla LED 6×3 m con')).toBeVisible();
  expect(lines.getByText('operador técnico')).toBeVisible();
  expect(fetcher).not.toHaveBeenCalled();
  expect(quote.items[0].description).toBe('Menú de tres pasos');
  fireEvent.click(screen.getByText('Restablecer descripciones'));
  expect(screen.getByLabelText('Descripción')).toHaveValue('Menú de tres pasos');
});
