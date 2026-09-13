import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import type { ApiClient } from './core/api';
import './app.css';
import './product-theme.css';

type Person = { id: string; legal_name: string; branch_id: string };
type Purpose = { id: string; name: string; channel: string; category: string };
type ConsentActions = { submit: boolean; verify: boolean; activate: boolean; expire: boolean; revoke: boolean; archive: boolean };
type ConsentRow = {
  id: string;
  subject_person_id: string;
  purpose_id: string;
  lifecycle_state: string;
  effective_from: string;
  effective_to: string | null;
  available_actions: ConsentActions;
  created_at: string | null;
  updated_at: string | null;
};
type RevocationRow = { id: string; consent_id: string; revoked_by: string; scope: string; effect: string; created_at: string | null };
type DisclosureRow = { id: string; subject_person_id: string; recipient: string; purpose: string; authority: string; scope: string; disclosed_category: string; disclosed_by: string; created_at: string | null };
type ExportRow = {
  id: string;
  subject_person_id: string;
  purpose: string;
  organization_id: string;
  lifecycle_state: string;
  requested_by: string;
  approver_one_id: string | null;
  approver_two_id: string | null;
  exported_by: string | null;
  disclosure_id: string | null;
  available_actions: { approve: boolean; execute: boolean };
  created_at: string | null;
  updated_at: string | null;
};
type WorkspaceData = {
  scope: { branch_ids: string[] };
  available_actions: { define_purpose: boolean; consent: boolean; disclose: boolean; export: boolean; approve_bulk_export: boolean };
  people: Person[];
  purposes: Purpose[];
  consents: ConsentRow[];
  revocations: RevocationRow[];
  disclosures: DisclosureRow[];
  export_requests: ExportRow[];
  policy: { authority: string; history: string; correction: string; erasure: string };
};
type DossierConsent = {
  consent_id: string;
  purpose_id: string;
  lifecycle_state: string;
  effective_from: string;
  effective_to: string | null;
  recorded_by: string;
  revocations: Array<{ revocation_id: string; revoked_by: string; scope: string; effect: string; at: string | null }>;
};
type Dossier = {
  subject_person_id: string;
  as_of: string;
  consents: DossierConsent[];
  consent_history: DossierConsent[];
  disclosures: Array<{ disclosure_id: string; recipient: string; purpose: string; authority: string; scope: string; disclosed_category: string; disclosed_by: string; at: string | null }>;
  export_requests: Array<{ request_id: string; purpose: string; organization_id: string; lifecycle_state: string; requested_by: string; approver_one_id: string | null; approver_two_id: string | null; exported_by: string | null; disclosure_id: string | null; closed: boolean; at: string | null }>;
  provenance: { organization_id: string; campus_id: string | null; branch_id: string | null; department_id: string | null };
  policy: { authority: string; history: string; correction: string; erasure: string };
};
type Receipt = { subject: string; purpose: string; disclosure_id: string; correlation_id: string; consents: number; disclosures: number };
type ActiveForm = { consent: ConsentRow } | null;
type Area = 'register' | 'subjects' | 'record' | 'release';
type Props = ApiClient & { csrfToken: string };

const titleCase = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const compactId = (value: string) => value.length > 16 ? `${value.slice(0, 8)}…${value.slice(-5)}` : value;
const recordedAt = (value: string | null) => {
  if (!value) return 'Recorded';
  const date = new Date(value);
  // Calendar authority: format in Kabul AFT (fixed offset), never the browser locale.
  if (Number.isNaN(date.valueOf())) return value;
  try {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Kabul' }).format(date);
  } catch {
    return date.toISOString();
  }
};

/**
 * Privacy & Consent is a browser projection over the canonical privacy
 * commands. It renders the server's state-legal `available_actions` verbatim:
 * the consent lifecycle table and the staged export chain are never re-derived
 * here, consent evidence locators are never sent to this workspace, and every
 * write re-enters the owning command, which re-checks authority, subject
 * provenance, lifecycle legality, separation of duties, idempotency and audit.
 *
 * Consequential acts (activation, expiry, archival, a bulk-export signature
 * and its execution) demand an explicit confirm; revocation demands its scope
 * and effect in an open command panel, because a withdrawal without them is
 * not evidence.
 */
