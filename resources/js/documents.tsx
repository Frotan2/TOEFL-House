import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type Person = { id: string; legal_name: string; branch_id: string };
type Classification = { id: string; category: string; owner_module: string; access_class: string };
type RetentionRule = { id: string; category: string; retention_days: number; legal_basis: string; operational_basis: string | null };
type DocumentRow = {
  id: string;
  subject_person_id: string;
  classification_id: string;
  title: string;
  lifecycle_state: string;
  available_actions: { submit: boolean; verify: boolean; activate: boolean; expire: boolean; archive: boolean; retention: boolean };
  created_at: string | null;
  updated_at: string | null;
};
type RetentionDecision = { id: string; document_id: string; rule_id: string; action: string; basis: string; decided_by: string; created_at: string | null };
type WorkspaceData = {
  scope: { branch_ids: string[] };
  available_actions: { classify: boolean; register: boolean; verify: boolean; retention: boolean };
  people: Person[];
  classifications: Classification[];
  retention_rules: RetentionRule[];
  documents: DocumentRow[];
  retention_decisions: RetentionDecision[];
  policy: { authority: string; history: string; correction: string };
};
type HistoryData = {
  document_id: string;
  lifecycle_state: string;
  versions: Array<{
    version_no: number;
    content_hash: string;
    uploaded_by: string;
    verifications: Array<{ verifier: string; result: string; reason: string; at: string | null }>;
  }>;
  policy: { authority: string; history: string; correction: string };
};
type ActiveForm = { kind: 'submit' | 'verify'; document: DocumentRow } | null;
type Props = ApiClient & { csrfToken: string };

const titleCase = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const compactId = (value: string) => value.length > 16 ? `${value.slice(0, 8)}…${value.slice(-5)}` : value;
const hashLabel = (value: string) => value.length > 26 ? `${value.slice(0, 18)}…${value.slice(-6)}` : value;
const recordedAt = (value: string | null) => {
  if (!value) return 'Recorded';
  const date = new Date(value);
  // Calendar authority: format using Kabul AFT fixed offset, not browser locale
  if (Number.isNaN(date.valueOf())) return value;
  try {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Kabul' }).format(date);
  } catch {
    return date.toISOString();
  }
};

/**
 * Documents is a browser projection over the canonical server commands. It
 * deliberately receives no storage references and renders the server's
 * state-legal `available_actions` matrix verbatim: the lifecycle table is
 * never re-derived in the browser, and command handlers re-authorize every
 * write.
 */
