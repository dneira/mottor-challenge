import { useEffect, useRef, useState } from 'react';
import { getQuote } from '../../lib/api';
import { formatMoney } from '../../lib/money';
import { ticketLines } from '../../lib/ticket';
import type { QuoteDetail as Detail } from '../../lib/types';

export function TicketPreview({ quote }: { quote: Detail }) {
  const [selected, setSelected] = useState(quote.items[0]?.id || '');
  const [edits, setEdits] = useState<Record<string, string>>({});
  const item = quote.items.find((candidate) => candidate.id === selected);
  const description = item ? edits[item.id] ?? item.description : '';
  return <section className="ticket-section" aria-labelledby="preview-title">
    <div className="ticket-editor"><span className="number-label">VISTA PREVIA LOCAL</span><h3 id="preview-title">Así sale en el ticket.</h3><p>Editá una descripción y mirá cómo se distribuye en 28 columnas.</p>
      {item ? <><label htmlFor="preview-item">Ítem del presupuesto</label><select id="preview-item" value={selected} onChange={(event) => setSelected(event.target.value)}>{quote.items.map((entry) => <option key={entry.id} value={entry.id}>{entry.id} · {entry.description || 'Sin descripción'}</option>)}</select>
      <label htmlFor="preview-description">Descripción</label><textarea id="preview-description" rows={4} value={description} onChange={(event) => setEdits({ ...edits, [item.id]: event.target.value })} /><small>Esta edición no se guarda ni modifica el presupuesto.</small><button className="button quiet" onClick={() => setEdits({})}>Restablecer descripciones</button></> : <p>Este presupuesto no contiene ítems.</p>}
      <button className="button secondary" onClick={() => window.print()}>Imprimir presupuesto</button>
    </div>
    <div className="ticket-paper"><span className="ticket-caption">MOTTOR / PRESUPUESTO</span><div className="ticket-rule" />
      <div className="ticket-lines" aria-label="Renglones del evento">{quote.event_lines.map((line, index) => <div key={index}>{line}</div>)}</div>
      <div className="ticket-rule" /><span className="ticket-caption">DESCRIPCIÓN DEL ÍTEM</span>
      <div className="ticket-lines" aria-label="Renglones de la descripción" data-testid="preview-lines">{ticketLines(description).map((line, index) => <div key={index}>{line}</div>)}</div>
      <div className="ticket-rule" /><span className="ticket-caption">28 COLUMNAS · MÁX. 4 RENGLONES</span>
    </div>
    <div className="print-ticket"><strong>{quote.budget_id}</strong><p>{quote.client}</p>
      <pre>{quote.event_lines.join('\n')}</pre>
      {quote.items.map((entry) => <div key={entry.id}><pre>{ticketLines(edits[entry.id] ?? entry.description).join('\n')}</pre><small>{entry.quantity} · {formatMoney(entry.line_total, quote.currency)}</small></div>)}
      <p>Subtotal: {formatMoney(quote.subtotal, quote.currency)}</p>
      {quote.breakdown.map((step) => <p key={step.step}>{step.label}: {formatMoney(step.amount, quote.currency)}</p>)}
      <strong>Total: {formatMoney(quote.total, quote.currency)}</strong>
    </div>
  </section>;
}

export function QuoteDetail({ id, onClose }: { id: string; onClose: () => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const [quote, setQuote] = useState<Detail | null>(null);
  const [error, setError] = useState('');
  const [revision, setRevision] = useState(0);
  useEffect(() => { const element = dialog.current; element?.showModal(); return () => element?.close(); }, []);
  useEffect(() => {
    const controller = new AbortController(); let active = true;
    setQuote(null); setError('');
    getQuote(id, controller.signal).then((data) => { if (active) setQuote(data); }).catch((reason) => { if (active) setError(reason.message); });
    return () => { active = false; controller.abort(); };
  }, [id, revision]);
  return <dialog ref={dialog} className="detail-dialog" onCancel={onClose} aria-labelledby="detail-title">
    <header className="detail-header"><div><span className="number-label">DETALLE DEL PRESUPUESTO</span><h2 id="detail-title">{quote?.budget_id || 'Presupuesto'}</h2>{quote && <p>{quote.client}</p>}</div><button className="close-button" onClick={onClose} aria-label="Cerrar detalle">×</button></header>
    <div className="detail-content">
      {error ? <div className="alert error" role="alert">{error}<button className="button secondary" onClick={() => setRevision(revision + 1)}>Volver a intentar</button></div> : !quote ? <div className="empty-state" role="status">Cargando detalle…</div> : <>
        <div className="detail-event">{quote.event_lines.map((line, index) => <div key={index}>{line}</div>)}</div>
        <div className="detail-totals"><div><span>Subtotal original</span><strong>{formatMoney(quote.subtotal, quote.currency)}</strong></div><div className="grand-total"><span>Total del presupuesto</span><strong>{formatMoney(quote.total, quote.currency)}</strong></div></div>
        <div className="section-title"><h3>Ítems del evento</h3><span>{quote.items.length} ítems · totales en {quote.currency}</span></div>
        <div className="table-scroll"><table className="items-table"><thead><tr><th>Descripción</th><th>Cant.</th><th className="align-right">Precio unitario</th><th className="align-right">Total de línea</th></tr></thead><tbody>{quote.items.map((item) => <tr key={item.id}><td><span className="event-lines">{item.description_lines.map((line, index) => <span key={index}>{line}</span>)}</span><small className="category">{item.category}</small></td><td>{item.quantity}</td><td className="align-right money">{formatMoney(item.unit_price, item.currency)}</td><td className="align-right money">{formatMoney(item.line_total, quote.currency)}</td></tr>)}</tbody></table></div>
        <div className="section-title"><h3>Cómo llegamos al total</h3><span>{quote.breakdown.length} reglas, en su orden original</span></div>
        {quote.breakdown.length ? <ol className="breakdown">{quote.breakdown.map((step) => <li key={step.step}><span className="step-number">{step.step}</span><div><strong>{step.label}</strong><small>Base: {formatMoney(step.base, quote.currency)}</small></div><div className="step-amount"><strong className={step.amount.startsWith('-') ? 'discount' : ''}>{formatMoney(step.amount, quote.currency)}</strong><small>Acumulado: {formatMoney(step.running_total, quote.currency)}</small></div></li>)}</ol> : <p className="muted">Este presupuesto no tiene reglas comerciales.</p>}
        <TicketPreview key={quote.id} quote={quote} />
      </>}
    </div>
  </dialog>;
}
