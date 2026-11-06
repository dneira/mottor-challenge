import type { Currency, QuotePage } from '../../lib/types';
import { formatMoney } from '../../lib/money';

interface Props {
  data: QuotePage | null; loading: boolean; error: string; currency: Currency; page: number; perPage: number; client: string;
  onCurrency: (value: Currency) => void; onClient: (value: string) => void; onPage: (value: number) => void;
  onPerPage: (value: number) => void; onOpen: (id: string) => void; onRetry: () => void; onImport: () => void;
}
export function QuoteList(props: Props) {
  const { data, loading, error, currency, page, perPage, client } = props;
  return <>
    <div className="overview"><div><span className="number-label">HISTÓRICO COMERCIAL</span><strong>{data ? data.meta.total.toLocaleString('es-AR') : '—'}<small>presupuestos{client ? ' encontrados' : ' guardados'}</small></strong></div><p>Cada presupuesto conserva sus reglas<br />y el tipo de cambio con el que se calculó.</p><div className="overview-note"><span className="small-dot" />Trazabilidad, paso a paso</div></div>
    <section className="panel quote-list" aria-label="Listado de presupuestos">
      <div className="list-toolbar"><div className="search-field"><label htmlFor="client-search">Buscar cliente</label><div><span aria-hidden="true">⌕</span><input id="client-search" type="search" placeholder="Nombre del cliente…" value={client} onChange={(event) => props.onClient(event.target.value)} /></div></div>
        <fieldset className="currency-switch"><legend>Moneda de visualización</legend><div>{(['ARS', 'USD', 'EUR'] as const).map((code) => <button key={code} type="button" aria-pressed={currency === code} onClick={() => props.onCurrency(code)}>{code}</button>)}</div></fieldset>
      </div>
      {error ? <div className="empty-state" role="alert"><h2>No pudimos cargar el histórico</h2><p>{error}</p><button className="button secondary" onClick={props.onRetry}>Volver a intentar</button></div>
        : loading ? <div className="empty-state" role="status"><span className="loader" /><p>Cargando presupuestos…</p></div>
        : !data?.data.length ? <div className="empty-state"><span className="empty-icon" aria-hidden="true">▤</span><h2>{data?.meta.total ? 'Esta página está vacía' : client ? 'No encontramos ese cliente' : 'Tu próximo evento empieza acá'}</h2><p>{data?.meta.total ? 'Volvé a la primera página del histórico.' : client ? 'Probá con otro nombre o quitá el filtro.' : 'Importá tus presupuestos para consultar totales y entender cada ajuste.'}</p>{data?.meta.total ? <button className="button secondary" onClick={() => props.onPage(1)}>Ir a la primera página</button> : client ? <button className="button secondary" onClick={() => props.onClient('')}>Quitar filtro</button> : <button className="button primary" onClick={props.onImport}>Importar histórico ↗</button>}</div>
        : <div className="table-scroll"><table><thead><tr><th>Presupuesto / cliente</th><th>Evento</th><th className="align-right">Total en {currency}</th><th><span className="sr-only">Acción</span></th></tr></thead><tbody>{data.data.map((quote) => <tr key={quote.id}>
          <td><span className="budget-code">{quote.budget_id}</span><strong className="client-name">{quote.client}</strong></td>
          <td><span className="event-lines">{quote.event_lines.map((line, index) => <span key={index}>{line}</span>)}</span></td>
          <td className="align-right"><strong className={`money ${quote.total === null ? 'missing-rate' : ''}`}>{formatMoney(quote.total, quote.currency)}</strong>{quote.currency !== quote.original_currency && <small className="original-total">Original: {formatMoney(quote.original_total, quote.original_currency)}</small>}</td>
          <td><button className="open-quote" onClick={() => props.onOpen(quote.id)} aria-label={`Ver detalle de ${quote.budget_id}`}>Ver detalle <span aria-hidden="true">↗</span></button></td>
        </tr>)}</tbody></table></div>}
      <div className="pagination"><label>Por página <select value={perPage} onChange={(event) => props.onPerPage(Number(event.target.value))}>{[25, 50, 100, 200].map((size) => <option key={size} value={size}>{size}</option>)}</select></label>
        <div><span>Página <b>{page}</b> de {data?.meta.last_page ?? '—'}</span><button aria-label="Página anterior" disabled={loading || page <= 1} onClick={() => props.onPage(page - 1)}>←</button><button aria-label="Página siguiente" disabled={loading || !data || page >= data.meta.last_page} onClick={() => props.onPage(page + 1)}>→</button></div>
      </div>
    </section>
    <p className="list-footnote">Los totales usan las tasas guardadas de cada presupuesto. Una conversión sin tasa disponible se indica en el listado.</p>
  </>;
}
