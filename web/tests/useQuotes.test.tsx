import { act, renderHook, waitFor } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { useQuotes } from '../src/features/quotes/useQuotes';
import { getQuotes } from '../src/lib/api';
import type { Currency, QuotePage } from '../src/lib/types';

vi.mock('../src/lib/api', () => ({ getQuotes: vi.fn() }));
it('discards late responses after filter or currency changes', async () => {
  let oldResolve!: (value: QuotePage) => void;
  const old = new Promise<QuotePage>((resolve) => { oldResolve = resolve; });
  const next: QuotePage = { data: [], meta: { page: 1, per_page: 50, total: 2, last_page: 1 } };
  vi.mocked(getQuotes).mockReturnValueOnce(old).mockResolvedValueOnce(next);
  const { result, rerender } = renderHook(({ currency }: { currency: Currency }) => useQuotes(1, 50, '', currency, 0), { initialProps: { currency: 'ARS' as Currency } });
  rerender({ currency: 'USD' });
  await waitFor(() => expect(result.current.data).toEqual(next));
  await act(async () => { oldResolve({ ...next, meta: { ...next.meta, total: 999 } }); await old; });
  expect(result.current.data?.meta.total).toBe(2);
  expect(vi.mocked(getQuotes).mock.calls[0][1]?.aborted).toBe(true);
});
