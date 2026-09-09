import { useEffect, useState } from 'react';
import { AppShell, Icon, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type ManagementWorkspace = {
  actor?: { id: string; display_name: string };
  scope: { type: string; organization_ids: string[]; branch_ids: string[] };
  counts: Record<string, number>;
  operational_health: Record<string, number | string>;
  latest_reports: Array<{
    id: string;
    period_key: string;
    result: string | null;
    completeness: 'complete' | 'incomplete' | null;
    evidence_status: 'complete' | 'incomplete' | 'historic_unclassified';
    created_at: string | null;
  }>;
};

type EmployeeWorkspace = {
  actor: { id: string; display_name: string };
  positions: Array<{ position_name: string }>;
  scope: { organization_ids: string[]; branch_ids: string[]; scope_known: boolean };
  work: { items: Array<{ id?: string; kind: string; source_type: string; source_id: string; title: string; status: string; due_at: string | null; route: string; reason?: string | null }>; count: number };
  notifications: { unread_count: number; items: Array<{ id: string; title: string; severity: string; status: string; created_at: string | null }> };
};

type AcademicSnapshot = {
  periods: Array<{ id: string; name: string; lifecycle_state: string }>;
  program_versions: Array<{ id: string; program_name: string; version_no: number }>;
  levels: Array<{ id: string; title: string; lifecycle_state: string }>;
  offerings: Array<{ id: string; lifecycle_state: string }>;
  classes: Array<{ id: string; lifecycle_state: string }>;
  teachers: Array<{ id: string; name: string }>;
  rooms: Array<{ id: string; name: string }>;
  sessions: Array<{ id: string }>;
};

type OrganizationSnapshot = {
  organizations: Array<{ id: string; name: string; lifecycle_state?: string }>;
  campuses: Array<{ id: string; name: string; lifecycle_state?: string }>;
  branches: Array<{ id: string; name: string; lifecycle_state?: string }>;
  positions: Array<{ id: string; name: string }>;
};

type ManagementAppProps = ApiClient & { csrfToken: string };
type View = 'command' | 'administration';

function humanize(key: string): string {
  return key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function count(data: ManagementWorkspace | null, key: string): number {
  return data?.counts[key] ?? 0;
}

function ActionLink({ href, title, description, icon }: { href: string; title: string; description: string; icon: 'users' | 'academic' | 'finance' | 'teacher' | 'reporting' | 'settings' }) {
  return <a className="quick-action command-action" href={href}><span className="quick-icon" aria-hidden="true"><Icon name={icon} /></span><span><strong>{title}</strong><small>{description}</small></span><Icon name="chevron" className="command-chevron" /></a>;
}

export function ManagementApp({ getJson, csrfToken }: ManagementAppProps) {
  const [management, setManagement] = useState<ManagementWorkspace | null>(null);
  const [employee, setEmployee] = useState<EmployeeWorkspace | null>(null);
  const [academic, setAcademic] = useState<AcademicSnapshot | null>(null);
  const [organization, setOrganization] = useState<OrganizationSnapshot | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [view, setView] = useState<View>(() => new URLSearchParams(window.location.search).get('view') === 'administration' ? 'administration' : 'command');

  const load = () => {
    setLoading(true);
    setError(null);
    void Promise.allSettled([
      getJson<{ data: ManagementWorkspace }>('/management'),
      getJson<{ data: EmployeeWorkspace }>('/workspace'),
      getJson<AcademicSnapshot>('/academic/workspace'),
      getJson<OrganizationSnapshot>('/organization/workspace'),
    ]).then(([managementResult, employeeResult, academicResult, organizationResult]) => {
      const failures: string[] = [];
      if (managementResult.status === 'fulfilled') setManagement(managementResult.value.data);
      else failures.push('management');
      if (employeeResult.status === 'fulfilled') setEmployee(employeeResult.value.data);
      else failures.push('work queue');
      if (academicResult.status === 'fulfilled') setAcademic(academicResult.value.data);
      else failures.push('academic readiness');
      if (organizationResult.status === 'fulfilled') setOrganization(organizationResult.value.data);
      else failures.push('organization readiness');
      if (managementResult.status === 'rejected' && employeeResult.status === 'rejected') {
        setError('The command center could not resolve an authorized management scope.');
      } else if (failures.length) {
        setError(`Some live projections are unavailable: ${failures.join(', ')}.`);
      }
    }).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const setRoute = (next: View) => {
    setView(next);
    const url = new URL(window.location.href);
    if (next === 'administration') url.searchParams.set('view', 'administration');
    else url.searchParams.delete('view');
    window.history.replaceState({}, '', url);
  };

  if (loading && management === null && employee === null) return <><AppShell current="management" csrfToken={csrfToken} /><PageStatus>Building your authorized command center…</PageStatus></>;

  const scopeLabel = management ? humanize(management.scope.type) : employee?.scope.scope_known ? 'Authorized' : 'Unavailable';
  const workItems = employee?.work.items ?? [];
  const notifications = employee?.notifications.items ?? [];
  const readiness = [
    { key: 'organization', label: 'Organization structure', ready: (organization?.organizations.length ?? 0) > 0 && (organization?.branches.length ?? 0) > 0, detail: `${organization?.organizations.length ?? 0} organization(s) · ${organization?.branches.length ?? 0} branch(es)`, href: '/organization' },
    { key: 'period', label: 'Academic period', ready: (academic?.periods.length ?? 0) > 0, detail: `${academic?.periods.filter((item) => item.lifecycle_state === 'published').length ?? 0} published`, href: '/academic' },
    { key: 'program', label: 'Programs & levels', ready: (academic?.program_versions.length ?? 0) > 0 && (academic?.levels.length ?? 0) > 0, detail: `${academic?.program_versions.length ?? 0} program version(s) · ${academic?.levels.length ?? 0} level(s)`, href: '/academic' },
    { key: 'delivery', label: 'Delivery capacity', ready: (academic?.offerings.length ?? 0) > 0 && (academic?.classes.length ?? 0) > 0, detail: `${academic?.offerings.length ?? 0} offering(s) · ${academic?.classes.length ?? 0} class(es)`, href: '/academic' },
    { key: 'people', label: 'People & resources', ready: (organization?.positions.length ?? 0) > 0 || (academic?.teachers.length ?? 0) > 0 || (academic?.rooms.length ?? 0) > 0, detail: `${academic?.teachers.length ?? 0} teacher(s) · ${academic?.rooms.length ?? 0} room(s)`, href: '/management?view=administration' },
  ];

  return <>
    <AppShell current="management" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace command-center" aria-labelledby="management-title">
      <header className="workspace-header command-header">
        <div><p className="eyebrow">{view === 'command' ? 'Owner / management workspace' : 'Control plane'}</p><h1 id="management-title">{view === 'command' ? 'Know what needs attention.' : 'Configure the institute from one place.'}</h1><p className="lede">{view === 'command' ? 'A decision-oriented view of the operation, your current work, and the setup gaps that can block delivery.' : 'Operational settings are organized by responsibility. Existing domain pages remain the authorities for every change.'}</p></div>
        <div className="command-header-actions"><span className="scope-badge">{scopeLabel} scope</span><button className="button secondary" type="button" onClick={load} disabled={loading}>{loading ? 'Refreshing…' : 'Refresh snapshot'}</button></div>
      </header>
      <div className="workspace-switch" role="tablist" aria-label="Management areas">
        <button className={view === 'command' ? 'active' : ''} type="button" role="tab" aria-selected={view === 'command'} onClick={() => setRoute('command')}>Command center</button>
        <button className={view === 'administration' ? 'active' : ''} type="button" role="tab" aria-selected={view === 'administration'} onClick={() => setRoute('administration')}>Administration & setup</button>
      </div>
      {error && <div className="alert" role="alert">{error}</div>}

      {view === 'command' && <>
        <section className="command-metrics" aria-label="Operational overview">
          <a className="panel metric-link" href="/students"><span className="metric">{count(management, 'students')}</span><span className="metric-label">Students in scope</span><small>Open learner operations</small></a>
          <a className="panel metric-link" href="/academic"><span className="metric">{count(management, 'classes')}</span><span className="metric-label">Classes</span><small>Open delivery workspace</small></a>
          <a className="panel metric-link" href="/students/applicants"><span className="metric">{count(management, 'pending_admission_reviews')}</span><span className="metric-label">Pending admissions</span><small>Review or approve</small></a>
          <a className="panel metric-link" href="/finance"><span className="metric">{count(management, 'pending_financial_gate_exceptions')}</span><span className="metric-label">Financial exceptions</span><small>Resolve blocked decisions</small></a>
          <a className="panel metric-link" href="/payroll"><span className="metric">{count(management, 'held_payroll_calculations')}</span><span className="metric-label">Held payroll</span><small>Open payroll workspace</small></a>
          <a className="panel metric-link" href="/workspace#work-queue"><span className="metric">{employee?.work.count ?? count(management, 'open_work_items')}</span><span className="metric-label">Open work</span><small>Claim and coordinate</small></a>
        </section>

        <section className="command-grid">
          <section className="panel" aria-labelledby="attention-heading"><div className="section-heading"><div><p className="eyebrow">Action center</p><h2 id="attention-heading">What needs your attention</h2></div><span className="source-note">Live source projections</span></div>
            {workItems.length === 0 ? <p className="empty">No work item is currently assigned to this scope.</p> : <ul className="work-list command-list">{workItems.slice(0, 8).map((item, index) => <li key={item.id ?? `${item.source_type}-${item.source_id}-${index}`}><div><span className="kind">{humanize(item.kind)}</span><a href={item.route}>{item.title}</a><small>{item.due_at ? `Due ${item.due_at}` : `${item.source_type} · ${item.source_id}`}{item.reason ? ` · ${item.reason}` : ''}</small></div><span className={`status-chip ${item.status}`}>{humanize(item.status)}</span></li>)}</ul>}
          </section>

          <section className="panel" aria-labelledby="quick-actions-heading"><div className="section-heading"><div><p className="eyebrow">Do the work</p><h2 id="quick-actions-heading">Quick actions</h2></div><span className="source-note">Existing server authorities</span></div><div className="quick-actions command-actions"><ActionLink href="/students" title="Register / manage student" description="Admissions, lifecycle and learner records" icon="users" /><ActionLink href="/academic" title="Run academic operations" description="Classes, schedules, attendance and outcomes" icon="academic" /><ActionLink href="/finance" title="Open finance" description="Payments, obligations and financial exceptions" icon="finance" /><ActionLink href="/teachers" title="Manage people & faculty" description="Teacher capability and assignment records" icon="teacher" /><ActionLink href="/reporting" title="Open reports" description="Evidence-aware reporting and drill-in" icon="reporting" /><ActionLink href="/management?view=administration" title="Configure institute" description="Organization, access and operational setup" icon="settings" /></div></section>
        </section>

        <section className="command-grid">
          <section className="panel" aria-labelledby="health-heading"><div className="section-heading"><div><p className="eyebrow">Institute health</p><h2 id="health-heading">Ready for operations?</h2></div><span className="source-note">Calculated from live authorized projections</span></div><div className="readiness-list">{readiness.map((item) => <a href={item.href} key={item.key} className={`readiness-item ${item.ready ? 'ready' : 'attention'}`}><span className="readiness-icon" aria-hidden="true">{item.ready ? '✓' : '!'}</span><span><strong>{item.label}</strong><small>{item.detail}</small></span><Icon name="chevron" /></a>)}</div></section>
          <section className="panel" aria-labelledby="notifications-heading"><div className="section-heading"><div><p className="eyebrow">Your signals</p><h2 id="notifications-heading">Recent notifications</h2></div><span className="source-note">{employee?.notifications.unread_count ?? 0} unread</span></div>{notifications.length === 0 ? <p className="empty">No active notifications.</p> : <ul className="compact-list notification-list">{notifications.slice(0, 6).map((notification) => <li key={notification.id}><span><strong>{notification.title}</strong><small>{notification.created_at ?? 'Time unavailable'} · {humanize(notification.severity)} · {notification.status}</small></span></li>)}</ul>}</section>
        </section>

        <section className="panel" aria-labelledby="reports-heading"><div className="section-heading"><div><p className="eyebrow">Decision evidence</p><h2 id="reports-heading">Latest reporting activity</h2></div><a className="text-button" href="/reporting">Open reporting</a></div>{management?.latest_reports.length ? <div className="table-wrap"><table><thead><tr><th>Period</th><th>Evidence</th><th>Result</th><th>Created</th></tr></thead><tbody>{management.latest_reports.map((report) => <tr key={report.id}><td>{report.period_key}</td><td><span className="status-chip">{report.evidence_status === 'complete' ? 'Complete' : report.evidence_status === 'incomplete' ? 'Incomplete evidence' : 'Historic evidence unclassified'}</span></td><td>{report.result ?? 'Withheld'}</td><td>{report.created_at ?? 'Timestamp unavailable'}</td></tr>)}</tbody></table></div> : <p className="empty">No report run is available in the authorized management scope.</p>}</section>
      </>}

      {view === 'administration' && <section className="administration-grid" aria-label="Administration and settings">
        <section className="panel admin-group"><div className="section-heading"><div><p className="eyebrow">Organization</p><h2>Structure & access</h2></div></div><ActionLink href="/organization" title="Organization structure" description="Organizations, campuses, branches and positions" icon="settings" /><ActionLink href="/identity" title="Identity" description="People verification and accounts" icon="users" /><ActionLink href="/access" title="Access governance" description="Roles, scope and authority assignments" icon="settings" /></section>
        <section className="panel admin-group"><div className="section-heading"><div><p className="eyebrow">Academic</p><h2>Academic setup</h2></div></div><ActionLink href="/academic" title="Academic operations & setup" description="Periods, programs, levels, offerings, classes and delivery" icon="academic" /><ActionLink href="/teachers" title="Teacher capability" description="Assignments, qualifications, availability and workload" icon="teacher" /></section>
        <section className="panel admin-group"><div className="section-heading"><div><p className="eyebrow">Finance & people</p><h2>Operational authorities</h2></div></div><ActionLink href="/finance" title="Finance configuration & work" description="Authoritative monetary operations and exceptions" icon="finance" /><ActionLink href="/hr" title="HR & employment" description="Employment, contracts and leave" icon="teacher" /><ActionLink href="/payroll" title="Payroll" description="Payroll calculations and held exceptions" icon="finance" /></section>
        <section className="panel admin-group"><div className="section-heading"><div><p className="eyebrow">Governance</p><h2>Control & evidence</h2></div></div><ActionLink href="/reporting" title="Reports & dashboards" description="Reproducible projections and evidence status" icon="reporting" /><a className="quick-action command-action" href="/management?view=administration#governance"><span className="quick-icon" aria-hidden="true"><Icon name="settings" /></span><span><strong>System governance</strong><small>Audit, privacy and operational governance live in their canonical areas</small></span><Icon name="chevron" className="command-chevron" /></a></section>
      </section>}
    </main>
  </>;
}
