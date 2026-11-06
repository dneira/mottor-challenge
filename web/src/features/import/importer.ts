import { isLosslessNumber, parse } from 'lossless-json';
import { ApiError, postBatch } from '../../lib/api';
import type { BatchResponse } from '../../lib/types';

export type BatchStatus = 'pending' | 'sending' | 'success' | 'rejected' | 'unknown';
export interface BatchJob { index: number; from: number; to: number; quotes: unknown[]; status: BatchStatus; message?: string; errors?: Record<string, string[]> }
export interface Dataset { count: number; generatedAt: string; quotes: unknown[] }
export function parseDataset(text: string): Dataset {
  let value: any;
  try { value = parse(text); } catch { throw new Error('El archivo no contiene JSON válido.'); }
  if (!value || value.version !== 'v2' || !Array.isArray(value.quotes) || typeof value.generated_at !== 'string') throw new Error('Se requiere un dataset v2 con generated_at y una lista quotes.');
  if (!isLosslessNumber(value.count) || !/^\d+$/.test(value.count.value) || Number(value.count.value) !== value.quotes.length) throw new Error('El campo count no coincide con la cantidad de presupuestos.');
  if (!value.quotes.length) throw new Error('El archivo no contiene presupuestos para importar.');
  return { count: value.quotes.length, generatedAt: value.generated_at, quotes: value.quotes };
}
export function makeBatches(quotes: unknown[]): BatchJob[] {
  return Array.from({ length: Math.ceil(quotes.length / 500) }, (_, index) => ({
    index, from: index * 500 + 1, to: Math.min((index + 1) * 500, quotes.length),
    quotes: quotes.slice(index * 500, (index + 1) * 500), status: 'pending',
  }));
}
export async function runBatches(
  jobs: BatchJob[],
  update: (job: BatchJob) => void,
  shouldStop: () => boolean,
  send: (quotes: unknown[]) => Promise<BatchResponse> = postBatch,
): Promise<void> {
  for (const job of jobs) {
    if (shouldStop()) break;
    if (job.status !== 'pending') continue;
    update({ ...job, status: 'sending' });
    try {
      await send(job.quotes);
      update({ ...job, status: 'success' });
    } catch (error) {
      const api = error instanceof ApiError ? error : new ApiError('No se pudo confirmar el lote.');
      update({ ...job, status: api.uncertain ? 'unknown' : 'rejected', message: api.message, errors: api.errors });
      // 422 is a confirmed rejection. Continue independent batches, but never retry a POST.
      if (api.status !== 422) break;
    }
  }
}
