import { useEffect, useMemo, useState } from 'react';
import { AppShell, Icon, PageStatus } from './ui';

type JourneyData = {
  student_id: string;
  student_code: string;
  person: { person_id: string; legal_name: string; verified: boolean };
  admission: { program_interest: string | null; outcome: string; lifecycle_state: string; reason: string; evidence_ref: string; created_at: string | null } | null;
  originating_branch_id: string;
  current_home_branch_id: string;
  branch_provenance: { originating: { id: string; name: string } | null; current_home: { id: string; name: string } | null };
  placement: { lifecycle_state: string; recommended_level_id: string; overall_cefr_ref: string | null } | null;
  status: string | null;
  status_history: Array<{ id: string; status: string; effective_from: string; reason: string; actor_id: string }>;
  holds: { open: boolean; history: Array<{ action: string; effective_from: string; reason: string }> };
  branch_transfers: Array<{ from_branch_id: string; to_branch_id: string; effective_from: string; reason: string }>;
  enrollments: Array<{ id: string; class_id: string; lifecycle_state: string; period_name?: string | null }>;
  attendance: { present: number; absent: number; late: number; excused: number };
  assessment_results: Array<{ id: string; kind: string; score: string | number | null; lifecycle_state: string; evidence_ref: string }>;
  progression_decisions: Array<{ id: string; outcome: string; lifecycle_state: string; reason: string }>;
  obligations: Array<{ id: string; original_amount: string | number; source: string; reason: string; originating_branch_id: string; current_home_branch_id: string }>;
  payments: Array<{ id: string; amount: string | number; method: string; received_on: string; originating_branch_id: string; current_home_branch_id: string }>;
  documents: Array<{ id: string; title: string; lifecycle_state: string; version_no: number | null }>;
  messages: Array<{ id: string; channel: string; lifecycle_state: string; created_at?: string }>;
  audit_events: Array<{ id: string; operation: string; occurred_at: string | null; occurred_time_basis: 'database_insert' | null; time_evidence_status: 'database_recorded' | 'historic_unclassified'; actor_id: string }>;
  workflow: Array<{ type: string; label: string }>;
};

type JourneyEvent = { at: string | null; title: string; detail: string; kind: string; confidence: string };

function humanize(value: string): string { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }

function buildEvents(data: JourneyData): JourneyEvent[] {
  const events: JourneyEvent[] = [];
  if (data.admission) events.push({ at: data.admission.created_at, title: 'Admission decision', detail: `${humanize(data.admission.outcome)} · ${data.admission.reason} · evidence ${data.admission.evidence_ref}`, kind: 'admission', confidence: data.admission.created_at ? 'dated source record' : 'timestamp unavailable' });
  data.status_history.forEach((item) => events.push({ at: item.effective_from, title: `Status → ${humanize(item.status)}`, detail: item.reason, kind: 'status', confidence: 'effective-dated lifecycle fact' }));
  data.branch_transfers.forEach((item) => events.push({ at: item.effective_from, title: 'Home branch changed', detail: `${item.from_branch_id} → ${item.to_branch_id} · ${item.reason}`, kind: 'transfer', confidence: 'effective-dated branch fact' }));
  data.holds.history.forEach((item) => events.push({ at: item.effective_from, title: `Hold ${humanize(item.action)}`, detail: item.reason, kind: 'hold', confidence: 'effective-dated lifecycle fact' }));
  data.audit_events.forEach((item) => events.push({ at: item.occurred_at, title: humanize(item.operation), detail: `Actor ${item.actor_id}`, kind: 'audit', confidence: item.time_evidence_status === 'database_recorded' ? 'database recorded' : 'historic-unclassified' }));
  return events.sort((a, b) => {
    if (!a.at && !b.at) return 0;
    if (!a.at) return 1;
    if (!b.at) return -1;
    return Date.parse(b.at) - Date.parse(a.at);
  });
}

