import { FormEvent, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './app.css';
import { AppErrorBoundary } from './core/error-boundary';
import type { ApiClient } from './core/api';
import { AppShell, PageStatus } from './ui';
import { createApiClient } from './core/api';

type Metric = { id: string; key: string; name: string; current_version: number; period_authority?: string };
type ReportRun = { id: string; metric_key: string; metric_name: string; period_key: string; scope_type: string; scope_id: string | null; organization_id: string | null; result: string | number | null; completeness: string | null; reproducibility_hash: string; created_at: string | null; evidence_status: 'complete' | 'incomplete' | 'historic_unclassified' };
type Dashboard = { id: string; name: string; organization_id: string };
type AcademicPeriod = { id: string; name: string; starts_on: string; ends_on: string; lifecycle_state: string };
type FinancialPeriod = { id: string; period_key: string; date_from: string; date_to: string; lifecycle_state: string };
type PayrollPeriod = { id: string; period_key: string; date_from: string; date_to: string; lifecycle_state: string };
type ReportingWorkspace = { metrics: Metric[]; runs: ReportRun[]; dashboards: Dashboard[]; dashboard_organizations: string[]; periods: { academic: AcademicPeriod[]; financial: FinancialPeriod[]; payroll: PayrollPeriod[] } };
type ReportingProps = ApiClient & { csrfToken: string };
type Tab = 'overview' | 'runs' | 'dashboards';

function humanize(value: string): string { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }

export function ReportingApp({ getJson, postJson, csrfToken }: ReportingProps) {
  const [data, setData] = useState<ReportingWorkspace | null>(null);
  const [tab, setTab] = useState<Tab>('overview');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [runForm, setRunForm] = useState({ metric_key: '', period_key: '', scope_type: 'branch', scope_id: '' });
  const [dashboardForm, setDashboardForm] = useState({ name: '', organization_id: '' });
  const [pinForms, setPinForms] = useState<Record<string, { metric_key: string; period_key: string; scope_type: 'branch' | 'student' | 'class' | 'fund'; scope_id: string }>>({});

  const periodsForMetric = (metricKey: string): { key: string; label: string }[] => {
    if (!data) return [];
    const metric = data.metrics.find((m) => m.key === metricKey);
    const authority = metric?.period_authority ?? 'academic_period';
    if (authority === 'financial_period') {
      return data.periods.financial.map((p) => ({ key: p.period_key, label: `${p.period_key} · ${p.date_from} to ${p.date_to} (${p.lifecycle_state})` }));
    }
    if (authority === 'payroll_period') {
      return data.periods.payroll.map((p) => ({ key: p.period_key, label: `${p.period_key} · ${p.date_from} to ${p.date_to} (${p.lifecycle_state})` }));
    }
    return data.periods.academic.map((p) => ({ key: p.id, label: `${p.name} · ${p.starts_on} to ${p.ends_on} (${p.lifecycle_state})` }));
  };

  const load = () => {
    setLoading(true); setError(null);
    void getJson<{ data: ReportingWorkspace }>('/reporting/workspace')
      .then((response) => {
        setData(response.data);
        setRunForm((current) => ({ ...current, metric_key: current.metric_key || response.data.metrics[0]?.key || '' }));
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Reporting workspace could not be loaded.'))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const filteredRuns = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return data?.runs ?? [];
    return (data?.runs ?? []).filter((run) => `${run.metric_name} ${run.metric_key} ${run.period_key} ${run.scope_type} ${run.scope_id ?? ''} ${run.organization_id ?? ''}`.toLowerCase().includes(term));
  }, [data, query]);

  const command = (request: Promise<unknown>, success: string) => {
    setBusy(true); setError(null); setMessage(null);
    void request.then(() => { setMessage(success); load(); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Reporting command was rejected.')).finally(() => setBusy(false));
  };

  const submitRun = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    command(postJson('/reporting/runs', { ...runForm, scope_id: runForm.scope_id || undefined }), 'Report run recorded with source evidence and reproducibility metadata.');
  };

  const submitDashboard = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    command(postJson('/reporting/dashboards', { ...dashboardForm, organization_id: dashboardForm.organization_id || undefined }), 'Dashboard created from the Reporting authority.');
    setDashboardForm({ name: '', organization_id: '' });
  };

  const pinDashboard = (event: FormEvent<HTMLFormElement>, dashboardId: string) => {
    event.preventDefault();
    const form = pinForms[dashboardId];
    if (!form) return;
    command(postJson(`/reporting/dashboards/${encodeURIComponent(dashboardId)}/pin`, { ...form, scope_id: form.scope_id || undefined }), 'Metric pinned to the existing dashboard definition.');
  };

  const dashboardFormFor = (dashboardId: string): { metric_key: string; period_key: string; scope_type: 'branch' | 'student' | 'class' | 'fund'; scope_id: string } => pinForms[dashboardId] ?? {
    metric_key: data?.metrics[0]?.key ?? '', period_key: '', scope_type: 'branch', scope_id: '',
  };

  if (loading && !data) return <><AppShell current="reporting" csrfToken={csrfToken} /><PageStatus>Loading source-linked reporting workspace…</PageStatus></>;
  if (!data) return <><AppShell current="reporting" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'Reporting data is unavailable.'}<button className="button secondary" type="button" onClick={load}>Retry</button></div></main></>;

  return <>
    <AppShell current="reporting" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace" aria-labelledby="reporting-title">
      <header className="workspace-header">
        <div><p className="eyebrow">Reporting · derived information</p><h1 id="reporting-title">Evidence before the number.</h1><p className="lede">Reports are reproducible projections over authoritative sources. Periods come from the canonical academic/financial/payroll authority — no free-text keys. Incomplete or historically unclassified evidence is shown explicitly rather than presented as certain.</p></div>
        <button className="button secondary" type="button" onClick={load} disabled={loading} aria-busy={loading}>{loading ? 'Refreshing…' : 'Refresh reporting'}</button>
      </header>
      {error && <div className="alert" role="alert">{error}<button className="text-button" type="button" onClick={load}>Retry</button></div>}
      {message && <div className="notice" role="status" aria-live="polite">{message}</div>}
      <section className="summary-grid" aria-label="Reporting summary"><div className="panel"><span className="metric">{data.metrics.length}</span><span className="metric-label">Resolved metric definitions</span></div><div className="panel"><span className="metric">{data.runs.length}</span><span className="metric-label">Visible report runs</span></div><div className="panel"><span className="metric">{data.dashboards.length}</span><span className="metric-label">Authorized dashboards</span></div><div className="panel"><span className="metric">Authoritative periods</span><span className="metric-label">{data.periods.academic.length + data.periods.financial.length + data.periods.payroll.length} canonical periods</span></div></section>
      <div className="reporting-boundary"><strong>Reporting boundary</strong><span>These values are derived read-model outputs. Finance, Academic, Students, Organization, and Access remain authoritative in their owning domains. Calendar authority enforces Kabul AFT, Shamsi, Gregorian storage.</span></div>
      <div className="student-tabs" role="tablist" aria-label="Reporting work areas"><button id="reporting-tab-overview" role="tab" aria-selected={tab === 'overview'} aria-controls="reporting-panel-overview" tabIndex={tab === 'overview' ? 0 : -1} className={tab === 'overview' ? 'active' : ''} type="button" onClick={() => setTab('overview')}>Overview</button><button id="reporting-tab-runs" role="tab" aria-selected={tab === 'runs'} aria-controls="reporting-panel-runs" tabIndex={tab === 'runs' ? 0 : -1} className={tab === 'runs' ? 'active' : ''} type="button" onClick={() => setTab('runs')}>Report runs <span aria-hidden="true">{data.runs.length}</span></button><button id="reporting-tab-dashboards" role="tab" aria-selected={tab === 'dashboards'} aria-controls="reporting-panel-dashboards" tabIndex={tab === 'dashboards' ? 0 : -1} className={tab === 'dashboards' ? 'active' : ''} type="button" onClick={() => setTab('dashboards')}>Dashboards <span aria-hidden="true">{data.dashboards.length}</span></button></div>

      {(tab === 'overview' || tab === 'runs') && <section id={tab === 'overview' ? 'reporting-panel-overview' : 'reporting-panel-runs'} role="tabpanel" aria-labelledby={tab === 'overview' ? 'reporting-tab-overview' : 'reporting-tab-runs'} tabIndex={0} className="panel"><div className="section-heading"><div><p className="eyebrow">Reproducible projections</p><h2 id="report-runs-heading">Report runs</h2></div><span className="source-note">Source-linked · numeric result withheld when evidence is incomplete</span></div><label className="directory-filter" htmlFor="report-runs-filter">Filter report runs<input id="report-runs-filter" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Metric, period, scope, organization" /></label>{filteredRuns.length === 0 ? <p className="empty">No report run matches the current scope or filter.</p> : <div className="table-wrap"><table><thead><tr><th scope="col">Metric</th><th scope="col">Period</th><th scope="col">Scope</th><th scope="col">Evidence</th><th scope="col">Result</th><th scope="col">Provenance</th></tr></thead><tbody>{filteredRuns.map((run) => <tr key={run.id}><td><strong>{run.metric_name}</strong><small>{run.metric_key}</small></td><td>{run.period_key}</td><td>{humanize(run.scope_type)}{run.scope_id ? ` · ${run.scope_id}` : ''}</td><td><span className="status-chip">{run.evidence_status === 'complete' ? 'Complete' : run.evidence_status === 'incomplete' ? 'Incomplete evidence' : 'Historic evidence unclassified'}</span></td><td>{run.result === null ? 'Withheld' : <strong>{String(run.result)}</strong>}</td><td><small>{run.organization_id ?? 'Organization unavailable'} · hash {run.reproducibility_hash.slice(0, 14)}…</small></td></tr>)}</tbody></table></div>}</section>}

      {tab === 'overview' && <section id="reporting-panel-overview-commands" className="reporting-command-grid">
        <section className="panel"><div className="section-heading"><div><p className="eyebrow">Command · run</p><h2>Run a report</h2></div><span className="source-note">Canonical period selector — no free-text period keys. Resolved via CalendarAuthority.</span></div><form onSubmit={submitRun}><div className="compact-grid"><label>Metric<select required value={runForm.metric_key} onChange={(event) => setRunForm({ ...runForm, metric_key: event.target.value, period_key: '' })}><option value="">Select a metric…</option>{data.metrics.map((metric) => <option key={metric.id} value={metric.key}>{metric.name} · v{metric.current_version}</option>)}</select></label><label>Period (authoritative)<select required value={runForm.period_key} onChange={(event) => setRunForm({ ...runForm, period_key: event.target.value })}><option value="">Select canonical period…</option>{periodsForMetric(runForm.metric_key).map((period) => <option key={period.key} value={period.key}>{period.label}</option>)}</select></label><label>Scope type<select value={runForm.scope_type} onChange={(event) => setRunForm({ ...runForm, scope_type: event.target.value })}><option value="global">Global</option><option value="branch">Branch</option><option value="student">Student</option><option value="class">Class</option><option value="fund">Fund</option></select></label><label>Scope ID<input value={runForm.scope_id} onChange={(event) => setRunForm({ ...runForm, scope_id: event.target.value })} placeholder="Required by selected scope when applicable" /></label></div><p className="form-help">Periods are authoritative — academic_periods, financial_periods, payroll_periods from server. No free-text period keys allowed.</p><button className="button" type="submit" disabled={busy}>Run report</button></form></section>
        <section className="panel"><div className="section-heading"><div><p className="eyebrow">Command · dashboard</p><h2>Create dashboard</h2></div><span className="source-note">Dashboard definitions remain tenant-owned projections.</span></div><form onSubmit={submitDashboard}><div className="compact-grid"><label className="full-width">Name<input required maxLength={160} value={dashboardForm.name} onChange={(event) => setDashboardForm({ ...dashboardForm, name: event.target.value })} /></label>{data.dashboard_organizations.length > 0 && <label className="full-width">Organization<select value={dashboardForm.organization_id} onChange={(event) => setDashboardForm({ ...dashboardForm, organization_id: event.target.value })} required={data.dashboard_organizations.length > 1}><option value="">{data.dashboard_organizations.length > 1 ? 'Select organization…' : 'Use current organization scope'}</option>{data.dashboard_organizations.map((organizationId) => <option key={organizationId} value={organizationId}>{organizationId}</option>)}</select></label>}</div><button className="button" type="submit" disabled={busy}>Create dashboard</button></form></section>
      </section>}

      {tab === 'dashboards' && <section id="reporting-panel-dashboards" role="tabpanel" aria-labelledby="reporting-tab-dashboards" tabIndex={0} className="panel"><div className="section-heading"><div><p className="eyebrow">Saved projection definitions</p><h2 id="dashboards-heading">Dashboards</h2></div><span className="source-note">Pin existing metric definitions; dashboards never become a second truth.</span></div>{data.dashboards.length === 0 ? <p className="empty">No dashboard is available in the current authorization scope.</p> : <div className="dashboard-list">{data.dashboards.map((dashboard) => { const form = dashboardFormFor(dashboard.id); return <article className="panel nested-panel" key={dashboard.id}><div className="section-heading"><div><h3>{dashboard.name}</h3><p className="muted">Organization {dashboard.organization_id}</p></div><span className="status-chip">Authorized</span></div><form onSubmit={(event) => pinDashboard(event, dashboard.id)}><div className="compact-grid"><label>Metric<select required value={form.metric_key} onChange={(event) => setPinForms({ ...pinForms, [dashboard.id]: { ...form, metric_key: event.target.value, period_key: '' } })}><option value="">Select metric…</option>{data.metrics.map((metric) => <option key={metric.id} value={metric.key}>{metric.name}</option>)}</select></label><label>Period (authoritative)<select required value={form.period_key} onChange={(event) => setPinForms({ ...pinForms, [dashboard.id]: { ...form, period_key: event.target.value } })}><option value="">Select canonical period…</option>{periodsForMetric(form.metric_key).map((period) => <option key={period.key} value={period.key}>{period.label}</option>)}</select></label><label>Scope type<select value={form.scope_type} onChange={(event) => setPinForms({ ...pinForms, [dashboard.id]: { ...form, scope_type: event.target.value as typeof form.scope_type } })}><option value="branch">Branch</option><option value="student">Student</option><option value="class">Class</option><option value="fund">Fund</option></select></label><label>Scope ID<input required value={form.scope_id} onChange={(event) => setPinForms({ ...pinForms, [dashboard.id]: { ...form, scope_id: event.target.value } })} /></label></div><button className="button secondary" type="submit" disabled={busy}>Pin metric</button></form></article>; })}</div>}</section>}
    </main>
  </>;
}

const root = document.getElementById('reporting-console');
if (root) {
  const csrfToken = root.getAttribute('data-csrf-token') ?? '';
  const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken });
  createRoot(root).render(<AppErrorBoundary><ReportingApp {...api} csrfToken={csrfToken} /></AppErrorBoundary>);
}
