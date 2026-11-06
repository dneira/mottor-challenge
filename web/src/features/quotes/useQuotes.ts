import { useEffect, useState } from 'react';
import { getQuotes } from '../../lib/api';
import type { Currency, QuotePage } from '../../lib/types';

export function useQuotes(page: number, perPage: number, client: string, currency: Currency, revision: number) {
  const [data, setData] = useState<QuotePage | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    const controller = new AbortController();
    let active = true;
    setLoading(true); setError(''); setData(null);
    getQuotes({ page, perPage, client, currency }, controller.signal)
      .then((result) => { if (active) setData(result); })
      .catch((reason) => { if (active) setError(reason.message); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; controller.abort(); };
  }, [page, perPage, client, currency, revision]);
  return { data, error, loading };
}
