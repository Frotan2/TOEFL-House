import { FormEvent, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { AppShell, PageStatus } from './ui';
import { createApiClient, type ApiClient } from './core/api';
import './app.css';
import './product-theme.css';

type RecordMap = Record<string, any> & { id: string; lifecycle_state?: string | null };
type Branch = { id: string; name: string };
type Person = { id: string; legal_name?: string | null };
type WorkspaceData = {
  assets: RecordMap[]; copies: RecordMap[]; issuances: RecordMap[]; work_orders: RecordMap[];
  open_custodies: RecordMap[]; disposal_requests: RecordMap[]; disposals: RecordMap[];
  book_branches: Branch[]; asset_branches: Branch[]; work_branches: Branch[]; borrowers: Person[]; custodians: Person[];
};

const today = () => new Date().toISOString().slice(0, 10);
const text = (value: unknown, fallback = '—') => typeof value === 'string' && value !== '' ? value : fallback;
const human = (value: unknown) => text(value, 'recorded').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const confirmAction = (message: string, irreversible = false) => window.confirm(irreversible ? `${message}\n\nThis action is irreversible.` : message);

function LibraryApp({ getJson, postJson, csrfToken }: ApiClient & { csrfToken: string }) {
  const [data, setData] = useState<WorkspaceData | null>(null);
  const [tab, setTab] = useState<'overview' | 'books' | 'assets' | 'work'>('overview');
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [bookForm, setBookForm] = useState({ code: '', title: '', acquired_on: today(), branch_id: '' });
  const [assetForm, setAssetForm] = useState({ code: '', name: '', category: '', location: '', acquired_on: today(), branch_id: '' });
  const [workForm, setWorkForm] = useState({ branch_id: '', facility_note: '', description: '' });

  const load = () => {
    setLoading(true); setError(null);
    void getJson<WorkspaceData>('/resources/workspace').then(setData).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Library and resource data could not be loaded.')).finally(() => setLoading(false));
  };
  useEffect(() => { load(); }, []);

  const command = (request: Promise<unknown>, success: string) => {
    setBusy(true); setError(null); setMessage(null);
    void request.then(() => { setMessage(success); load(); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The resource operation was rejected.')).finally(() => setBusy(false));
  };
  const act = (path: string, payload: Record<string, unknown>, success: string, confirm?: string, irreversible = false) => {
    if (confirm && !confirmAction(confirm, irreversible)) return;
    command(postJson(path, payload), success);
  };

  const assets = useMemo(() => filter(data?.assets ?? [], query), [data?.assets, query]);
  const copies = useMemo(() => filter(data?.copies ?? [], query), [data?.copies, query]);
  const issuances = useMemo(() => filter(data?.issuances ?? [], query), [data?.issuances, query]);
  const works = useMemo(() => filter(data?.work_orders ?? [], query), [data?.work_orders, query]);
  const branchName = (id?: string) => [...(data?.book_branches ?? []), ...(data?.asset_branches ?? []), ...(data?.work_branches ?? [])].find((b) => b.id === id)?.name ?? text(id, '—');
  const assetCustody = (assetId: string) => data?.open_custodies.find((c) => c.asset_id === assetId);

  const submitBook = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); command(postJson('/resources/books', bookForm), 'Book copy registered.'); };
  const submitAsset = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); command(postJson('/resources/assets', assetForm), 'Asset registered.'); };
  const submitWork = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); command(postJson('/resources/work-orders', workForm), 'Work order requested.'); };

  if (loading) return <><AppShell current="library" csrfToken={csrfToken} /><PageStatus>Loading authorized Library & Resources facts…</PageStatus></>;
  if (!data) return <><AppShell current="library" csrfToken={csrfToken} /><PageStatus>Library workspace unavailable.</PageStatus></>;

  const counts = [
    ['Book copies', data.copies.length], ['Open loans', data.issuances.filter((i) => i.lifecycle_state === 'issued').length],
    ['Assets', data.assets.length], ['Open custody', data.open_custodies.length],
    ['Pending disposal', data.disposal_requests.filter((d) => !['completed', 'executed', 'cancelled'].includes(String(d.lifecycle_state))).length],
    ['Work orders', data.work_orders.filter((w) => !['completed', 'cancelled'].includes(String(w.lifecycle_state))).length],
  ];

  return <>
    <AppShell current="library" csrfToken={csrfToken} />
    <main id="library-main" className="workspace" aria-labelledby="library-title">
      <header className="workspace-header"><div><p className="eyebrow">Operations · Library & Resources</p><h1 id="library-title">Books, assets, custody, and facilities.</h1><p className="lede">One workspace for the resource lifecycle. Server commands remain the authority for scope, state transitions, evidence and audit.</p></div><button className="button secondary" type="button" onClick={load}>Refresh facts</button></header>
      {error && <div className="alert" role="alert">{error}</div>}{message && <div className="notice" role="status">{message}</div>}
      <section className="summary-grid" aria-label="Resource facts">{counts.map(([label, value]) => <div className="panel" key={String(label)}><span className="metric">{String(value)}</span><span className="metric-label">{label}</span></div>)}</section>
      <div className="finance-guard"><strong>Authority boundary</strong><span>Branch visibility, lifecycle legality, custody rules, staged disposal approvals and book circulation constraints are enforced by the server.</span></div>
      <div className="student-tabs" role="tablist" aria-label="Resource work areas">
        {(['overview', 'books', 'assets', 'work'] as const).map((key) => <button key={key} role="tab" type="button" aria-selected={tab === key} aria-controls={`resource-${key}`} tabIndex={tab === key ? 0 : -1} className={tab === key ? 'active' : ''} onClick={() => setTab(key)}>{human(key)}</button>)}
      </div>
      <div className="toolbar"><div><label htmlFor="resource-search">Search resources</label><input id="resource-search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="code, title, state, facility…" /></div></div>

      {(tab === 'overview' || tab === 'books') && <section id="resource-books" role="tabpanel" className="panel" tabIndex={0}><div className="section-heading"><div><p className="eyebrow">Library circulation</p><h2>Book copies and current custody</h2></div><span className="source-note">Resources API · scope server-enforced</span></div><div className="compact-grid panel"><form onSubmit={submitBook}><h3>Register book copy</h3><label>Copy code<input required value={bookForm.code} onChange={(e) => setBookForm({ ...bookForm, code: e.target.value })} /></label><label>Title<input required value={bookForm.title} onChange={(e) => setBookForm({ ...bookForm, title: e.target.value })} /></label><label>Acquired on<input required type="date" value={bookForm.acquired_on} onChange={(e) => setBookForm({ ...bookForm, acquired_on: e.target.value })} /></label><label>Owning branch<select required value={bookForm.branch_id} onChange={(e) => setBookForm({ ...bookForm, branch_id: e.target.value })}><option value="">Select a branch…</option>{data.book_branches.map((b) => <option value={b.id} key={b.id}>{b.name}</option>)}</select></label><button className="button" type="submit" disabled={busy}>Register copy</button></form></div>
        {copies.length === 0 ? <p className="empty">No book copies match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Copy</th><th>Title</th><th>Branch</th><th>State</th><th>Action</th></tr></thead><tbody>{copies.map((copy) => <tr key={copy.id}><td><strong>{text(copy.code)}</strong></td><td>{text(copy.title)}</td><td>{branchName(copy.originating_branch_id)}</td><td><span className="status-chip">{human(copy.lifecycle_state)}</span></td><td><button className="button small" type="button" onClick={() => issueCopy(copy)} disabled={busy}>Issue</button></td></tr>)}</tbody></table></div>}
      </section>}

      {(tab === 'overview' || tab === 'assets') && <section id="resource-assets" role="tabpanel" className="panel" tabIndex={0}><div className="section-heading"><div><p className="eyebrow">Asset register</p><h2>Assets, custody and staged disposal</h2></div><span className="source-note">Immutable provenance · evidence-backed disposal</span></div><div className="compact-grid panel"><form onSubmit={submitAsset}><h3>Register asset</h3><label>Code<input required value={assetForm.code} onChange={(e) => setAssetForm({ ...assetForm, code: e.target.value })} /></label><label>Name<input required value={assetForm.name} onChange={(e) => setAssetForm({ ...assetForm, name: e.target.value })} /></label><label>Category<input required value={assetForm.category} onChange={(e) => setAssetForm({ ...assetForm, category: e.target.value })} /></label><label>Location<input required value={assetForm.location} onChange={(e) => setAssetForm({ ...assetForm, location: e.target.value })} /></label><label>Acquired on<input required type="date" value={assetForm.acquired_on} onChange={(e) => setAssetForm({ ...assetForm, acquired_on: e.target.value })} /></label><label>Owning branch<select required value={assetForm.branch_id} onChange={(e) => setAssetForm({ ...assetForm, branch_id: e.target.value })}><option value="">Select a branch…</option>{data.asset_branches.map((b) => <option value={b.id} key={b.id}>{b.name}</option>)}</select></label><button className="button" type="submit" disabled={busy}>Register asset</button></form></div>
        {assets.length === 0 ? <p className="empty">No assets match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Asset</th><th>Location</th><th>State</th><th>Custody</th><th>Actions</th></tr></thead><tbody>{assets.map((asset) => { const custody = assetCustody(asset.id); return <tr key={asset.id}><td><strong>{text(asset.code)}</strong><small>{text(asset.name)}</small></td><td>{text(asset.location)}</td><td><span className="status-chip">{human(asset.lifecycle_state)}</span></td><td>{custody ? text(custody.custodian_person_id) : 'Unassigned'}</td><td className="actions"><button className="button small" type="button" onClick={() => assignCustody(asset)} disabled={busy}>Assign custody</button>{custody && <button className="button small secondary" type="button" onClick={() => releaseCustody(asset)} disabled={busy}>Release</button>}<button className="button small secondary" type="button" onClick={() => dispose(asset)} disabled={busy}>Request disposal</button></td></tr>; })}</tbody></table></div>}
      </section>}

      {(tab === 'overview' || tab === 'work') && <section id="resource-work" role="tabpanel" className="panel" tabIndex={0}><div className="section-heading"><div><p className="eyebrow">Facilities</p><h2>Work orders with evidence</h2></div><span className="source-note">Request → approve → start → complete/cancel</span></div><form onSubmit={submitWork} className="panel compact-grid"><h3>Request facilities work</h3><label>Branch<select required value={workForm.branch_id} onChange={(e) => setWorkForm({ ...workForm, branch_id: e.target.value })}><option value="">Select a branch…</option>{data.work_branches.map((b) => <option value={b.id} key={b.id}>{b.name}</option>)}</select></label><label>Facility<input required value={workForm.facility_note} onChange={(e) => setWorkForm({ ...workForm, facility_note: e.target.value })} placeholder="Campus A / Room 4" /></label><label className="full-width">Description<textarea required value={workForm.description} onChange={(e) => setWorkForm({ ...workForm, description: e.target.value })} /></label><button className="button" type="submit" disabled={busy}>Request work</button></form>
        {works.length === 0 ? <p className="empty">No work orders match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Facility</th><th>Description</th><th>State</th><th>Action</th></tr></thead><tbody>{works.map((work) => <tr key={work.id}><td>{text(work.facility_note)}</td><td>{text(work.description)}</td><td><span className="status-chip">{human(work.lifecycle_state)}</span></td><td className="actions">{work.lifecycle_state === 'requested' && <button className="button small" type="button" onClick={() => act(`/resources/work-orders/${work.id}/approve`, {}, 'Work order approved.', 'Sign approval for this work order?')} disabled={busy}>Approve</button>}{work.lifecycle_state === 'approved' && <button className="button small" type="button" onClick={() => act(`/resources/work-orders/${work.id}/start`, {}, 'Work order started.', 'Start this work order?')} disabled={busy}>Start</button>}{work.lifecycle_state === 'in_progress' && <button className="button small" type="button" onClick={() => completeWork(work)} disabled={busy}>Complete</button>}{['requested', 'approved', 'in_progress'].includes(String(work.lifecycle_state)) && <button className="button small secondary" type="button" onClick={() => act(`/resources/work-orders/${work.id}/cancel`, {}, 'Work order cancelled.', 'Cancel this work order?', true)} disabled={busy}>Cancel</button>}</td></tr>)}</tbody></table></div>}
      </section>}

      {tab === 'overview' && <section className="panel"><div className="section-heading"><div><p className="eyebrow">Circulation register</p><h2>Loans and returns</h2></div><span className="source-note">Due dates and loss evidence remain server-owned facts.</span></div>{issuances.length === 0 ? <p className="empty">No circulation records match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Copy</th><th>Borrower</th><th>Issued</th><th>Due</th><th>State</th><th>Action</th></tr></thead><tbody>{issuances.map((loan) => <tr key={loan.id}><td>{text(loan.copy_id)}</td><td>{text(loan.borrower_person_id)}</td><td>{text(loan.issued_on)}</td><td>{text(loan.due_on)}</td><td><span className="status-chip">{human(loan.lifecycle_state)}</span></td><td className="actions">{loan.lifecycle_state === 'issued' && <><button className="button small" type="button" onClick={() => returnLoan(loan)} disabled={busy}>Return</button><button className="button small secondary" type="button" onClick={() => reportLoss(loan)} disabled={busy}>Report loss</button></>}</td></tr>)}</tbody></table></div>}</section>}
    </main>
  </>;

  function issueCopy(copy: RecordMap) {
    const borrower = data.borrowers[0];
    if (!borrower) { setError('No verified borrower is available in your authorized branch scope.'); return; }
    const due = window.prompt('Due date (YYYY-MM-DD)', today());
    if (!due) return;
    act(`/resources/books/${copy.id}/issue`, { borrower_id: borrower.id, issued_on: today(), due_on: due }, 'Book issued.', `Issue ${text(copy.title)} to ${text(borrower.legal_name)}?`);
  }
  function returnLoan(loan: RecordMap) {
    const returned = window.prompt('Return date (YYYY-MM-DD)', today());
    if (!returned) return;
    act(`/resources/issuances/${loan.id}/return`, { returned_on: returned }, 'Book returned.', 'Record this book as returned?');
  }
  function reportLoss(loan: RecordMap) {
    const evidence = window.prompt('Loss evidence reference');
    if (!evidence) return;
    act(`/resources/issuances/${loan.id}/loss`, { loss_evidence: evidence }, 'Book loss recorded with evidence.', 'Record this circulation as lost?', true);
  }
  function assignCustody(asset: RecordMap) {
    const person = data.custodians[0];
    if (!person) { setError('No verified custodian is available in your authorized branch scope.'); return; }
    const assigned = window.prompt('Assignment date (YYYY-MM-DD)', today());
    if (!assigned) return;
    act(`/resources/assets/${asset.id}/custody`, { custodian_id: person.id, assigned_on: assigned }, 'Custody assigned.', `Assign custody of ${text(asset.name)} to ${text(person.legal_name)}?`);
  }
  function releaseCustody(asset: RecordMap) {
    const released = window.prompt('Release date (YYYY-MM-DD)', today());
    if (!released) return;
    act(`/resources/assets/${asset.id}/custody/release`, { released_on: released }, 'Custody released.', `Release custody of ${text(asset.name)}?`);
  }
  function dispose(asset: RecordMap) {
    const method = window.prompt('Disposal method: sale, scrap, or donation', 'scrap');
    if (!method || !['sale', 'scrap', 'donation'].includes(method)) return;
    const reason = window.prompt('Reason for disposal');
    if (!reason) return;
    act(`/resources/assets/${asset.id}/disposal`, { method, reason }, 'Disposal request created.', `Create a staged disposal request for ${text(asset.name)}?`);
  }
  function completeWork(work: RecordMap) {
    const evidence = window.prompt('Completion evidence reference');
    if (!evidence) return;
    act(`/resources/work-orders/${work.id}/complete`, { evidence_ref: evidence }, 'Work order completed with evidence.', 'Complete this work order?');
  }
}

function filter(items: RecordMap[], query: string) {
  const term = query.trim().toLowerCase();
  if (!term) return items;
  return items.filter((item) => Object.values(item).some((value) => typeof value === 'string' && value.toLowerCase().includes(term)));
}

const root = document.getElementById('react-console');
if (root) {
  const csrfToken = root.getAttribute('data-csrf-token') ?? '';
  const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken });
  createRoot(root).render(<LibraryApp {...api} csrfToken={csrfToken} />);
}
