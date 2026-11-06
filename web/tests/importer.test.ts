import { describe, expect, it, vi } from 'vitest';
import { stringify } from 'lossless-json';
import { makeBatches, parseDataset, runBatches, type BatchJob } from '../src/features/import/importer';
import { ApiError } from '../src/lib/api';

const result = (count: number) => ({ imported: count, quotes: [] });
describe('dataset envelope', () => {
  it('preserves exact numeric tokens when splitting files', () => {
    const dataset = parseDataset('{"version":"v2","generated_at":"2026-10-26","count":1,"quotes":[{"price":9007199254740993.01}]}');
    expect(stringify({ quotes: makeBatches(dataset.quotes)[0].quotes })).toBe('{"quotes":[{"price":9007199254740993.01}]}');
  });
  it.each([
    'broken', '{"version":"v1","generated_at":"today","count":1,"quotes":[{}]}',
    '{"version":"v2","generated_at":"today","count":2,"quotes":[{}]}',
    '{"version":"v2","generated_at":"today","count":"1","quotes":[{}]}',
    '{"version":"v2","generated_at":"today","count":0,"quotes":[]}',
  ])('rejects malformed or inconsistent file %s', (raw) => expect(() => parseDataset(raw)).toThrow());
  it('splits 10,000 quotes into 20 bounded batches', () => {
    const jobs = makeBatches(Array.from({ length: 10000 }, (_, id) => ({ id })));
    expect(jobs).toHaveLength(20);
    expect(jobs.every((job) => job.quotes.length === 500)).toBe(true);
    expect(jobs[19].to).toBe(10000);
  });
});
describe('sequential import', () => {
  it('never sends a second batch while the first is pending', async () => {
    const jobs = makeBatches(Array(1001).fill({}));
    let resolve!: (value: ReturnType<typeof result>) => void;
    const first = new Promise<ReturnType<typeof result>>((done) => { resolve = done; });
    const send = vi.fn().mockReturnValueOnce(first).mockResolvedValue(result(1));
    const update = vi.fn();
    const running = runBatches(jobs, update, () => false, send);
    expect(send).toHaveBeenCalledTimes(1);
    resolve(result(500)); await running;
    expect(send).toHaveBeenCalledTimes(3);
    expect(update.mock.calls.map(([job]) => job.status)).toEqual(['sending', 'success', 'sending', 'success', 'sending', 'success']);
  });
  it('continues after 422 and reports batch errors', async () => {
    const send = vi.fn().mockRejectedValueOnce(new ApiError('Inválido', 422, { 'quotes.0.rules.0.type': ['Desconocida'] })).mockResolvedValue(result(1));
    const updates: BatchJob[] = [];
    await runBatches(makeBatches(Array(501).fill({})), (job) => updates.push(job), () => false, send);
    expect(updates.map((job) => job.status)).toEqual(['sending', 'rejected', 'sending', 'success']);
    expect(updates[1].errors).toHaveProperty('quotes.0.rules.0.type');
  });
  it.each([null, 500])('stops without retry on uncertain response %s', async (status) => {
    const send = vi.fn().mockRejectedValue(new ApiError('Sin confirmar', status));
    const update = vi.fn();
    await runBatches(makeBatches(Array(501).fill({})), update, () => false, send);
    expect(send).toHaveBeenCalledTimes(1);
    expect(update.mock.calls[1][0].status).toBe('unknown');
  });
  it('stops after current batch and resumes only pending jobs', async () => {
    let jobs = makeBatches(Array(501).fill({})); let stop = false;
    const send = vi.fn().mockResolvedValue(result(500));
    await runBatches(jobs, (job) => { jobs = jobs.map((entry) => entry.index === job.index ? job : entry); if (job.status === 'success') stop = true; }, () => stop, send);
    expect(send).toHaveBeenCalledTimes(1);
    expect(jobs.map((job) => job.status)).toEqual(['success', 'pending']);
    await runBatches(jobs, () => {}, () => false, send);
    expect(send).toHaveBeenCalledTimes(2);
    expect(send.mock.calls[1][0]).toHaveLength(1);
  });
});
