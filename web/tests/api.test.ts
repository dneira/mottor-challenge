import { expect, it, vi } from 'vitest';
import { parse } from 'lossless-json';
import { getQuotes, postBatch } from '../src/lib/api';

it('keeps response money exact and converts only metadata to safe integers', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"data":[{"id":9007199254740993,"total":9007199254740993.01}],"meta":{"page":1,"per_page":50,"total":1,"last_page":1}}')));
  const page = await getQuotes({ page: 1, perPage: 50, currency: 'ARS', client: '100%_!' });
  expect(page.data[0].total).toBe('9007199254740993.01');
  expect(page.data[0].id).toBe('9007199254740993');
  expect(page.meta.total).toBe(1);
  expect(vi.mocked(fetch).mock.calls[0][0]).toContain('client=100%25_%21');
});
it('sends numeric tokens without a float roundtrip', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"imported":1,"quotes":[{"id":1,"budget_id":"A","currency":"ARS","total":9007199254740993.01}]}', { status: 201 })));
  await postBatch(parse('[{"unit_price":9007199254740993.01}]') as unknown[]);
  expect(vi.mocked(fetch).mock.calls[0][1]?.body).toBe('{"quotes":[{"unit_price":9007199254740993.01}]}');
});
it('marks malformed success as uncertain and does not retry', async () => {
  const fetcher = vi.fn().mockResolvedValue(new Response('{"imported":0,"quotes":[]}', { status: 201 }));
  vi.stubGlobal('fetch', fetcher);
  await expect(postBatch([{}])).rejects.toMatchObject({ uncertain: true });
  expect(fetcher).toHaveBeenCalledTimes(1);
});
it('keeps 422 paths for the importer', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"message":"Inválido","errors":{"quotes.0.currency":["Moneda"]}}', { status: 422 })));
  await expect(postBatch([{}])).rejects.toMatchObject({ status: 422, errors: { 'quotes.0.currency': ['Moneda'] }, uncertain: false });
});
