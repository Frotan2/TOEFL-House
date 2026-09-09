import { useEffect, useMemo, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type PrivacyData = {
  scope: { branch_ids: string[] };
  people: Array<{ id: string; legal_name: string; branch_id: string }>;
  purposes: Array<{ id: string; name: string; channel: string; category: string }>;
  consents: Array<{ id: string; subject_person_id: string; purpose_id: string; lifecycle_state: string; effective_from: string; effective_to: string | null; evidence_ref: string }>;
  revocations: Array<{ id: string; consent_id: string; revoked_by: string; scope: string; effect: string; created_at: string | null }>;
  disclosures: Array<{ id: string; subject_person_id: string; recipient: string; purpose: string; authority: string; scope_type: string; scope_id: string; disclosed_category: string; disclosed_by: string; created_at: string | null }>;
  export_requests: Array<{ id: string; subject_person_id: string; purpose: string; organization_id: string; lifecycle_state: string; requested_by: string; approver_one_id?: string | null; approver_two_id?: string | null; exported_by?: string | null; created_at?: string | null }>;
  policy: { authority: string; history: string; correction: string };
};

type Props = ApiClient & { csrfToken: string };

const humanize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function PrivacyApp({ getJson, csrfToken }: Props) {
  const [data, setData] = useState<PrivacyData | null>(null);
  const [selected, setSelected] = useState<string | null>(null);
  const [subject, setSubject] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { void getJson<{ data: PrivacyData }>('/privacy/workspace').then((r) => setData(r.data)).catch((e: unknown) => setError(e instanceof Error ? e.message : 'Privacy evidence could not be loaded.')); }, [getJson]);
  useEffect(() => {
    if (!selected) { setSubject(null); return; }
    void getJson<{ data: Record<string, unknown> }>(`/privacy/subjects/${encodeURIComponent(selected)}`).then((r) => setSubject(r.data)).catch((e: unknown) => setError(e instanceof Error ? e.message : 'Subject privacy evidence could not be loaded.'));
  }, [selected, getJson]);
  const peopleById = useMemo(() => new Map((data?.people ?? []).map((p) => [p.id, p.legal_name])), [data]);
  if (!data && !error) return <><AppShell current="management" csrfToken={csrfToken} /><PageStatus>Loading server-authorized privacy evidence…</PageStatus></>;
  return <><AppShell current="management" csrfToken={csrfToken} /><main className="workspace" aria-labelledby="privacy-title">
    <header className="workspace-header"><div><p className="eyebrow">Governance · Privacy</p><h1 id="privacy-title">Privacy evidence & subject history</h1><p className="lede">Read-only evidence projected by the server. The browser does not decide roles, branches, or correction authority.</p></div><span className="scope-badge">{data?.scope.branch_ids.length ?? 0} authorized branch scope</span></header>
    {error && <div className="alert" role="alert">{error}</div>}
    {data && <>
      <section className="summary-grid workspace-summary" aria-label="Privacy policy"><div className="panel"><span className="metric">{data.people.length}</span><span className="metric-label">Visible subjects</span><small className="metric-detail">Server-scoped</small></div><div className="panel"><span className="metric">{data.consents.length}</span><span className="metric-label">Consent records</span><small className="metric-detail">Lifecycle history retained</small></div><div className="panel"><span className="metric">{data.disclosures.length}</span><span className="metric-label">Disclosure evidence</span><small className="metric-detail">Append-only release records</small></div><div className="panel"><span className="metric">{data.export_requests.length}</span><span className="metric-label">Export requests</span><small className="metric-detail">Approval chain retained</small></div></section>
      <section className="panel"><div className="section-heading"><div><p className="eyebrow">Policy authority</p><h2>One server contract</h2></div></div><p>{humanize(data.policy.authority)} · {humanize(data.policy.history)} · {humanize(data.policy.correction)}</p></section>
      <section className="panel"><div className="section-heading"><div><p className="eyebrow">Subjects</p><h2>Authorized people</h2></div></div><div className="table-wrap"><table><thead><tr><th>Subject</th><th>Branch</th><th>Evidence</th></tr></thead><tbody>{data.people.map((person) => <tr key={person.id}><td><button className="text-button" type="button" onClick={() => setSelected(person.id)}>{person.legal_name}</button></td><td>{person.branch_id}</td><td>{data.disclosures.filter((d) => d.subject_person_id === person.id).length} disclosure(s)</td></tr>)}</tbody></table></div></section>
      {selected && <section className="panel" aria-live="polite"><div className="section-heading"><div><p className="eyebrow">Subject evidence</p><h2>{peopleById.get(selected) ?? selected}</h2></div></div><pre className="evidence-block">{JSON.stringify(subject, null, 2)}</pre></section>}
      <section className="panel"><div className="section-heading"><div><p className="eyebrow">Release evidence</p><h2>Recent disclosures</h2></div></div><div className="table-wrap"><table><thead><tr><th>Subject</th><th>Recipient</th><th>Purpose</th><th>Scope</th><th>Recorded</th></tr></thead><tbody>{data.disclosures.slice(0, 100).map((d) => <tr key={d.id}><td>{peopleById.get(d.subject_person_id) ?? d.subject_person_id}</td><td>{d.recipient}</td><td>{d.purpose}</td><td>{d.scope_type}:{d.scope_id}</td><td>{d.created_at ?? 'Unavailable'}</td></tr>)}</tbody></table></div></section>
    </>}
  </main></>;
}
