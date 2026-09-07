import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import { createApiClient } from './core/api';
import type { ApiClient } from './core/api';
import './app.css';

type Organization = { id: string; name: string; lifecycle_state?: string };
type Campus = { id: string; name: string; lifecycle_state?: string; organization_id: string };
type Department = { id: string; name: string; lifecycle_state?: string; scope_type: string; scope_id: string };
type Branch = { id: string; name: string; lifecycle_state?: string; campus_id?: string | null };
type Position = { id: string; name: string; organization_id: string };
type Workspace = { organizations: Organization[]; campuses: Campus[]; departments: Department[]; branches: Branch[]; positions: Position[]; scope: { organization_ids: string[]; branch_ids: string[] } };
const humanize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function OrganizationTree({ data }: { data: Workspace }) {
  return <div className="fact-list">
    {data.organizations.map((organization) => <div key={organization.id} className="panel"><div className="section-heading"><div><p className="eyebrow">Organization</p><h3>{organization.name}</h3></div><span className="status-chip">{humanize(organization.lifecycle_state ?? 'active')}</span></div>
      <ul className="fact-list">{data.campuses.filter((campus) => campus.organization_id === organization.id).map((campus) => <li key={campus.id}><strong>{campus.name}</strong><span>{humanize(campus.lifecycle_state ?? 'active')} · campus {campus.id}</span>{data.branches.filter((branch) => branch.campus_id === campus.id).map((branch) => <div key={branch.id} className="fact-line">↳ <strong>{branch.name}</strong> · {humanize(branch.lifecycle_state ?? 'active')} · branch {branch.id}</div>)}</li>)}
        {data.campuses.filter((campus) => campus.organization_id === organization.id).length === 0 && <li><span className="muted">No campus records in the current scope.</span></li>}
      </ul>
      <h4>Departments</h4><ul className="fact-list">{data.departments.filter((department) => department.scope_type === 'organization' && department.scope_id === organization.id).map((department) => <li key={department.id}><strong>{department.name}</strong><span>Organization-scoped · {humanize(department.lifecycle_state ?? 'active')}</span></li>)}</ul>
    </div>)}
  </div>;
}

export function OrganizationApp({ getJson, csrfToken }: ApiClient & { csrfToken: string }) {
  const [data, setData] = useState<Workspace | null>(null); const [loading, setLoading] = useState(true); const [error, setError] = useState<string | null>(null);
  const load = () => { setLoading(true); setError(null); void getJson<Workspace>('/organization/workspace').then(setData).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Organization structure could not be loaded.')).finally(() => setLoading(false)); };
  useEffect(load, []);
  if (loading) return <><AppShell current="management" csrfToken={csrfToken} /><PageStatus>Loading authorized organization structure…</PageStatus></>;
  if (!data) return <><AppShell current="management" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'Organization structure is unavailable.'}</div></main></>;
  return <><AppShell current="management" csrfToken={csrfToken} /><main id="workspace-main" className="workspace" aria-labelledby="organization-title">
    <header className="workspace-header"><div><p className="eyebrow">Organization topology</p><h1 id="organization-title">See structure without confusing it with access.</h1><p className="lede">This is a scoped read projection of organizations, campuses, departments, branches and positions. It does not grant permissions or alter topology.</p></div><button className="button secondary" type="button" onClick={load}>Refresh structure</button></header>
    {error && <div className="alert" role="alert">{error}</div>}
    <div className="reporting-boundary"><strong>Effective structure scope</strong><span>{data.scope.organization_ids.length} organization(s) · {data.scope.branch_ids.length} branch(es). Access capabilities are evaluated separately by the Access authority.</span></div>
    <section className="summary-grid"><div className="panel"><span className="metric">{data.organizations.length}</span><span className="metric-label">Organizations</span></div><div className="panel"><span className="metric">{data.campuses.length}</span><span className="metric-label">Campuses</span></div><div className="panel"><span className="metric">{data.branches.length}</span><span className="metric-label">Visible branches</span></div><div className="panel"><span className="metric">{data.positions.length}</span><span className="metric-label">Positions</span></div></section>
    <section className="panel"><div className="section-heading"><div><p className="eyebrow">Canonical structure</p><h2>Organization hierarchy</h2></div><span className="source-note">Read-only projection · structural commands remain domain-owned</span></div>{data.organizations.length === 0 ? <p className="empty">No organization is visible in the current scope.</p> : <OrganizationTree data={data} />}</section>
    <section className="panel"><div className="section-heading"><div><p className="eyebrow">Position catalog</p><h2>Positions within authorized organizations</h2></div><span className="source-note">Position definition is distinct from assignment and permission.</span></div>{data.positions.length === 0 ? <p className="empty">No position is visible.</p> : <div className="table-wrap"><table><thead><tr><th>Position</th><th>Organization</th><th>Identifier</th></tr></thead><tbody>{data.positions.map((position) => <tr key={position.id}><td><strong>{position.name}</strong></td><td>{data.organizations.find((organization) => organization.id === position.organization_id)?.name ?? position.organization_id}</td><td>{position.id}</td></tr>)}</tbody></table></div>}</section>
  </main></>;
}

const root = document.getElementById('organization-console');
if (root) { const csrfToken = root.getAttribute('data-csrf-token') ?? ''; const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken }); createRoot(root).render(<OrganizationApp {...api} csrfToken={csrfToken} />); }
