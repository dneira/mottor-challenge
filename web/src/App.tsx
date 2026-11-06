import { useEffect, useState } from 'react';
import { ImportPanel } from './features/import/ImportPanel';
import { QuoteList } from './features/quotes/QuoteList';
import { QuoteDetail } from './features/quotes/QuoteDetail';
import { useQuotes } from './features/quotes/useQuotes';
import type { Currency } from './lib/types';

export default function App() {
  const [view, setView] = useState<'quotes' | 'import'>('quotes');
  const [client, setClient] = useState('');
  const [filter, setFilter] = useState('');
  const [currency, setCurrency] = useState<Currency>('ARS');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);
  const [revision, setRevision] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  useEffect(() => { const timer = setTimeout(() => { setFilter(client); setPage(1); }, 300); return () => clearTimeout(timer); }, [client]);
  const quotes = useQuotes(page, perPage, filter, currency, revision);
  return <div className="app-shell">
    <aside className="sidebar"><a className="brand" href="#" onClick={(event) => { event.preventDefault(); setView('quotes'); }}>mottor<span>.ai</span><i aria-hidden="true" /></a><div className="workspace"><span className="workspace-avatar">M</span><div>Espacio comercial<small>Presupuestos de eventos</small></div></div><span className="nav-caption">TU ESPACIO</span>
      <nav aria-label="Navegación principal"><button aria-current={view === 'quotes' ? 'page' : undefined} onClick={() => setView('quotes')}><span aria-hidden="true">▤</span> Presupuestos</button><button aria-current={view === 'import' ? 'page' : undefined} onClick={() => setView('import')}><span aria-hidden="true">↥</span> Importar histórico</button></nav>
      <div className="sidebar-bottom"><span className="small-dot" /><p>El detrás de escena<br />de cada gran evento.</p><small>MOTTOR / COMERCIAL</small></div>
    </aside>
    <div className="main-shell"><header className="topbar"><span>Comercial <span className="breadcrumb-slash">/</span> <b>{view === 'quotes' ? 'Presupuestos' : 'Importación'}</b></span><span className="topbar-note">Planificá con claridad.</span></header>
      <main><div className="page-heading"><div><span className="eyebrow">CADA NÚMERO TIENE UNA HISTORIA</span><h1>{view === 'quotes' ? 'Presupuestos.' : 'Importar histórico.'}</h1><p>{view === 'quotes' ? 'Todo tu histórico, con el detalle que necesitás.' : 'Tus presupuestos anteriores, en un solo lugar.'}</p></div>{view === 'quotes' && <button className="button primary" onClick={() => setView('import')}>Importar histórico <span aria-hidden="true">↗</span></button>}</div>
        <div hidden={view !== 'quotes'}><QuoteList {...quotes} currency={currency} page={page} perPage={perPage} client={client} onClient={setClient} onCurrency={(value) => { setCurrency(value); setPage(1); }} onPage={setPage} onPerPage={(value) => { setPerPage(value); setPage(1); }} onOpen={setSelected} onRetry={() => setRevision((value) => value + 1)} onImport={() => setView('import')} /></div>
        <div hidden={view !== 'import'}><ImportPanel onImported={() => setRevision((value) => value + 1)} onViewQuotes={() => { setPage(1); setView('quotes'); }} /></div>
      </main>
    </div>
    {selected && <QuoteDetail id={selected} onClose={() => setSelected(null)} />}
  </div>;
}