export function DocumentsApp({ getJson, postJson, csrfToken }: Props) {
  const [data, setData] = useState<WorkspaceData | null>(null);
  const [area, setArea] = useState<'registry' | 'register' | 'policy'>('registry');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [activeForm, setActiveForm] = useState<ActiveForm>(null);
  const [selectedDocumentId, setSelectedDocumentId] = useState<string | null>(null);
  const [history, setHistory] = useState<HistoryData | null>(null);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const [registration, setRegistration] = useState({ subject_person_id: '', classification_id: '', title: '', content_hash: '', storage_ref: '' });
  const [classification, setClassification] = useState({ category: '', owner_module: '', access_class: 'confidential' });
  const [retentionRule, setRetentionRule] = useState({ category: '', retention_days: '', legal_basis: '', operational_basis: '' });
  const [submission, setSubmission] = useState({ content_hash: '', storage_ref: '' });
  const [verification, setVerification] = useState({ result: 'pass', reason: '' });
  const commandPanelRef = useRef<HTMLElement | null>(null);
  const historyPanelRef = useRef<HTMLElement | null>(null);

  const load = (withSpinner = true) => {
    if (withSpinner) setLoading(true);
    setError(null);
    void getJson<{ data: WorkspaceData }>('/documents')
      .then((response) => {
        setData(response.data);
        setRegistration((current) => ({
          ...current,
          subject_person_id: response.data.people.some((person) => person.id === current.subject_person_id) ? current.subject_person_id : '',
          classification_id: response.data.classifications.some((item) => item.id === current.classification_id) ? current.classification_id : '',
        }));
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Document evidence could not be loaded.'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  // Dynamic command and evidence panels can be below dense table content.
  // Move keyboard focus deliberately after they mount instead of making a
  // screen-reader or keyboard operator hunt for the newly opened work area.
  useEffect(() => {
    if (!activeForm) return;
    const handle = window.setTimeout(() => {
      commandPanelRef.current?.scrollIntoView({ block: 'nearest' });
      commandPanelRef.current?.focus();
    }, 0);

    return () => window.clearTimeout(handle);
  }, [activeForm]);
  useEffect(() => {
    if (!history && !historyError) return;
    const handle = window.setTimeout(() => {
      historyPanelRef.current?.scrollIntoView({ block: 'nearest' });
      historyPanelRef.current?.focus();
    }, 0);

    return () => window.clearTimeout(handle);
  }, [history, historyError]);

  const run = (request: Promise<unknown>, success: string, next?: () => void) => {
    setBusy(true);
    setError(null);
    setNotice(null);
    void request
      .then(() => {
        next?.();
        setNotice(success);
        load(false);
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The document operation was rejected.'))
      .finally(() => setBusy(false));
  };

  const peopleById = useMemo(() => new Map((data?.people ?? []).map((person) => [person.id, person])), [data]);
  const classificationsById = useMemo(() => new Map((data?.classifications ?? []).map((item) => [item.id, item])), [data]);
  const filteredDocuments = useMemo(() => {
    const term = query.trim().toLocaleLowerCase();
    if (!term) return data?.documents ?? [];
    return (data?.documents ?? []).filter((document) => {
      const person = peopleById.get(document.subject_person_id)?.legal_name ?? '';
      const category = classificationsById.get(document.classification_id)?.category ?? '';
      return [document.title, document.lifecycle_state, person, category].some((value) => value.toLocaleLowerCase().includes(term));
    });
  }, [data?.documents, query, peopleById, classificationsById]);

  const openHistory = (document: DocumentRow) => {
    setSelectedDocumentId(document.id);
    setHistory(null);
    setHistoryError(null);
    setHistoryLoading(true);
    void getJson<{ data: HistoryData }>(`/documents/${encodeURIComponent(document.id)}/history`)
      .then((response) => setHistory(response.data))
      .catch((reason: unknown) => setHistoryError(reason instanceof Error ? reason.message : 'The immutable history could not be loaded.'))
      .finally(() => setHistoryLoading(false));
  };

  const submitRegistration = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(postJson('/documents', registration), 'Document registered as a draft with immutable version 1.', () => {
      setRegistration({ subject_person_id: '', classification_id: '', title: '', content_hash: '', storage_ref: '' });
      setArea('registry');
    });
  };
  const submitClassification = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(postJson('/documents/classifications', classification), 'Classification defined and audit-recorded.', () => setClassification({ category: '', owner_module: '', access_class: 'confidential' }));
  };
  const submitRetentionRule = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(postJson('/documents/retention-rules', { ...retentionRule, retention_days: Number(retentionRule.retention_days) }), 'Retention rule defined and audit-recorded.', () => setRetentionRule({ category: '', retention_days: '', legal_basis: '', operational_basis: '' }));
  };
  const submitVersion = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!activeForm || activeForm.kind !== 'submit') return;
    run(postJson(`/documents/${encodeURIComponent(activeForm.document.id)}/submit`, submission), 'New immutable version submitted for review.', () => {
      setActiveForm(null);
      setSubmission({ content_hash: '', storage_ref: '' });
    });
  };
  const submitVerification = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!activeForm || activeForm.kind !== 'verify') return;
    run(postJson(`/documents/${encodeURIComponent(activeForm.document.id)}/verify`, verification), `Verification ${verification.result === 'pass' ? 'passed' : 'failed'} and evidence was recorded.`, () => {
      setActiveForm(null);
      setVerification({ result: 'pass', reason: '' });
    });
  };
  const transition = (document: DocumentRow, action: 'activate' | 'expire' | 'archive' | 'retention') => {
    const irreversible = action === 'archive';
    const label = action === 'retention' ? 'Record a retention decision using this document’s classification rule?' : `${titleCase(action)} “${document.title}”?`;
    if (!window.confirm(irreversible ? `${label}\n\nArchiving does not erase immutable evidence.` : label)) return;
    const messages = {
      activate: 'Document activated.',
      expire: 'Document expired; its immutable evidence remains available.',
      archive: 'Document archived; immutable evidence remains retained.',
      retention: 'Retention decision recorded from the current rule.',
    };
    run(postJson(`/documents/${encodeURIComponent(document.id)}/${action}`), messages[action]);
  };
  const actionForm = (kind: 'submit' | 'verify', document: DocumentRow) => {
    setActiveForm({ kind, document });
    if (kind === 'submit') setSubmission({ content_hash: '', storage_ref: '' });
    if (kind === 'verify') setVerification({ result: 'pass', reason: '' });
  };

  if (loading && !data) return <><AppShell current="documents" csrfToken={csrfToken} /><PageStatus>Loading server-authorized document evidence…</PageStatus></>;
  if (!data) return <><AppShell current="documents" csrfToken={csrfToken} /><main id="workspace-main" className="workspace" aria-labelledby="documents-title"><header className="workspace-header"><div><p className="eyebrow">Operations · Documents & Evidence</p><h1 id="documents-title">Documents workspace unavailable</h1><p className="lede">No document facts were displayed because the server did not authorize a workspace projection.</p></div></header><div className="alert" role="alert">{error ?? 'Try refreshing the authorized workspace.'}</div><button type="button" className="button" onClick={() => load()}>Retry</button></main></>;

  const workspace = data;
  const pending = workspace.documents.filter((document) => ['draft', 'submitted', 'rejected', 'verified'].includes(document.lifecycle_state)).length;
  const active = workspace.documents.filter((document) => document.lifecycle_state === 'active').length;
  const retentionActions = workspace.retention_decisions.length;
  const tabLabels: Array<{ id: 'registry' | 'register' | 'policy'; label: string }> = [
    { id: 'registry', label: 'Evidence registry' },
    { id: 'register', label: 'Register document' },
    { id: 'policy', label: 'Classification & retention' },
  ];

  return <>
    <AppShell current="documents" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace documents-workspace" aria-labelledby="documents-title">
      <header className="workspace-header">
        <div><p className="eyebrow">Operations · Documents & Evidence</p><h1 id="documents-title">Evidence with an immutable chain of custody.</h1><p className="lede">Document authority, branch scope, lifecycle legality, verifier/uploader separation, idempotency, and audit evidence are enforced by the server on every command.</p></div>
        <div className="inline-actions"><span className="scope-badge">{workspace.scope.branch_ids.length} authorized branch{workspace.scope.branch_ids.length === 1 ? '' : 'es'}</span><button type="button" className="button secondary" onClick={() => load(false)} disabled={loading || busy}>{loading ? 'Refreshing…' : 'Refresh facts'}</button></div>
      </header>
      {error && <div className="alert" role="alert">{error}</div>}
      {notice && <div className="notice" role="status">{notice}</div>}

      <section className="summary-grid workspace-summary" aria-label="Document evidence summary">
        <div className="panel"><span className="metric">{workspace.documents.length}</span><span className="metric-label">Visible documents</span><small className="metric-detail">Scoped server projection</small></div>
        <div className="panel"><span className="metric">{pending}</span><span className="metric-label">In lifecycle review</span><small className="metric-detail">Draft through verified</small></div>
        <div className="panel"><span className="metric">{active}</span><span className="metric-label">Active documents</span><small className="metric-detail">Evidence remains append-only</small></div>
        <div className="panel"><span className="metric">{retentionActions}</span><span className="metric-label">Retention decisions</span><small className="metric-detail">No destructive deletion</small></div>
      </section>

      <section className="finance-guard documents-authority" aria-label="Authority boundary"><strong>Authority boundary</strong><span>Interface controls are server-projected affordances, not permission grants. The server independently re-checks every target, state transition, and separation-of-duties rule.</span></section>

      <div className="student-tabs" role="tablist" aria-label="Document work areas">
        {tabLabels.map((item) => <button key={item.id} id={`documents-tab-${item.id}`} role="tab" type="button" aria-selected={area === item.id} aria-controls={`documents-panel-${item.id}`} tabIndex={area === item.id ? 0 : -1} className={area === item.id ? 'active' : ''} onClick={() => setArea(item.id)}>{item.label}</button>)}
      </div>

      {area === 'registry' && <section id="documents-panel-registry" role="tabpanel" aria-labelledby="documents-tab-registry" className="panel" tabIndex={0}>
        <div className="section-heading"><div><p className="eyebrow">Evidence registry</p><h2>Documents in your authorized scope</h2><p className="form-help">Select History to review immutable versions and verification evidence. Storage references are intentionally never sent to this workspace.</p></div><span className="source-note">Server-authorized projection</span></div>
        <div className="search documents-search"><label htmlFor="document-search">Find evidence</label><input id="document-search" type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Title, subject, classification, or lifecycle…" /></div>
        {filteredDocuments.length === 0 ? <p className="empty">No authorized documents match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Document</th><th>Subject</th><th>Classification</th><th>Lifecycle</th><th>Recorded</th><th>Actions</th></tr></thead><tbody>{filteredDocuments.map((document) => {
          const person = peopleById.get(document.subject_person_id);
          const documentClass = classificationsById.get(document.classification_id);
          return <tr key={document.id}><td><strong>{document.title}</strong><small className="documents-id">{compactId(document.id)}</small></td><td>{person?.legal_name ?? compactId(document.subject_person_id)}<small className="documents-id">{person?.branch_id ?? 'Scoped record'}</small></td><td>{documentClass?.category ?? compactId(document.classification_id)}<small className="documents-id">{documentClass?.access_class ?? 'classification unavailable'}</small></td><td><span className="status-chip">{titleCase(document.lifecycle_state)}</span></td><td>{recordedAt(document.updated_at ?? document.created_at)}</td><td><div className="action-strip documents-actions"><button type="button" className="button small secondary" onClick={() => openHistory(document)} disabled={busy}>History</button>{document.available_actions.submit && <button type="button" className="button small" onClick={() => actionForm('submit', document)} disabled={busy}>Submit version</button>}{document.available_actions.verify && <button type="button" className="button small" onClick={() => actionForm('verify', document)} disabled={busy}>Verify</button>}{document.available_actions.activate && <button type="button" className="button small" onClick={() => transition(document, 'activate')} disabled={busy}>Activate</button>}{document.available_actions.expire && <button type="button" className="button small secondary" onClick={() => transition(document, 'expire')} disabled={busy}>Expire</button>}{document.available_actions.archive && <button type="button" className="button small secondary" onClick={() => transition(document, 'archive')} disabled={busy}>Archive</button>}{document.available_actions.retention && <button type="button" className="button small secondary" onClick={() => transition(document, 'retention')} disabled={busy}>Retention</button>}</div></td></tr>;
        })}</tbody></table></div>}
      </section>}

      {area === 'register' && <section id="documents-panel-register" role="tabpanel" aria-labelledby="documents-tab-register" className="panel" tabIndex={0}>
        <div className="section-heading"><div><p className="eyebrow">New evidence</p><h2>Register a document</h2><p className="form-help">Registration creates a draft and immutable version 1. The storage reference is accepted only for the write command and is never returned in read projections.</p></div><span className="source-note">Subject branch scope rechecked on submit</span></div>
        {!workspace.available_actions.register ? <div className="alert" role="status">Your current server-authorized scope does not include document registration. Existing evidence may still be visible for verification or retention work.</div> : workspace.people.length === 0 ? <div className="alert" role="status">No verified people are available in the server-authorized registration scope.</div> : workspace.classifications.length === 0 ? <div className="alert" role="status">A classification must be defined before a document can be registered.{workspace.available_actions.classify ? ' Use the Classification & retention area to define one.' : ''}</div> : <form className="form-grid" onSubmit={submitRegistration}>
          <label>Subject<select required value={registration.subject_person_id} onChange={(event) => setRegistration({ ...registration, subject_person_id: event.target.value })}><option value="">Select a verified subject…</option>{workspace.people.map((person) => <option key={person.id} value={person.id}>{person.legal_name}</option>)}</select></label>
          <label>Classification<select required value={registration.classification_id} onChange={(event) => setRegistration({ ...registration, classification_id: event.target.value })}><option value="">Select a classification…</option>{workspace.classifications.map((item) => <option key={item.id} value={item.id}>{item.category} · {titleCase(item.access_class)}</option>)}</select></label>
          <label>Document title<input required maxLength={255} value={registration.title} onChange={(event) => setRegistration({ ...registration, title: event.target.value })} placeholder="e.g. Identity document — September 2026" /></label>
          <label>Content hash<input required maxLength={255} value={registration.content_hash} onChange={(event) => setRegistration({ ...registration, content_hash: event.target.value })} placeholder="Integrity fingerprint" aria-describedby="content-hash-help" /><small id="content-hash-help" className="form-help">Use the authoritative content fingerprint, not a storage URL.</small></label>
          <label className="full-width">Storage reference<input required maxLength={500} value={registration.storage_ref} onChange={(event) => setRegistration({ ...registration, storage_ref: event.target.value })} placeholder="Authorized evidence store reference" /><small className="form-help">This confidential locator is transmitted only to the command and cannot be recovered through this list or history view.</small></label>
          <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Registering…' : 'Register immutable version 1'}</button><span className="form-help">The server records the registrar, branch context, classification, and audit correlation.</span></div>
        </form>}
      </section>}

      {area === 'policy' && <section id="documents-panel-policy" role="tabpanel" aria-labelledby="documents-tab-policy" className="documents-policy" tabIndex={0}>
        <section className="panel"><div className="section-heading"><div><p className="eyebrow">Policy catalog</p><h2>Classifications & retention rules</h2><p className="form-help">Policy definitions need organization-rooted classification authority. Branch grants alone are not treated as organization-wide authority.</p></div><span className="source-note">{titleCase(workspace.policy.authority)}</span></div>
          {!workspace.available_actions.classify && <div className="alert" role="status">Policy definitions are unavailable in your current server-authorized scope. Existing classifications and rules are shown as reference data.</div>}
          {workspace.available_actions.classify && <div className="documents-policy-forms"><form className="panel documents-inset" onSubmit={submitClassification}><h3>Define classification</h3><label>Category<input required maxLength={120} value={classification.category} onChange={(event) => setClassification({ ...classification, category: event.target.value })} placeholder="e.g. identity_evidence" /></label><label>Owning module<input required maxLength={120} value={classification.owner_module} onChange={(event) => setClassification({ ...classification, owner_module: event.target.value })} placeholder="e.g. admissions" /></label><label>Access class<select value={classification.access_class} onChange={(event) => setClassification({ ...classification, access_class: event.target.value })}><option value="public">Public</option><option value="internal">Internal</option><option value="confidential">Confidential</option><option value="restricted">Restricted</option></select></label><button type="submit" className="button" disabled={busy}>{busy ? 'Saving…' : 'Define classification'}</button></form>
            <form className="panel documents-inset" onSubmit={submitRetentionRule}><h3>Define retention rule</h3><label>Category<input required maxLength={120} value={retentionRule.category} onChange={(event) => setRetentionRule({ ...retentionRule, category: event.target.value })} placeholder="Match a classification category" /></label><label>Retention days<input required type="number" min="1" step="1" value={retentionRule.retention_days} onChange={(event) => setRetentionRule({ ...retentionRule, retention_days: event.target.value })} /></label><label>Legal basis<input required maxLength={500} value={retentionRule.legal_basis} onChange={(event) => setRetentionRule({ ...retentionRule, legal_basis: event.target.value })} placeholder="Applicable legal basis" /></label><label>Operational basis <span className="muted">(optional)</span><input maxLength={500} value={retentionRule.operational_basis} onChange={(event) => setRetentionRule({ ...retentionRule, operational_basis: event.target.value })} placeholder="Operational rationale" /></label><button type="submit" className="button" disabled={busy}>{busy ? 'Saving…' : 'Define retention rule'}</button></form></div>}
        </section>
        <section className="panel"><div className="section-heading"><div><p className="eyebrow">Classification registry</p><h2>Current document classes</h2></div></div>{workspace.classifications.length === 0 ? <p className="empty">No classifications are currently defined.</p> : <div className="table-wrap"><table><thead><tr><th>Category</th><th>Owner module</th><th>Access class</th></tr></thead><tbody>{workspace.classifications.map((item) => <tr key={item.id}><td><strong>{item.category}</strong><small className="documents-id">{compactId(item.id)}</small></td><td>{item.owner_module}</td><td><span className="status-chip">{titleCase(item.access_class)}</span></td></tr>)}</tbody></table></div>}</section>
        <section className="panel"><div className="section-heading"><div><p className="eyebrow">Retention registry</p><h2>Current policy rules</h2></div><span className="source-note">Archive replaces deletion</span></div>{workspace.retention_rules.length === 0 ? <p className="empty">No retention rules are currently defined.</p> : <div className="table-wrap"><table><thead><tr><th>Category</th><th>Period</th><th>Legal basis</th><th>Operational basis</th></tr></thead><tbody>{workspace.retention_rules.map((rule) => <tr key={rule.id}><td><strong>{rule.category}</strong><small className="documents-id">{compactId(rule.id)}</small></td><td>{rule.retention_days} day{rule.retention_days === 1 ? '' : 's'}</td><td>{rule.legal_basis}</td><td>{rule.operational_basis ?? '—'}</td></tr>)}</tbody></table></div>}</section>
      </section>}

      {activeForm && <section ref={commandPanelRef} className="panel documents-command-panel" aria-labelledby="document-command-title" aria-live="polite" tabIndex={-1}><div className="section-heading"><div><p className="eyebrow">Lifecycle command</p><h2 id="document-command-title">{activeForm.kind === 'submit' ? 'Submit a new immutable version' : 'Record verification evidence'}</h2><p className="form-help">{activeForm.document.title} · {titleCase(activeForm.document.lifecycle_state)}. The server rechecks target scope and lifecycle state when you submit.</p></div><button type="button" className="text-button" onClick={() => setActiveForm(null)} disabled={busy}>Cancel</button></div>
        {activeForm.kind === 'submit' ? <form className="form-grid" onSubmit={submitVersion}><label>Content hash<input required maxLength={255} value={submission.content_hash} onChange={(event) => setSubmission({ ...submission, content_hash: event.target.value })} placeholder="New immutable content fingerprint" /></label><label>Storage reference<input required maxLength={500} value={submission.storage_ref} onChange={(event) => setSubmission({ ...submission, storage_ref: event.target.value })} placeholder="Authorized evidence store reference" /></label><div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Submitting…' : 'Submit new version'}</button><span className="form-help">A rejected document may only be corrected with a new version; existing evidence is never overwritten.</span></div></form> : <form className="form-grid" onSubmit={submitVerification}><label>Verification result<select value={verification.result} onChange={(event) => setVerification({ ...verification, result: event.target.value })}><option value="pass">Pass</option><option value="fail">Fail</option></select></label><label className="full-width">Evidence reason<textarea required maxLength={1000} value={verification.reason} onChange={(event) => setVerification({ ...verification, reason: event.target.value })} placeholder="State the verification basis and outcome…" /></label><div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Recording…' : `Record ${verification.result === 'pass' ? 'passing' : 'failed'} verification`}</button><span className="form-help">The command prevents a version uploader from verifying that same version.</span></div></form>}
      </section>}

      {selectedDocumentId && <section ref={historyPanelRef} className="panel documents-history" aria-labelledby="documents-history-title" aria-live="polite" tabIndex={-1}><div className="section-heading"><div><p className="eyebrow">Immutable chain of custody</p><h2 id="documents-history-title">{workspace.documents.find((document) => document.id === selectedDocumentId)?.title ?? 'Document history'}</h2><p className="form-help">Version records and verification evidence are projected without storage references.</p></div><button type="button" className="text-button" onClick={() => { setSelectedDocumentId(null); setHistory(null); setHistoryError(null); }}>Close history</button></div>{historyLoading && <p className="form-help">Loading immutable history…</p>}{historyError && <div className="alert" role="alert">{historyError}</div>}{history && <><p className="form-help">Current lifecycle: <strong>{titleCase(history.lifecycle_state)}</strong> · {titleCase(history.policy.history)}</p>{history.versions.length === 0 ? <p className="empty">No versions were returned for this authorized document.</p> : <ol className="documents-timeline">{history.versions.map((version) => <li key={version.version_no}><div><strong>Version {version.version_no}</strong><span className="documents-id">Hash {hashLabel(version.content_hash)} · uploaded by {compactId(version.uploaded_by)}</span></div>{version.verifications.length === 0 ? <p className="form-help">No verification is recorded for this version.</p> : <ul>{version.verifications.map((item, index) => <li key={`${item.verifier}-${item.at ?? index}`}><span className="status-chip">{titleCase(item.result)}</span> <strong>{compactId(item.verifier)}</strong> — {item.reason} <span className="muted">{item.at ?? 'Recorded'}</span></li>)}</ul>}</li>)}</ol>}</>}</section>}

      <section className="panel"><div className="section-heading"><div><p className="eyebrow">Retention evidence</p><h2>Recent recorded decisions</h2></div><span className="source-note">Append-only policy outcomes</span></div>{workspace.retention_decisions.length === 0 ? <p className="empty">No retention decisions are visible in this authorized scope.</p> : <div className="table-wrap"><table><thead><tr><th>Document</th><th>Outcome</th><th>Basis</th><th>Decided by</th><th>Recorded</th></tr></thead><tbody>{workspace.retention_decisions.slice(0, 100).map((decision) => <tr key={decision.id}><td>{workspace.documents.find((document) => document.id === decision.document_id)?.title ?? compactId(decision.document_id)}</td><td><span className="status-chip">{titleCase(decision.action)}</span></td><td>{decision.basis}</td><td>{compactId(decision.decided_by)}</td><td>{recordedAt(decision.created_at)}</td></tr>)}</tbody></table></div>}</section>
    </main>
  </>;
}
