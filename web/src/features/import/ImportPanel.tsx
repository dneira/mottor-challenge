import { useEffect, useRef, useState } from 'react';
import { makeBatches, parseDataset, runBatches, type BatchJob, type BatchStatus, type Dataset } from './importer';

const labels: Record<BatchStatus, string> = { pending: 'Pendiente', sending: 'Enviando', success: 'Importado', rejected: 'Rechazado', unknown: 'Sin confirmar' };
export function ImportPanel({ onImported, onViewQuotes }: { onImported: () => void; onViewQuotes: () => void }) {
  const [dataset, setDataset] = useState<Dataset | null>(null);
  const [filename, setFilename] = useState('');
  const [jobs, setJobs] = useState<BatchJob[]>([]);
  const [error, setError] = useState('');
  const [reading, setReading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [stopping, setStopping] = useState(false);
  const stop = useRef(false);
  const running = useRef(false);
  const inputRef = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (!busy) return;
    const warn = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = ''; };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [busy]);

  async function selectFile(file?: File) {
    if (!file || busy || reading) return;
    setReading(true); setError(''); setDataset(null); setJobs([]); setFilename(file.name);
    try {
      if (file.size > 100 * 1024 * 1024) throw new Error('El archivo supera el límite local de 100 MB.');
      const next = parseDataset(await file.text());
      setDataset(next); setJobs(makeBatches(next.quotes));
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'No se pudo leer el archivo.'); }
    finally { setReading(false); if (inputRef.current) inputRef.current.value = ''; }
  }

  async function start() {
    if (running.current) return;
    running.current = true; stop.current = false; setStopping(false); setBusy(true);
    try {
      await runBatches(jobs, (job) => setJobs((current) => current.map((entry) => entry.index === job.index ? job : entry)), () => stop.current);
    } finally { running.current = false; setBusy(false); setStopping(false); onImported(); }
  }
  const confirmed = jobs.filter((job) => job.status === 'success').reduce((sum, job) => sum + job.quotes.length, 0);
  const rejected = jobs.filter((job) => job.status === 'rejected').length;
  const uncertain = jobs.some((job) => job.status === 'unknown');
  const pending = jobs.some((job) => job.status === 'pending');
  const started = jobs.some((job) => job.status !== 'pending');
  return <div className="import-layout">
    <section className="panel import-main" aria-labelledby="import-title">
      <div className="section-heading"><span className="number-label">01 / ARCHIVO</span><h2 id="import-title">Traé tu histórico.</h2><p>Seleccioná el dataset de presupuestos para incorporarlo al espacio comercial.</p></div>
      <div className={`file-drop ${busy ? 'disabled' : ''}`} onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); void selectFile(event.dataTransfer.files[0]); }}>
        <div className="file-symbol" aria-hidden="true">↥</div>
        <strong>{filename || 'Arrastrá tu dataset.json aquí'}</strong>
        <p>{reading ? 'Leyendo y validando el archivo…' : 'JSON · formato v2 · hasta 100 MB'}</p>
        <label className={`button secondary ${busy || reading ? 'disabled' : ''}`} htmlFor="dataset-file">Seleccionar archivo</label>
        <input ref={inputRef} id="dataset-file" aria-label="Seleccionar archivo dataset.json" type="file" accept=".json,application/json" disabled={busy || reading} onChange={(event) => void selectFile(event.target.files?.[0])} />
      </div>
      {error && <div className="alert error" role="alert">{error}</div>}
      {dataset && <>
        <div className="file-facts"><span><b>{dataset.count.toLocaleString('es-AR')}</b> presupuestos</span><span><b>{jobs.length}</b> lotes</span><span>Generado <b>{dataset.generatedAt}</b></span></div>
        <div className="import-actions">
          {busy ? <button className="button secondary" disabled={stopping} onClick={() => { stop.current = true; setStopping(true); }}> {stopping ? 'Deteniendo después de este lote…' : 'Detener después de este lote'}</button>
            : <button className="button primary" disabled={!pending || uncertain} onClick={() => void start()}>{started ? 'Continuar lotes pendientes' : 'Importar presupuestos'} <span aria-hidden="true">↗</span></button>}
          {confirmed > 0 && <button className="button quiet" onClick={onViewQuotes}>Ver presupuestos →</button>}
        </div>
      </>}
      {started && <div className="import-progress" aria-live="polite">
        <div><strong>{confirmed.toLocaleString('es-AR')} confirmados</strong><span>de {dataset?.count.toLocaleString('es-AR')}</span></div>
        <progress aria-label="Presupuestos confirmados" value={confirmed} max={dataset?.count || 1} />
        <p>{busy ? 'El lote actual se completa antes de detenerse.' : uncertain ? 'Hay un lote sin confirmar. Revisá el histórico antes de volver a enviarlo.' : rejected ? `${rejected} lote(s) rechazado(s). Los lotes importados se conservaron.` : pending ? 'Importación detenida. Podés continuar con los lotes pendientes.' : 'Importación terminada.'}</p>
      </div>}
    </section>
    <aside className="import-guide"><span className="number-label">IMPORTACIÓN SEGURA</span><h3>Un lote a la vez.<br />Cada resultado a la vista.</h3><ol><li><b>Validamos el archivo</b><p>La versión y el conteo deben coincidir con su contenido.</p></li><li><b>Enviamos hasta 500 por lote</b><p>Si un presupuesto es inválido, se rechaza todo ese lote.</p></li><li><b>Conservamos lo confirmado</b><p>Los lotes exitosos no se vuelven a enviar al continuar.</p></li></ol><p className="small-note">Importar otra vez el mismo archivo puede crear duplicados. Ante una desconexión, no reenviamos automáticamente.</p></aside>
    {jobs.length > 0 && <section className="panel batch-panel" aria-labelledby="batches-title"><div className="panel-heading"><h2 id="batches-title">Seguimiento por lote</h2><span className="pill">{jobs.length} lotes</span></div>
      <div className="batch-list">{jobs.map((job) => <div className="batch-row" key={job.index}>
        <div className="batch-title"><strong>Lote {job.index + 1}</strong><span>Presupuestos {job.from}–{job.to}</span></div><span className={`status ${job.status}`}>{labels[job.status]}</span>
        {job.message && <div className="batch-error"><p>{job.message}</p>{Object.entries(job.errors || {}).map(([path, messages]) => <p key={path}><code>{path}</code>: {messages.join(' ')}</p>)}</div>}
      </div>)}</div>
    </section>}
  </div>;
}