export function StudentJourneyApp({ getJson, csrfToken, studentId }: { getJson: <T>(path: string) => Promise<T>; csrfToken: string; studentId: string }) {
  const [data, setData] = useState<JourneyData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    if (!studentId) { setError('A student identifier is required for Journey view.'); setLoading(false); return; }
    void getJson<JourneyData>(`/students/${encodeURIComponent(studentId)}`)
      .then(setData)
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The learner journey could not be loaded.'))
      .finally(() => setLoading(false));
  }, [getJson, studentId]);

  const events = useMemo(() => data ? buildEvents(data) : [], [data]);
  if (loading) return <><AppShell current="students" csrfToken={csrfToken} /><PageStatus>Reconstructing the authoritative learner journey…</PageStatus></>;
  if (!data) return <><AppShell current="students" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'Journey data is unavailable.'}</div><a className="button secondary" href="/students">Return to students</a></main></>;

  const attendanceTotal = data.attendance.present + data.attendance.absent + data.attendance.late + data.attendance.excused;
  return <>
    <AppShell current="students" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace student-journey" aria-labelledby="journey-title">
      <div className="detail-back"><a className="text-button" href={`/students/${encodeURIComponent(studentId)}`}>← Back to learner record</a></div>
      <header className="workspace-header journey-header"><div><p className="eyebrow">Student Journey · authoritative view</p><h1 id="journey-title">{data.person.legal_name}</h1><p className="lede">{data.student_code} · {data.person.verified ? 'Verified identity' : 'Identity requires attention'} · current status {humanize(data.status ?? 'unavailable')}</p></div><div className="journey-actions"><a className="button secondary" href={`/students/${encodeURIComponent(studentId)}`}>Open learner controls</a></div></header>

      <section className="journey-hero panel"><div><p className="eyebrow">Current position</p><strong>{data.branch_provenance.current_home?.name ?? data.current_home_branch_id}</strong><span>Origin: {data.branch_provenance.originating?.name ?? data.originating_branch_id}</span>{data.placement && <span>Placement: {data.placement.recommended_level_id} {data.placement.overall_cefr_ref ? `· ${data.placement.overall_cefr_ref}` : ''}</span>}</div><div className="journey-workflow">{data.workflow.map((item) => <span key={`${item.type}-${item.label}`}><b>{item.type}</b>{item.label}</span>)}</div></section>

      <section className="summary-grid journey-summary" aria-label="Learner journey summary">
        <div className="panel"><span className="metric">{data.enrollments.length}</span><span className="metric-label">Enrollments</span><small className="metric-detail">Lifecycle memberships</small></div>
        <div className="panel"><span className="metric">{attendanceTotal}</span><span className="metric-label">Attendance records</span><small className="metric-detail">Present {data.attendance.present} · absent {data.attendance.absent}</small></div>
        <div className="panel"><span className="metric">{data.assessment_results.length}</span><span className="metric-label">Assessment results</span><small className="metric-detail">Server-owned evidence</small></div>
        <div className="panel"><span className="metric">{data.progression_decisions.length}</span><span className="metric-label">Progression decisions</span><small className="metric-detail">Reviewable academic history</small></div>
      </section>

      <section className="journey-grid">
        <section className="panel journey-timeline" aria-labelledby="timeline-heading"><div className="section-heading"><div><p className="eyebrow">Chronology</p><h2 id="timeline-heading">What happened</h2></div><span className="source-note">Only timestamped/effective-dated source facts are placed on the timeline.</span></div>{events.length === 0 ? <p className="empty">No dated journey events are visible in the current scope.</p> : <ol className="timeline">{events.map((event, index) => <li key={`${event.kind}-${event.at ?? 'undated'}-${index}`}><span className={`timeline-dot ${event.kind}`} aria-hidden="true" /><div className="timeline-card"><div className="timeline-meta"><span>{event.at ?? 'Date unavailable'}</span><span>{event.confidence}</span></div><strong>{event.title}</strong><p>{event.detail}</p></div></li>)}</ol>}</section>

        <aside className="journey-side">
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Learning snapshot</p><h2>Academic evidence</h2></div></div>{data.assessment_results.length === 0 && data.progression_decisions.length === 0 ? <p className="empty">No academic outcome evidence is currently visible.</p> : <div className="journey-evidence">{data.assessment_results.slice(0, 5).map((item) => <div key={item.id}><strong>{humanize(item.kind)}</strong><span>Score: {item.score ?? 'not released'} · {humanize(item.lifecycle_state)}</span><small>Evidence: {item.evidence_ref}</small></div>)}{data.progression_decisions.slice(0, 5).map((item) => <div key={item.id}><strong>Progression · {humanize(item.outcome)}</strong><span>{humanize(item.lifecycle_state)}</span><small>{item.reason}</small></div>)}</div>}</section>
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Financial context</p><h2>Obligations & payments</h2></div></div><div className="journey-evidence">{data.obligations.slice(0, 5).map((item) => <div key={item.id}><strong>Obligation</strong><span>{item.original_amount} · {item.source}</span><small>{item.reason}</small></div>)}{data.payments.slice(0, 5).map((item) => <div key={item.id}><strong>Payment</strong><span>{item.amount} · {item.method}</span><small>{item.received_on}</small></div>)}{data.obligations.length === 0 && data.payments.length === 0 && <p className="empty">No financial records are visible.</p>}</div></section>
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Artifacts</p><h2>Documents & communication</h2></div></div><p className="muted">{data.documents.length} document(s) · {data.messages.length} message(s).</p>{data.documents.slice(0, 5).map((item) => <p className="fact-line" key={item.id}>{item.title} · {humanize(item.lifecycle_state)} · v{item.version_no ?? '—'}</p>)}</section>
        </aside>
      </section>
    </main>
  </>;
}
