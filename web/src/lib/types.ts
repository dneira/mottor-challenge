export type Currency = 'ARS' | 'USD' | 'EUR';
// Exact decimal strings inside the UI; HTTP continues to use JSON numbers.
export interface QuoteSummary {
  id: string; budget_id: string; client: string; event: string; event_lines: string[];
  original_currency: Currency; original_total: string; currency: Currency; total: string | null;
}
export interface QuoteItem {
  id: string; description: string; description_lines: string[]; category: string;
  quantity: string; unit_price: string; currency: Currency; line_total: string;
}
export interface BreakdownStep {
  step: string; type: string; label: string; base: string; amount: string; running_total: string;
}
export interface QuoteDetail {
  id: string; budget_id: string; client: string; event: string; event_lines: string[];
  currency: Currency; subtotal: string; total: string; exchange_rates: Record<string, string>;
  items: QuoteItem[]; breakdown: BreakdownStep[];
}
export interface QuotePage {
  data: QuoteSummary[];
  meta: { page: number; per_page: number; total: number; last_page: number };
}
export interface BatchResponse { imported: number; quotes: { id: string; budget_id: string; currency: Currency; total: string }[] }
