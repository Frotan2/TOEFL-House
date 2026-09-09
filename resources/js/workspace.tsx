import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AppShell, Icon, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type ApiEnvelope<T> = { data: T };
type Me = { username: string; person_id: string; display_name: string };
type Position = { id: string; position_id: string; position_name: string; effective_from: string; effective_to: string | null };
type WorkItem = { id?: string; organization_id?: string | null; branch_id?: string | null; kind: string; source_type: string; source_id: string; title: string; status: string; due_at: string | null; route: string; reason?: string | null };
type NotificationItem = { id: string; source_type: string; source_id: string; title: string; body_ref: string | null; severity: string; status: string; read_at: string | null; created_at: string | null };
type EmployeeWorkspace = { actor: { id: string; display_name: string }; positions: Position[]; scope: { organization_ids: string[]; branch_ids: string[]; scope_known: boolean }; work: { items: WorkItem[]; count: number }; notifications: { status: string; unread_count: number; items: NotificationItem[] }; generated_at: string };
type SearchResult = { type: string; id: string; label: string; secondary: string; status?: string; route: string };
type SearchResponse = { term: string; results: SearchResult[]; scope: { branch_ids: string[]; scope_known: boolean } };

function humanize(value: string): string { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function dueRank(value: string | null): number {
  if (!value) return 3;
  const parsed = Date.parse(value);
  if (Number.isNaN(parsed)) return 2;
  const delta = parsed - Date.now();
  if (delta < 0) return 0;
  if (delta < 24 * 60 * 60 * 1000) return 1;
  return 2;
}

export function WorkspaceApp({ getJson, postJson, csrfToken }: ApiClient & { csrfToken: string }) {
  const [me, setMe] = useState<Me | null>(null);
  const [workspace, setWorkspace] = useState<EmployeeWorkspace | null>(null);
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState<SearchResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    void Promise.all([getJson<ApiEnvelope<Me>>('/me'), getJson<ApiEnvelope<EmployeeWorkspace>>('/workspace')])
      .then(([meResponse, workspaceResponse]) => { setMe(meResponse.data); setWorkspace(workspaceResponse.data); })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The workspace could not be loaded.'))
      .finally(() => setLoading(false));
  }, [getJson]);

  const reloadWorkspace = () => getJson<ApiEnvelope<EmployeeWorkspace>>('/workspace').then((response) => setWorkspace(response.data));
  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const term = query.trim();
    if (term.length < 2) { setSearch(null); return; }
    setSearching(true); setError(null);
    void getJson<ApiEnvelope<SearchResponse>>(`/search?q=${encodeURIComponent(term)}`)
      .then((response) => setSearch(response.data))
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Search could not be completed.'))
      .finally(() => setSearching(false));
  };
  const markNotification = (id: string, state: 'read' | 'dismiss') => {
    setError(null);
    void postJson(`/notifications/${encodeURIComponent(id)}/${state}`).then(reloadWorkspace).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Notification state could not be changed.'));
  };
  const transitionWorkItem = (item: WorkItem, state: string) => {
    if (!item.id) return;
    setError(null);
    void postJson(`/work-items/${encodeURIComponent(item.id)}/transition`, { to_state: state }).then(reloadWorkspace).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Work state could not be changed.'));
  };
  const openItems = useMemo(() => workspace?.work.items ?? [], [workspace]);
  const prioritizedItems = useMemo(() => [...openItems].sort((a, b) => dueRank(a.due_at) - dueRank(b.due_at)).slice(0, 8), [openItems]);
  const overdueCount = openItems.filter((item) => dueRank(item.due_at) === 0).length;
  const todayCount = openItems.filter((item) => dueRank(item.due_at) === 1).length;
  const positionSummary = workspace?.positions.map((item) => item.position_name).filter(Boolean).slice(0, 2).join(' · ') || 'Authorized employee';

  if (loading) return <><AppShell current="workspace" csrfToken={csrfToken} /><PageStatus>Building your authorized workspace…</PageStatus></>;

  return <>
    <AppShell current="workspace" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace work-home" aria-labelledby="workspace-title">
      <header className="workspace-header home-hero">
        <div>
          <p className="eyebrow">My work · {positionSummary}</p>
          <h1 id="workspace-title">Focus on what needs to move.</h1>
          <p className="lede">{me ? `${me.display_name}, your workspace shows only live work and records authorized for your effective scope.` : 'Your workspace shows only live work and records authorized for your effective scope.'}</p>
        </div>
        <div className="hero-actions"><a className="button" href="#work-queue">Open priority queue</a><a className="button secondary" href="/management">Open command center</a></div>
      </header>

      {error && <div className="alert" role="alert">{error}</div>}

      <section className="operating-summary" aria-label="Operating brief">
        <div className="operating-summary-main"><span className="eyebrow">Operating brief</span><strong>{overdueCount > 0 ? `${overdueCount} item${overdueCount === 1 ? '' : 's'} need attention now.` : todayCount > 0 ? `${todayCount} item${todayCount === 1 ? '' : 's'} are due today.` : 'No urgent due items are visible.'}</strong><span>{workspace?.scope.branch_ids.length ?? 0} branch scope{(workspace?.scope.branch_ids.length ?? 0) === 1 ? '' : 's'} · snapshot {workspace?.generated_at ?? 'time unavailable'}</span></div>
        <div className="operating-summary-stat"><strong>{openItems.length}</strong><span>Open work</span></div>
        <div className="operating-summary-stat"><strong>{workspace?.notifications.unread_count ?? 0}</strong><span>Unread signals</span></div>
      </section>

      <form className="search global-search" onSubmit={submitSearch} role="search">
        <div className="search-label"><Icon name="search" /><label htmlFor="workspace-search">Search the records you can see</label></div>
        <div className="search-row"><input id="workspace-search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Student, visitor, code, email or phone" /><button className="button" type="submit" disabled={searching}>{searching ? 'Searching…' : 'Search'}</button></div>
        {search && <div className="search-results" aria-live="polite"><strong>{search.results.length} result{search.results.length === 1 ? '' : 's'}</strong>{search.results.length === 0 ? <p className="muted">No matching record in your visible branches.</p> : <ul>{search.results.map((result) => <li key={`${result.type}-${result.id}`}><a href={result.route}>{result.label}</a><span>{result.secondary}{result.status ? ` · ${result.status}` : ''}</span></li>)}</ul>}</div>}
      </form>

      <section className="summary-grid workspace-summary" aria-label="Workspace summary">
        <div className="panel"><span className="metric">{openItems.length}</span><span className="metric-label">Open work items</span><small className="metric-detail">Server-projected</small></div>
        <div className="panel"><span className="metric">{overdueCount}</span><span className="metric-label">Overdue</span><small className="metric-detail">Requires immediate review</small></div>
        <div className="panel"><span className="metric">{workspace?.positions.length ?? 0}</span><span className="metric-label">Effective positions</span><small className="metric-detail">Current authority context</small></div>
        <div className="panel"><span className="metric">{workspace?.scope.branch_ids.length ?? 0}</span><span className="metric-label">Visible branches</span><small className="metric-detail">Scope enforced server-side</small></div>
      </section>

      <section aria-label="Common work areas">
        <div className="section-heading"><div><p className="eyebrow">Workspaces</p><h2>Go straight to the job</h2></div><span className="source-note">Task-oriented navigation · source modules own state</span></div>
        <div className="quick-actions work-quick-actions">
          <a className="quick-action" href="/students"><span className="quick-icon" aria-hidden="true"><Icon name="users" /></span><span><strong>Students & admissions</strong><small>Intake, decisions, learner lifecycle and records</small></span><Icon name="chevron" /></a>
          <a className="quick-action" href="/academic"><span className="quick-icon" aria-hidden="true"><Icon name="academic" /></span><span><strong>Academic operations</strong><small>Classes, timetable, attendance, assessment and progression</small></span><Icon name="chevron" /></a>
          <a className="quick-action" href="/crm"><span className="quick-icon" aria-hidden="true"><Icon name="crm" /></span><span><strong>Front office / CRM</strong><small>Visitors, follow-up, conversations and intake</small></span><Icon name="chevron" /></a>
          <a className="quick-action" href="/finance"><span className="quick-icon" aria-hidden="true"><Icon name="finance" /></span><span><strong>Finance operations</strong><small>Payments, obligations and financial exceptions</small></span><Icon name="chevron" /></a>
        </div>
      </section>

      <div className="home-grid">
        <section id="work-queue" className="panel priority-queue" aria-labelledby="work-heading">
          <div className="section-heading"><div><p className="eyebrow">Priority queue</p><h2 id="work-heading">What should move next</h2></div><span className="source-note">{openItems.length} live item{openItems.length === 1 ? '' : 's'}</span></div>
          {prioritizedItems.length === 0 ? <div className="empty-state"><strong>Your queue is clear.</strong><p>No open work was found in your current effective scope.</p><a className="button secondary" href="/management">Check command-center health</a></div> : <ul className="work-list">{prioritizedItems.map((item, index) => <li key={item.id ?? `${item.source_type}-${item.source_id}-${index}`}><div><span className={`kind ${dueRank(item.due_at) === 0 ? 'urgent' : ''}`}>{dueRank(item.due_at) === 0 ? 'Urgent' : humanize(item.kind)}</span><a href={item.route}>{item.title}</a><small>{item.due_at ? `Due ${item.due_at}` : `${item.source_type} · ${item.source_id}`}{item.reason ? ` · ${item.reason}` : ''}</small></div><div className="work-actions"><span className={`status-chip ${item.status}`}>{humanize(item.status)}</span>{item.id && item.status === 'open' && <button className="text-button" type="button" onClick={() => transitionWorkItem(item, 'claimed')}>Claim</button>}{item.id && item.status === 'claimed' && <button className="text-button" type="button" onClick={() => transitionWorkItem(item, 'in_progress')}>Start</button>}{item.id && item.status === 'in_progress' && <button className="text-button" type="button" onClick={() => transitionWorkItem(item, 'completed')}>Complete coordination</button>}</div></li>)}</ul>}
          {openItems.length > prioritizedItems.length && <a className="text-button queue-more" href="#work-queue">Showing the eight highest-priority items · server result contains more</a>}
        </section>

        <section className="panel" aria-labelledby="notifications-heading">
          <div className="section-heading"><div><p className="eyebrow">Signals</p><h2 id="notifications-heading">Notifications</h2></div><span className="source-note">{workspace?.notifications.unread_count ?? 0} unread</span></div>
          {(workspace?.notifications.items.length ?? 0) === 0 ? <p className="empty">No active notifications. There is nothing new in your feed.</p> : <ul className="notification-list">{workspace?.notifications.items.map((notification) => <li key={notification.id} className={notification.status === 'unread' ? 'unread' : ''}><div><strong>{notification.title}</strong><small>{humanize(notification.severity)} · {notification.created_at ?? 'Time unavailable'} · {notification.source_type} · {notification.source_id}</small></div><div className="notification-actions">{notification.status === 'unread' && <button className="text-button" type="button" onClick={() => markNotification(notification.id, 'read')}>Mark read</button>}<button className="text-button" type="button" onClick={() => markNotification(notification.id, 'dismiss')}>Dismiss</button></div></li>)}</ul>}
        </section>
      </div>
    </main>
  </>;
}