export function PrivacyApp({ getJson, postJson, csrfToken }: Props) {
  const [data, setData] = useState<WorkspaceData | null>(null);
  const [area, setArea] = useState<Area>('register');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [activeForm, setActiveForm] = useState<ActiveForm>(null);
  const [revocation, setRevocation] = useState({ scope: '', effect: '' });
  const [selectedSubjectId, setSelectedSubjectId] = useState<string | null>(null);
  const [dossierAsOf, setDossierAsOf] = useState('');
  const [dossier, setDossier] = useState<Dossier | null>(null);
  const [dossierLoading, setDossierLoading] = useState(false);
  const [dossierError, setDossierError] = useState<string | null>(null);
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const [purpose, setPurpose] = useState({ name: '', channel: 'email', category: 'communication' });
  const [consent, setConsent] = useState({ subject_person_id: '', purpose_id: '', evidence_ref: '', effective_from: '', effective_to: '' });
  const [disclosure, setDisclosure] = useState({ subject_person_id: '', recipient: '', purpose: '', authority: 'privacy.disclose', scope_type: 'subject', disclosed_category: '' });
  const [release, setRelease] = useState({ subject_person_id: '', purpose: '', scope_type: 'subject' });
  const [bulkRequest, setBulkRequest] = useState({ subject_person_id: '', purpose: '' });
  const commandPanelRef = useRef<HTMLElement | null>(null);
  const dossierPanelRef = useRef<HTMLElement | null>(null);

  const load = (withSpinner = true) => {
    if (withSpinner) setLoading(true);
    setError(null);
    void getJson<{ data: WorkspaceData }>('/privacy/workspace')
      .then((response) => {
        setData(response.data);
        // A projection refresh must never leave a stale subject or purpose
        // selected: the server, not the browser, decides what is in scope.
        setConsent((current) => ({
          ...current,
          subject_person_id: response.data.people.some((person) => person.id === current.subject_person_id) ? current.subject_person_id : '',
          purpose_id: response.data.purposes.some((item) => item.id === current.purpose_id) ? current.purpose_id : '',
        }));
        setDisclosure((current) => ({ ...current, subject_person_id: response.data.people.some((person) => person.id === current.subject_person_id) ? current.subject_person_id : '' }));
        setRelease((current) => ({ ...current, subject_person_id: response.data.people.some((person) => person.id === current.subject_person_id) ? current.subject_person_id : '' }));
        setBulkRequest((current) => ({ ...current, subject_person_id: response.data.people.some((person) => person.id === current.subject_person_id) ? current.subject_person_id : '' }));
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Privacy evidence could not be loaded.'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  // The command and dossier panels mount below dense tables: move keyboard
  // focus deliberately instead of making an operator hunt for the new area.
  useEffect(() => {
    if (!activeForm) return;
    const handle = window.setTimeout(() => {
      commandPanelRef.current?.scrollIntoView({ block: 'nearest' });
      commandPanelRef.current?.focus();
    }, 0);

    return () => window.clearTimeout(handle);
  }, [activeForm]);
  useEffect(() => {
    if (!dossier && !dossierError) return;
    const handle = window.setTimeout(() => {
      dossierPanelRef.current?.scrollIntoView({ block: 'nearest' });
      dossierPanelRef.current?.focus();
    }, 0);

    return () => window.clearTimeout(handle);
  }, [dossier, dossierError]);

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
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The privacy operation was rejected.'))
      .finally(() => setBusy(false));
  };

  const peopleById = useMemo(() => new Map((data?.people ?? []).map((person) => [person.id, person])), [data]);
  const purposesById = useMemo(() => new Map((data?.purposes ?? []).map((item) => [item.id, item])), [data]);
  const subjectLabel = (personId: string) => peopleById.get(personId)?.legal_name ?? compactId(personId);
  const purposeLabel = (purposeId: string) => {
    const found = purposesById.get(purposeId);
    return found ? `${found.name} · ${titleCase(found.channel)}` : compactId(purposeId);
  };
  const filteredConsents = useMemo(() => {
    const term = query.trim().toLocaleLowerCase();
    if (!term) return data?.consents ?? [];
    return (data?.consents ?? []).filter((row) => [subjectLabel(row.subject_person_id), purposeLabel(row.purpose_id), row.lifecycle_state]
      .some((value) => value.toLocaleLowerCase().includes(term)));
  }, [data?.consents, query, peopleById, purposesById]);

  const openDossier = (subjectId: string) => {
    setSelectedSubjectId(subjectId);
    setDossier(null);
    setDossierError(null);
    setDossierLoading(true);
    const asOf = dossierAsOf.trim() === '' ? '' : `?as_of=${encodeURIComponent(dossierAsOf.trim())}`;
    void getJson<{ data: Dossier }>(`/privacy/subjects/${encodeURIComponent(subjectId)}${asOf}`)
      .then((response) => setDossier(response.data))
      .catch((reason: unknown) => setDossierError(reason instanceof Error ? reason.message : 'The subject dossier could not be loaded.'))
      .finally(() => setDossierLoading(false));
  };

  const consentTransition = (row: ConsentRow, action: 'submit' | 'verify' | 'activate' | 'expire' | 'archive') => {
    const confirmations: Record<typeof action, string | null> = {
      submit: null,
      verify: null,
      activate: `Activate the consent for “${subjectLabel(row.subject_person_id)}” (${purposeLabel(row.purpose_id)})?\n\nActivation is what makes it current use authority for personal data.`,
      expire: `Close this consent as expired?\n\nExpiry ends future use; the consent and its evidence are retained.`,
      archive: `Archive this consent?\n\nArchiving does not erase consent evidence.`,
    };
    const confirmation = confirmations[action];
    if (confirmation !== null && !window.confirm(confirmation)) return;
    const messages: Record<typeof action, string> = {
      submit: 'Consent submitted for verification.',
      verify: 'Consent verified against its recorded evidence.',
      activate: 'Consent active; it now carries current use authority.',
      expire: 'Consent expired; the record and its evidence are retained.',
      archive: 'Consent archived; consent history is retained.',
    };
    run(postJson(`/privacy/consents/${encodeURIComponent(row.id)}/${action}`), messages[action]);
  };

  const submitRevocation = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!activeForm) return;
    const row = activeForm.consent;
    run(
      postJson(`/privacy/consents/${encodeURIComponent(row.id)}/revoke`, revocation),
      'Consent revoked; the withdrawal is recorded as append-only evidence.',
      () => { setActiveForm(null); setRevocation({ scope: '', effect: '' }); },
    );
  };

  const submitPurpose = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(postJson('/privacy/purposes', purpose), 'Consent purpose defined and audit-recorded.', () => setPurpose({ name: '', channel: 'email', category: 'communication' }));
  };

  const submitConsent = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(
      postJson('/privacy/consents', { ...consent, effective_to: consent.effective_to === '' ? null : consent.effective_to }),
      'Consent recorded as a draft with its evidence; it takes effect once verified and activated.',
      () => setConsent({ subject_person_id: '', purpose_id: '', evidence_ref: '', effective_from: '', effective_to: '' }),
    );
  };

  const submitDisclosure = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const person = peopleById.get(disclosure.subject_person_id);
    if (!person) return;
    run(
      postJson('/privacy/disclosures', {
        subject_person_id: person.id,
        recipient: disclosure.recipient,
        purpose: disclosure.purpose,
        authority: disclosure.authority,
        scope_type: disclosure.scope_type,
        scope_id: disclosure.scope_type === 'subject' ? person.id : person.branch_id,
        disclosed_category: disclosure.disclosed_category,
      }),
      'Disclosure recorded as immutable release evidence.',
      () => setDisclosure({ subject_person_id: '', recipient: '', purpose: '', authority: 'privacy.disclose', scope_type: 'subject', disclosed_category: '' }),
    );
  };

  const exportDataset = (response: unknown, subjectId: string, purposeText: string) => {
    const payload = (response as { data?: { disclosure_id?: string; correlation_id?: string; dataset?: { consents?: unknown[]; disclosures?: unknown[] } } })?.data;
    setReceipt({
      subject: subjectLabel(subjectId),
      purpose: purposeText,
      disclosure_id: payload?.disclosure_id ?? 'unavailable',
      correlation_id: payload?.correlation_id ?? 'unavailable',
      consents: payload?.dataset?.consents?.length ?? 0,
      disclosures: payload?.dataset?.disclosures?.length ?? 0,
    });
  };

  const submitExport = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const person = peopleById.get(release.subject_person_id);
    if (!person) return;
    const scopeId = release.scope_type === 'subject' ? person.id : person.branch_id;
    setReceipt(null);
    setBusy(true);
    setError(null);
    setNotice(null);
    void postJson('/privacy/exports', { subject_person_id: person.id, purpose: release.purpose, scope_type: release.scope_type, scope_id: scopeId })
      .then((response) => {
        exportDataset(response, person.id, release.purpose);
        setNotice('Subject data exported; the disclosure is recorded as the evidence of the release.');
        setRelease({ subject_person_id: '', purpose: '', scope_type: 'subject' });
        load(false);
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The export was rejected.'))
      .finally(() => setBusy(false));
  };

  const submitBulkRequest = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    run(
      // The organization is resolved by the server from the subject's own
      // provenance; the browser never supplies a tenant.
      postJson('/privacy/exports/bulk', bulkRequest),
      'Organization-wide export requested; it executes only after two distinct approvers sign in their own sessions.',
      () => setBulkRequest({ subject_person_id: '', purpose: '' }),
    );
  };

  const signApproval = (row: ExportRow) => {
    if (!window.confirm(`Sign this organization-wide export request for “${subjectLabel(row.subject_person_id)}”?\n\nYour signature is recorded as an approver slot and cannot be withdrawn.`)) return;
    void postJson(`/privacy/exports/${encodeURIComponent(row.id)}/approve`)
      .then((response) => {
        const state = (response as { data?: { lifecycle_state?: string } })?.data?.lifecycle_state;
        setBusy(true);
        setNotice(state === 'approved'
          ? 'Second approval signed; the request is approved for execution.'
          : 'First approval signed; a distinct second approver must sign before execution.');
        setError(null);
        load(false);
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The approval was rejected.'))
      .finally(() => setBusy(false));
  };

  const executeExport = (row: ExportRow) => {
    if (!window.confirm(`Execute the approved organization-wide export for “${subjectLabel(row.subject_person_id)}”?\n\nThis releases personal data and records an immutable disclosure.`)) return;
    setReceipt(null);
    setBusy(true);
    setError(null);
    setNotice(null);
    void postJson(`/privacy/exports/${encodeURIComponent(row.id)}/execute`)
      .then((response) => {
        exportDataset(response, row.subject_person_id, row.purpose);
        setNotice('Export executed; the disclosure is recorded as the evidence of the release.');
        load(false);
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The export execution was rejected.'))
      .finally(() => setBusy(false));
  };

  if (loading && !data) return <><AppShell current="privacy" csrfToken={csrfToken} /><PageStatus>Loading server-authorized privacy evidence…</PageStatus></>;
  if (!data) {
    return <>
      <AppShell current="privacy" csrfToken={csrfToken} />
      <main id="workspace-main" className="workspace" aria-labelledby="privacy-title">
        <header className="workspace-header">
          <div><p className="eyebrow">Governance · Privacy & Consent</p><h1 id="privacy-title">Privacy workspace unavailable</h1><p className="lede">No privacy facts were displayed because the server did not authorize a workspace projection.</p></div>
        </header>
        <div className="alert" role="alert">{error ?? 'Try refreshing the authorized workspace.'}</div>
        <button type="button" className="button" onClick={() => load()}>Retry</button>
      </main>
    </>;
  }

  const workspace = data;
  const tabLabels: Array<{ id: Area; label: string }> = [
    { id: 'register', label: 'Consent register' },
    { id: 'subjects', label: 'Subject dossiers' },
    { id: 'record', label: 'Purpose & consent' },
    { id: 'release', label: 'Disclosure & export' },
  ];
  const selectedPerson = peopleById.get(selectedSubjectId ?? '') ?? null;
  const disclosurePerson = peopleById.get(disclosure.subject_person_id) ?? null;
  const releasePerson = peopleById.get(release.subject_person_id) ?? null;

  return <>
    <AppShell current="privacy" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace privacy-workspace" aria-labelledby="privacy-title">
      <header className="workspace-header">
        <div>
          <p className="eyebrow">Governance · Privacy & Consent</p>
          <h1 id="privacy-title">Consent, disclosure and subject-data release evidence.</h1>
          <p className="lede">Purpose authority, branch scope, consent lifecycle legality, two-distinct-approver export signatures, idempotency and audit evidence are enforced by the server on every command.</p>
        </div>
        <div className="inline-actions">
          <span className="scope-badge">{workspace.scope.branch_ids.length} authorized branch{workspace.scope.branch_ids.length === 1 ? '' : 'es'}</span>
          <button type="button" className="button secondary" onClick={() => load(false)} disabled={loading || busy}>{loading ? 'Refreshing…' : 'Refresh facts'}</button>
        </div>
      </header>
      {error && <div className="alert" role="alert">{error}</div>}
      {notice && <div className="notice" role="status">{notice}</div>}

      <section className="summary-grid workspace-summary" aria-label="Privacy evidence summary">
        <div className="panel"><span className="metric">{workspace.people.length}</span><span className="metric-label">Visible subjects</span><small className="metric-detail">Scoped server projection</small></div>
        <div className="panel"><span className="metric">{workspace.consents.length}</span><span className="metric-label">Consent records</span><small className="metric-detail">Lifecycle history retained</small></div>
        <div className="panel"><span className="metric">{workspace.disclosures.length}</span><span className="metric-label">Disclosure evidence</span><small className="metric-detail">Append-only release records</small></div>
        <div className="panel"><span className="metric">{workspace.export_requests.length}</span><span className="metric-label">Export requests</span><small className="metric-detail">Staged approval chain</small></div>
      </section>

      <section className="finance-guard privacy-authority" aria-label="Authority boundary"><strong>Authority boundary</strong><span>Interface controls are server-projected affordances, not permission grants. Consent evidence locators are never sent to this workspace, and the server independently re-checks every subject, state transition and export signature.</span></section>

      <div className="student-tabs" role="tablist" aria-label="Privacy work areas">
        {tabLabels.map((item) => <button key={item.id} id={`privacy-tab-${item.id}`} role="tab" type="button" aria-selected={area === item.id} aria-controls={`privacy-panel-${item.id}`} tabIndex={area === item.id ? 0 : -1} className={area === item.id ? 'active' : ''} onClick={() => setArea(item.id)}>{item.label}</button>)}
      </div>

      {area === 'register' && <section id="privacy-panel-register" role="tabpanel" aria-labelledby="privacy-tab-register" className="panel" tabIndex={0}>
        <div className="section-heading"><div><p className="eyebrow">Consent register</p><h2>Consents in your authorized scope</h2><p className="form-help">Select Dossier to review one subject's complete privacy record. Consent evidence references are intentionally never sent to this workspace.</p></div><span className="source-note">Server-authorized projection</span></div>
        <div className="search privacy-search"><label htmlFor="consent-search">Find consent</label><input id="consent-search" type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Subject, purpose, or lifecycle…" /></div>
        {filteredConsents.length === 0 ? <p className="empty">No authorized consents match the current filter.</p> : <div className="table-wrap"><table><thead><tr><th>Subject</th><th>Purpose</th><th>Effective window</th><th>Lifecycle</th><th>Actions</th></tr></thead><tbody>{filteredConsents.map((row) => <tr key={row.id}>
          <td>{subjectLabel(row.subject_person_id)}<small className="privacy-id">{compactId(row.subject_person_id)}</small></td>
          <td>{purposeLabel(row.purpose_id)}<small className="privacy-id">{compactId(row.purpose_id)}</small></td>
          <td>{row.effective_from} → {row.effective_to ?? 'open-ended'}</td>
          <td><span className="status-chip">{titleCase(row.lifecycle_state)}</span><small className="privacy-id">{compactId(row.id)}</small></td>
          <td><div className="action-strip privacy-actions">
            <button type="button" className="button small secondary" onClick={() => openDossier(row.subject_person_id)} disabled={busy}>Dossier</button>
            {row.available_actions.submit && <button type="button" className="button small" onClick={() => consentTransition(row, 'submit')} disabled={busy}>Submit</button>}
            {row.available_actions.verify && <button type="button" className="button small" onClick={() => consentTransition(row, 'verify')} disabled={busy}>Verify</button>}
            {row.available_actions.activate && <button type="button" className="button small" onClick={() => consentTransition(row, 'activate')} disabled={busy}>Activate</button>}
            {row.available_actions.expire && <button type="button" className="button small secondary" onClick={() => consentTransition(row, 'expire')} disabled={busy}>Expire</button>}
            {row.available_actions.revoke && <button type="button" className="button small secondary" onClick={() => { setActiveForm({ consent: row }); setRevocation({ scope: '', effect: '' }); }} disabled={busy}>Revoke</button>}
            {row.available_actions.archive && <button type="button" className="button small secondary" onClick={() => consentTransition(row, 'archive')} disabled={busy}>Archive</button>}
          </div></td>
        </tr>)}</tbody></table></div>}
        {workspace.revocations.length > 0 && <div className="privacy-revocations"><div className="section-heading"><div><p className="eyebrow">Withdrawal evidence</p><h2>Recorded revocations</h2></div><span className="source-note">Append-only</span></div><div className="table-wrap"><table><thead><tr><th>Consent</th><th>Withdrawn by</th><th>Scope</th><th>Effect</th><th>Recorded</th></tr></thead><tbody>{workspace.revocations.slice(0, 50).map((row) => <tr key={row.id}>
          <td>{purposeLabel(workspace.consents.find((item) => item.id === row.consent_id)?.purpose_id ?? '')}<small className="privacy-id">{compactId(row.consent_id)}</small></td>
          <td>{compactId(row.revoked_by)}</td><td>{row.scope}</td><td>{row.effect}</td><td>{recordedAt(row.created_at)}</td>
        </tr>)}</tbody></table></div></div>}
      </section>}

      {area === 'subjects' && <section id="privacy-panel-subjects" role="tabpanel" aria-labelledby="privacy-tab-subjects" className="panel" tabIndex={0}>
        <div className="section-heading"><div><p className="eyebrow">Subjects</p><h2>People in your authorized scope</h2><p className="form-help">A dossier is a separately authorized read of one subject: current use authority, complete consent history with withdrawal evidence, disclosures and export chain.</p></div><span className="source-note">{workspace.people.length} visible</span></div>
        {workspace.people.length === 0 ? <p className="empty">No verified people are visible in your server-authorized scope.</p> : <div className="table-wrap"><table><thead><tr><th>Subject</th><th>Branch</th><th>Consents</th><th>Disclosures</th><th>Actions</th></tr></thead><tbody>{workspace.people.map((person) => <tr key={person.id}>
          <td><strong>{person.legal_name}</strong><small className="privacy-id">{compactId(person.id)}</small></td>
          <td>{compactId(person.branch_id)}</td>
          <td>{workspace.consents.filter((row) => row.subject_person_id === person.id).length}</td>
          <td>{workspace.disclosures.filter((row) => row.subject_person_id === person.id).length}</td>
          <td><div className="action-strip privacy-actions"><button type="button" className="button small secondary" onClick={() => openDossier(person.id)} disabled={busy}>Open dossier</button></div></td>
        </tr>)}</tbody></table></div>}
      </section>}

      {area === 'record' && <section id="privacy-panel-record" role="tabpanel" aria-labelledby="privacy-tab-record" className="privacy-record" tabIndex={0}>
        <section className="panel">
          <div className="section-heading"><div><p className="eyebrow">Purpose catalog</p><h2>Defined purposes of personal-data use</h2><p className="form-help">Communication and marketing purposes are separate definitions and are never conflated. Purpose definition needs organization-rooted authority.</p></div><span className="source-note">{titleCase(workspace.policy.authority)}</span></div>
          {!workspace.available_actions.define_purpose ? <div className="alert" role="status">Purpose definitions are unavailable in your current server-authorized scope. The existing catalog is shown as reference data.</div> : <form className="form-grid privacy-inset" onSubmit={submitPurpose}>
            <h3>Define consent purpose</h3>
            <label>Purpose name<input required maxLength={200} value={purpose.name} onChange={(event) => setPurpose({ ...purpose, name: event.target.value })} placeholder="e.g. enrollment-updates" /></label>
            <label>Channel<select value={purpose.channel} onChange={(event) => setPurpose({ ...purpose, channel: event.target.value })}><option value="email">Email</option><option value="sms">SMS</option><option value="phone">Phone</option><option value="post">Post</option></select></label>
            <label>Category<select value={purpose.category} onChange={(event) => setPurpose({ ...purpose, category: event.target.value })}><option value="communication">Communication</option><option value="marketing">Marketing</option><option value="statutory">Statutory</option><option value="operations">Operations</option></select></label>
            <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Saving…' : 'Define purpose'}</button><span className="form-help">The definition is audit-recorded with its channel and category.</span></div>
          </form>}
          {workspace.purposes.length === 0 ? <p className="empty">No consent purposes are currently defined.</p> : <div className="table-wrap"><table><thead><tr><th>Name</th><th>Channel</th><th>Category</th></tr></thead><tbody>{workspace.purposes.map((item) => <tr key={item.id}>
            <td><strong>{item.name}</strong><small className="privacy-id">{compactId(item.id)}</small></td>
            <td><span className="status-chip">{titleCase(item.channel)}</span></td><td>{titleCase(item.category)}</td>
          </tr>)}</tbody></table></div>}
        </section>

        <section className="panel">
          <div className="section-heading"><div><p className="eyebrow">Consent capture</p><h2>Record a consent</h2><p className="form-help">A consent requires a verified subject, a defined purpose and its evidence. It is born a draft and carries use authority only after verification and activation.</p></div><span className="source-note">Subject branch scope rechecked on submit</span></div>
          {!workspace.available_actions.consent ? <div className="alert" role="status">Your current server-authorized scope does not include recording consent for another person.</div> : workspace.people.length === 0 ? <div className="alert" role="status">No verified people are available in the server-authorized consent scope.</div> : workspace.purposes.length === 0 ? <div className="alert" role="status">A consent purpose must be defined before consent can be recorded.</div> : <form className="form-grid" onSubmit={submitConsent}>
            <label>Subject<select required value={consent.subject_person_id} onChange={(event) => setConsent({ ...consent, subject_person_id: event.target.value })}><option value="">Select a verified subject…</option>{workspace.people.map((person) => <option key={person.id} value={person.id}>{person.legal_name}</option>)}</select></label>
            <label>Purpose<select required value={consent.purpose_id} onChange={(event) => setConsent({ ...consent, purpose_id: event.target.value })}><option value="">Select a defined purpose…</option>{workspace.purposes.map((item) => <option key={item.id} value={item.id}>{item.name} · {titleCase(item.channel)}</option>)}</select></label>
            <label>Effective from<input required type="date" value={consent.effective_from} onChange={(event) => setConsent({ ...consent, effective_from: event.target.value })} /></label>
            <label>Effective to <span className="muted">(optional)</span><input type="date" value={consent.effective_to} onChange={(event) => setConsent({ ...consent, effective_to: event.target.value })} aria-describedby="consent-window-help" /><small id="consent-window-help" className="form-help">An open-ended consent can only be revoked or archived; it never expires by date.</small></label>
            <label className="full-width">Evidence reference<input required maxLength={500} value={consent.evidence_ref} onChange={(event) => setConsent({ ...consent, evidence_ref: event.target.value })} placeholder="e.g. signed-form/2026-09-13" aria-describedby="consent-evidence-help" /><small id="consent-evidence-help" className="form-help">This confidential locator is transmitted only to the command and cannot be recovered through any privacy read model.</small></label>
            <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Recording…' : 'Record consent draft'}</button><span className="form-help">The server records the recorder, subject provenance, window and audit correlation.</span></div>
          </form>}
        </section>
      </section>}

      {area === 'release' && <section id="privacy-panel-release" role="tabpanel" aria-labelledby="privacy-tab-release" className="privacy-release" tabIndex={0}>
        <section className="panel">
          <div className="section-heading"><div><p className="eyebrow">Release evidence</p><h2>Record a disclosure</h2><p className="form-help">A disclosure is immutable evidence that personal information was released: recipient, purpose, authority, declared scope and category. The declared scope must stay inside the subject's own provenance.</p></div><span className="source-note">{titleCase(workspace.policy.history)}</span></div>
          {!workspace.available_actions.disclose ? <div className="alert" role="status">Your current server-authorized scope does not include recording disclosures.</div> : workspace.people.length === 0 ? <div className="alert" role="status">No verified people are available in the server-authorized disclosure scope.</div> : <form className="form-grid" onSubmit={submitDisclosure}>
            <label>Subject<select required value={disclosure.subject_person_id} onChange={(event) => setDisclosure({ ...disclosure, subject_person_id: event.target.value })}><option value="">Select a verified subject…</option>{workspace.people.map((person) => <option key={person.id} value={person.id}>{person.legal_name}</option>)}</select></label>
            <label>Recipient<input required maxLength={200} value={disclosure.recipient} onChange={(event) => setDisclosure({ ...disclosure, recipient: event.target.value })} placeholder="e.g. Ministry of Education" /></label>
            <label>Purpose of release<input required maxLength={500} value={disclosure.purpose} onChange={(event) => setDisclosure({ ...disclosure, purpose: event.target.value })} placeholder="e.g. statutory-reporting" /></label>
            <label>Authority relied on<input required maxLength={120} value={disclosure.authority} onChange={(event) => setDisclosure({ ...disclosure, authority: event.target.value })} placeholder="e.g. privacy.disclose" /></label>
            <label>Disclosed category<input required maxLength={200} value={disclosure.disclosed_category} onChange={(event) => setDisclosure({ ...disclosure, disclosed_category: event.target.value })} placeholder="e.g. academic-records" /></label>
            <label>Declared scope<select value={disclosure.scope_type} onChange={(event) => setDisclosure({ ...disclosure, scope_type: event.target.value })}><option value="subject">This subject only</option><option value="branch">The subject's branch</option></select><small className="form-help">{disclosurePerson ? `Resolves to ${disclosure.scope_type === 'subject' ? compactId(disclosurePerson.id) : compactId(disclosurePerson.branch_id)}; the server re-checks it against subject provenance.` : 'Select a subject first.'}</small></label>
            <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Recording…' : 'Record disclosure'}</button><span className="form-help">Disclosures can never be edited or deleted; a correction is a new disclosure.</span></div>
          </form>}
          {workspace.disclosures.length === 0 ? <p className="empty">No disclosures are visible in this authorized scope.</p> : <div className="table-wrap"><table><thead><tr><th>Subject</th><th>Recipient</th><th>Purpose</th><th>Category</th><th>Scope</th><th>Recorded</th></tr></thead><tbody>{workspace.disclosures.slice(0, 100).map((row) => <tr key={row.id}>
            <td>{subjectLabel(row.subject_person_id)}</td><td>{row.recipient}</td><td>{row.purpose}</td><td><span className="status-chip">{titleCase(row.disclosed_category)}</span></td><td>{row.scope}</td><td>{recordedAt(row.created_at)}</td>
          </tr>)}</tbody></table></div>}
        </section>

        <section className="panel">
          <div className="section-heading"><div><p className="eyebrow">Subject access</p><h2>Export one subject's data</h2><p className="form-help">A direct export covers one subject inside a non-organization scope and records its own disclosure. Organization-wide exports are staged below and need two distinct approvers.</p></div><span className="source-note">{titleCase(workspace.policy.correction)}</span></div>
          {!workspace.available_actions.export ? <div className="alert" role="status">Your current server-authorized scope does not include subject-data exports.</div> : workspace.people.length === 0 ? <div className="alert" role="status">No verified people are available in the server-authorized export scope.</div> : <form className="form-grid" onSubmit={submitExport}>
            <label>Subject<select required value={release.subject_person_id} onChange={(event) => setRelease({ ...release, subject_person_id: event.target.value })}><option value="">Select a verified subject…</option>{workspace.people.map((person) => <option key={person.id} value={person.id}>{person.legal_name}</option>)}</select></label>
            <label>Purpose of export<input required maxLength={500} value={release.purpose} onChange={(event) => setRelease({ ...release, purpose: event.target.value })} placeholder="e.g. subject-data-request" /></label>
            <label>Declared scope<select value={release.scope_type} onChange={(event) => setRelease({ ...release, scope_type: event.target.value })}><option value="subject">This subject only</option><option value="branch">The subject's branch</option></select><small className="form-help">{releasePerson ? `Resolves to ${release.scope_type === 'subject' ? compactId(releasePerson.id) : compactId(releasePerson.branch_id)}.` : 'Select a subject first.'}</small></label>
            <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Exporting…' : 'Export subject data'}</button><span className="form-help">The release is recorded as an immutable disclosure with its correlation id.</span></div>
          </form>}
          <form className="form-grid privacy-inset" onSubmit={submitBulkRequest}>
            <h3>Request an organization-wide export</h3>
            {!workspace.available_actions.export ? <p className="form-help">Requesting an organization-wide export needs the same export authority inside the subject's branch scope.</p> : <>
              <label>Subject<select required value={bulkRequest.subject_person_id} onChange={(event) => setBulkRequest({ ...bulkRequest, subject_person_id: event.target.value })} disabled={workspace.people.length === 0}><option value="">Select a verified subject…</option>{workspace.people.map((person) => <option key={person.id} value={person.id}>{person.legal_name}</option>)}</select></label>
              <label>Purpose<input required maxLength={500} value={bulkRequest.purpose} onChange={(event) => setBulkRequest({ ...bulkRequest, purpose: event.target.value })} placeholder="e.g. organization-wide audit" /></label>
              <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy || workspace.people.length === 0}>{busy ? 'Requesting…' : 'Request organization-wide export'}</button><span className="form-help">The organization is resolved by the server from the subject's own provenance.</span></div>
            </>}
          </form>
        </section>

        <section className="panel">
          <div className="section-heading"><div><p className="eyebrow">Staged chain</p><h2>Organization-wide export requests</h2><p className="form-help">A request is signed by two distinct approvers in their own sessions and executed once approved. Approver slots are written once and an executed request is closed.</p></div><span className="source-note">{workspace.available_actions.approve_bulk_export ? 'You may sign approvals' : 'No approval authority in scope'}</span></div>
          {workspace.export_requests.length === 0 ? <p className="empty">No export requests are visible in this authorized scope.</p> : <div className="table-wrap"><table><thead><tr><th>Subject</th><th>Purpose</th><th>Signatures</th><th>Chain</th><th>Actions</th></tr></thead><tbody>{workspace.export_requests.map((row) => <tr key={row.id}>
            <td>{subjectLabel(row.subject_person_id)}<small className="privacy-id">{compactId(row.id)}</small></td>
            <td>{row.purpose}</td>
            <td>{row.approver_one_id ? compactId(row.approver_one_id) : '—'} / {row.approver_two_id ? compactId(row.approver_two_id) : '—'}</td>
            <td><span className="status-chip">{titleCase(row.lifecycle_state)}</span>{row.exported_by ? <small className="privacy-id">exported by {compactId(row.exported_by)}</small> : null}</td>
            <td><div className="action-strip privacy-actions">
              {row.available_actions.approve && <button type="button" className="button small" onClick={() => signApproval(row)} disabled={busy}>Sign approval</button>}
              {row.available_actions.execute && <button type="button" className="button small" onClick={() => executeExport(row)} disabled={busy}>Execute export</button>}
              {!row.available_actions.approve && !row.available_actions.execute && <span className="muted">No action available to you</span>}
            </div></td>
          </tr>)}</tbody></table></div>}
        </section>
      </section>}

      {activeForm && <section ref={commandPanelRef} className="panel privacy-command-panel" aria-labelledby="privacy-command-title" aria-live="polite" tabIndex={-1}>
        <div className="section-heading">
          <div><p className="eyebrow">Withdrawal of consent</p><h2 id="privacy-command-title">Revoke consent — {subjectLabel(activeForm.consent.subject_person_id)}</h2><p className="form-help">{purposeLabel(activeForm.consent.purpose_id)} · {titleCase(activeForm.consent.lifecycle_state)}. Revocation stops future use and is recorded as append-only evidence; nothing is erased.</p></div>
          <button type="button" className="text-button" onClick={() => setActiveForm(null)} disabled={busy}>Cancel</button>
        </div>
        <form className="form-grid" onSubmit={submitRevocation}>
          <label>Withdrawal scope<input required maxLength={200} value={revocation.scope} onChange={(event) => setRevocation({ ...revocation, scope: event.target.value })} placeholder="e.g. all-channels" /></label>
          <label>Effect<input required maxLength={200} value={revocation.effect} onChange={(event) => setRevocation({ ...revocation, effect: event.target.value })} placeholder="e.g. immediate-cessation" /></label>
          <div className="full-width inline-actions"><button type="submit" className="button" disabled={busy}>{busy ? 'Recording…' : 'Record revocation'}</button><span className="form-help">The subject may withdraw their own consent; staff need the consent capability in the subject's branch.</span></div>
        </form>
      </section>}

      {receipt && <section className="panel privacy-receipt" aria-labelledby="privacy-receipt-title" aria-live="polite">
        <div className="section-heading"><div><p className="eyebrow">Release receipt</p><h2 id="privacy-receipt-title">Export recorded</h2><p className="form-help">{receipt.subject} · {receipt.purpose}</p></div><button type="button" className="text-button" onClick={() => setReceipt(null)}>Close receipt</button></div>
        <ul className="privacy-receipt-list">
          <li><span>Disclosure evidence</span><strong className="privacy-id">{receipt.disclosure_id}</strong></li>
          <li><span>Audit correlation</span><strong className="privacy-id">{receipt.correlation_id}</strong></li>
          <li><span>Consent records released</span><strong>{receipt.consents}</strong></li>
          <li><span>Disclosure records released</span><strong>{receipt.disclosures}</strong></li>
        </ul>
        <p className="form-help">The exported dataset is not rendered in the browser: the disclosure above is the authoritative evidence of the release.</p>
      </section>}

      {selectedSubjectId && <section ref={dossierPanelRef} className="panel privacy-dossier" aria-labelledby="privacy-dossier-title" aria-live="polite" tabIndex={-1}>
        <div className="section-heading">
          <div><p className="eyebrow">Subject dossier</p><h2 id="privacy-dossier-title">{selectedPerson?.legal_name ?? compactId(selectedSubjectId)}</h2><p className="form-help">A separately authorized read of one subject. Consent evidence locators are never projected.</p></div>
          <button type="button" className="text-button" onClick={() => { setSelectedSubjectId(null); setDossier(null); setDossierError(null); }}>Close dossier</button>
        </div>
        <form className="form-grid privacy-asof" onSubmit={(event) => { event.preventDefault(); openDossier(selectedSubjectId); }}>
          <label>Current use authority as of<input type="date" value={dossierAsOf} onChange={(event) => setDossierAsOf(event.target.value)} /></label>
          <div className="inline-actions"><button type="submit" className="button secondary" disabled={dossierLoading}>{dossierLoading ? 'Loading…' : 'Reload dossier'}</button></div>
        </form>
        {dossierLoading && <p className="form-help">Loading the subject dossier…</p>}
        {dossierError && <div className="alert" role="alert">{dossierError}</div>}
        {dossier && <>
          <p className="form-help">As of <strong>{dossier.as_of}</strong> · branch {compactId(dossier.provenance.branch_id ?? 'unresolved')} · organization {compactId(dossier.provenance.organization_id)} · {titleCase(dossier.policy.erasure)}</p>
          <div className="privacy-dossier-grid">
            <div><h3>Current use authority</h3>{dossier.consents.length === 0 ? <p className="empty">No consent carries current use authority for this subject on this day.</p> : <ul className="privacy-list">{dossier.consents.map((row) => <li key={row.consent_id}><span className="status-chip">{titleCase(row.lifecycle_state)}</span> <strong>{purposeLabel(row.purpose_id)}</strong> <span className="muted">{row.effective_from} → {row.effective_to ?? 'open-ended'}</span></li>)}</ul>}</div>
            <div><h3>Complete consent history</h3>{dossier.consent_history.length === 0 ? <p className="empty">No consent was ever recorded for this subject.</p> : <ol className="privacy-timeline">{dossier.consent_history.map((row) => <li key={row.consent_id}>
              <div><strong>{purposeLabel(row.purpose_id)}</strong><span className="privacy-id">{compactId(row.consent_id)} · recorded by {compactId(row.recorded_by)}</span></div>
              <span className="status-chip">{titleCase(row.lifecycle_state)}</span> <span className="muted">{row.effective_from} → {row.effective_to ?? 'open-ended'}</span>
              {row.revocations.length === 0 ? null : <ul>{row.revocations.map((withdrawal) => <li key={withdrawal.revocation_id}>Withdrawn by <strong>{compactId(withdrawal.revoked_by)}</strong> — {withdrawal.scope} / {withdrawal.effect} <span className="muted">{withdrawal.at ?? 'recorded'}</span></li>)}</ul>}
            </li>)}</ol>}</div>
          </div>
          <h3>Disclosures about this subject</h3>
          {dossier.disclosures.length === 0 ? <p className="empty">No disclosure evidence exists for this subject.</p> : <div className="table-wrap"><table><thead><tr><th>Recipient</th><th>Purpose</th><th>Category</th><th>Scope</th><th>Released by</th><th>Recorded</th></tr></thead><tbody>{dossier.disclosures.map((row) => <tr key={row.disclosure_id}>
            <td>{row.recipient}</td><td>{row.purpose}</td><td>{row.disclosed_category}</td><td>{row.scope}</td><td>{compactId(row.disclosed_by)}</td><td>{row.at ?? 'Recorded'}</td>
          </tr>)}</tbody></table></div>}
          <h3>Export chain</h3>
          {dossier.export_requests.length === 0 ? <p className="empty">No organization-wide export was ever requested for this subject.</p> : <div className="table-wrap"><table><thead><tr><th>Purpose</th><th>Chain</th><th>Signatures</th><th>Requested by</th><th>Recorded</th></tr></thead><tbody>{dossier.export_requests.map((row) => <tr key={row.request_id}>
            <td>{row.purpose}<small className="privacy-id">{compactId(row.request_id)}</small></td>
            <td><span className="status-chip">{titleCase(row.lifecycle_state)}</span>{row.closed ? <small className="privacy-id">closed</small> : null}</td>
            <td>{row.approver_one_id ? compactId(row.approver_one_id) : '—'} / {row.approver_two_id ? compactId(row.approver_two_id) : '—'}</td>
            <td>{compactId(row.requested_by)}</td><td>{row.at ?? 'Recorded'}</td>
          </tr>)}</tbody></table></div>}
        </>}
      </section>}
    </main>
  </>;
}
