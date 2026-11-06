import { isLosslessNumber, parse, stringify } from 'lossless-json';
import type { BatchResponse, Currency, QuoteDetail, QuotePage } from './types';

export const API_URL = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
export class ApiError extends Error {
  constructor(message: string, public status: number | null = null, public errors: Record<string, string[]> = {}) {
    super(message); this.name = 'ApiError';
  }
  get uncertain() { return this.status === null || this.status >= 500; }
}
export function exactValues(value: unknown): unknown {
  if (isLosslessNumber(value)) return value.value;
  if (Array.isArray(value)) return value.map(exactValues);
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, exactValues(item)]));
  return value;
}
function count(value: unknown): number {
  if (typeof value !== 'string' || !/^\d+$/.test(value) || !Number.isSafeInteger(Number(value))) throw new ApiError('La API devolvió un conteo inválido.');
  return Number(value);
}
async function request(path: string, options: RequestInit = {}, timeout = 20_000): Promise<any> {
  const controller = new AbortController();
  const abort = () => controller.abort();
  options.signal?.addEventListener('abort', abort, { once: true });
  if (options.signal?.aborted) controller.abort();
  const timer = setTimeout(abort, timeout);
  try {
    const response = await fetch(`${API_URL}${path}`, { ...options, signal: controller.signal });
    const raw = await response.text();
    let data;
    try { data = exactValues(parse(raw)); }
    catch { throw new ApiError('La API devolvió una respuesta que no se pudo interpretar.', response.ok ? null : response.status); }
    if (!response.ok) {
      const error = data as { message?: string; errors?: Record<string, string[]> };
      throw new ApiError(error?.message || `Error HTTP ${response.status}.`, response.status, error?.errors || {});
    }
    return data;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (options.signal?.aborted) throw new DOMException('Solicitud cancelada', 'AbortError');
    throw new ApiError('No se pudo confirmar la respuesta del servidor. Verificá la conexión.');
  } finally {
    clearTimeout(timer);
    options.signal?.removeEventListener('abort', abort);
  }
}
export async function getQuotes(params: { page: number; perPage: number; client: string; currency: Currency }, signal?: AbortSignal): Promise<QuotePage> {
  const query = new URLSearchParams({ page: String(params.page), per_page: String(params.perPage), client: params.client, currency: params.currency });
  const data = await request(`/quotes?${query}`, { signal });
  if (!Array.isArray(data?.data) || !data?.meta) throw new ApiError('El listado recibido no es válido.');
  return { data: data.data, meta: { page: count(data.meta.page), per_page: count(data.meta.per_page), total: count(data.meta.total), last_page: count(data.meta.last_page) } };
}
export async function getQuote(id: string, signal?: AbortSignal): Promise<QuoteDetail> {
  const data = await request(`/quotes/${encodeURIComponent(id)}`, { signal });
  if (typeof data?.id !== 'string' || !Array.isArray(data?.items) || !Array.isArray(data?.breakdown)) throw new ApiError('El detalle recibido no es válido.');
  return data;
}
export async function postBatch(quotes: unknown[]): Promise<BatchResponse> {
  // lossless stringify emits the original numeric lexemes, never numeric strings or rounded floats.
  const data = await request('/quotes/batch', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: stringify({ quotes }) }, 120_000);
  if (!Array.isArray(data?.quotes)) throw new ApiError('No se pudo confirmar el resultado del lote.');
  const imported = count(data.imported);
  if (imported !== quotes.length || data.quotes.length !== imported) throw new ApiError('El servidor devolvió una cantidad inesperada; revisá el histórico.');
  return { imported, quotes: data.quotes };
}
