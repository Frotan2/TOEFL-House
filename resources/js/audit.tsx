import { FormEvent, useEffect, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type AuditEvent = { id: string; actor_id: string; operation: string; target_type: string; target_id: string; correlation_id: string; before_state: unknown; after_state: unknown; occurred_at: string | null; evidence_policy: string };
type AuditData = { scope: { organization_ids: string[]; branch_ids: string[] }; events: AuditEvent[]; operations: string[]; filters: { operation: string; actor_id: string; target_type: string } };
type Props = ApiClient & { csrfToken: string };

export function AuditApp({ getJson, csrfToken }: Props) {
  const [data, setData] = useState<AuditData | null>(null);
  const [filters, setFilters] = useState({ operation: '', actor_id: '', target_type: '' });
  const [error, setError] = useState<string | null>(null);
  const load = (query = '') => void getJson<{ data: AuditData }>(`/audit/workspace${query}`).then((r) => setData(r.data)).catch((e: unknown) => setError(e instanceof Error ? e.message : 'Audit evidence could not be loaded.'));
  useEffect(() => { load(); }, []);
  const submit = (event: FormEvent) => { event.preventDefault(); const q = new URLSearchParams(); Object.entries(filters).forEach(([k, v]) => { if (v.trim()) q.set(k, v.trim()); }); load(q.toString() ? `?${q.toString()}` : ''); };
  if (!data && !error) return <><AppShell current="audit" csrfToken={csrfToken} /><PageStatus>Loading immutable audit evidence…</PageStatus></>;
  return <><AppShell current="audit" csrfToken={csrfToken} /><main className="workspace" aria-labelledby="audit-title">
    <header className="workspace-header"><div><p className="eyebrow">Governance · Audit</p><h1 id="audit-title">Immutable audit evidence</h1><p className="lede">The server determines visible evidence. This surface never treats browser state, role labels, or caller timestamps as authority.</p></div><span className="scope-badge">{data?.scope.branch_ids.length ?? 0} branches · {data?.scope.organization_ids.length ?? 0} organizations</span></header>
    {error && <div className="alert" role="alert">{error}</div>}
    {data && <>
      <section className="summary-grid workspace-summary" aria-label="Audit evidence summary"><div className="panel"><span className="metric">{data.events.length}</span><span className="metric-label">Visible events</span><small className="metric-detail">Server-scoped</small></div><div className="panel"><span className="metric">{data.operations.length}</span><span className="metric-label">Known operations</span><small className="metric-detail">Within current scope</small></div><div className="panel"><span className="metric">{data.scope.organization_ids.length}</span><span className="metric-label">Organizations</span><small className="metric-detail">Authorized evidence scope</small></div><div className="panel"><span className="metric">{data.scope.branch_ids.length}</span><span className="metric-label">Branches</span><small className="metric-detail">Authorized evidence scope</small></div></section>
      <section className="panel"><form onSubmit={submit} className="search"><div className="search-row"><select aria-label="Operation" value={filters.operation} onChange={(e) => setFilters({ ...filters, operation: e.target.value })}><option value="">All operations</option>{data.operations.map((operation) => <option key={operation} value={operation}>{operation}</option>)}</select><input aria-label="Actor id" placeholder="Actor id" value={filters.actor_id} onChange={(e) => setFilters({ ...filters, actor_id: e.target.value })} /><input aria-label="Target type" placeholder="Target type" value={filters.target_type} onChange={(e) => setFilters({ ...filters, target_type: e.target.value })} /><button className="button" type="submit">Filter evidence</button></div></form></section>
      <section className="panel"><div className="section-heading"><div><p className="eyebrow">Evidence stream</p><h2>Append-only history</h2></div><span className="source-note">No edit/delete controls</span></div><div className="table-wrap"><table><thead><tr><th>Occurred</th><th>Operation</th><th>Actor</th><th>Target</th><th>Correlation</th><th>Evidence</th></tr></thead><tbody>{data.events.map((item) => <tr key={item.id}><td>{item.occurred_at ?? 'Unavailable'}</td><td>{item.operation}</td><td>{item.actor_id}</td><td>{item.target_type}:{item.target_id}</td><td>{item.correlation_id}</td><td><details><summary>Before / after</summary><pre className="evidence-block">{JSON.stringify({ before: item.before_state, after: item.after_state, policy: item.evidence_policy }, null, 2)}</pre></details></td></tr>)}</tbody></table></div></section>
    </>}
  </main></>;
}
