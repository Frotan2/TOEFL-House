import { useEffect, useMemo, useState } from 'react';
import { AppShell, PageStatus } from './ui';
import type { ApiClient } from './core/api';

type Visitor = { id: string; visitor_code: string; full_name: string; phone: string | null; email: string | null; status: string; preferred_channel: string; interest: string | null; origin_branch_id: string | null };
type Applicant = { id: string; person?: { legal_name?: string | null } | null; program_interest: string; lifecycle_state: string };
type Decision = { id: string; applicant_id: string; outcome: string; lifecycle_state: string };
type Capabilities = { admission_initiate: boolean; admission_review: boolean; admission_approve: boolean; admission_register: boolean; };
type Student = { id: string; student_code: string; person?: { legal_name?: string | null } | null; current_status?: string | null };
type StudentsIndex = { students: Student[]; applicants: Applicant[]; decisions: Decision[]; capabilities: Capabilities };
type AcademicWorkspace = { classes?: unknown[]; sessions?: unknown[]; branches?: unknown[]; periods?: unknown[] };

function humanize(value: string): string { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function actionForApplicant(applicant: Applicant, decision: Decision | undefined, capabilities: Capabilities): { label: string; href: string; tone: 'primary' | 'secondary' } {
  if (!decision && capabilities.admission_initiate) return { label: 'Start decision', href: `/students?view=admissions&applicant=${encodeURIComponent(applicant.id)}`, tone: 'primary' };
  if (decision?.lifecycle_state === 'proposed' && capabilities.admission_review) return { label: 'Review decision', href: `/students?view=admissions&applicant=${encodeURIComponent(applicant.id)}`, tone: 'primary' };
  if (decision?.lifecycle_state === 'reviewed' && capabilities.admission_approve) return { label: 'Approve decision', href: `/students?view=admissions&applicant=${encodeURIComponent(applicant.id)}`, tone: 'primary' };
  if (decision?.lifecycle_state === 'final' && decision.outcome === 'admit' && capabilities.admission_register) return { label: 'Enroll student', href: `/students?view=admissions&applicant=${encodeURIComponent(applicant.id)}`, tone: 'primary' };
  return { label: 'Open admissions', href: '/students?view=admissions', tone: 'secondary' };
}

export function FrontOfficeApp({ getJson, csrfToken }: ApiClient & { csrfToken: string }) {
  const [visitors, setVisitors] = useState<Visitor[]>([]);
  const [students, setStudents] = useState<StudentsIndex | null>(null);
  const [academic, setAcademic] = useState<AcademicWorkspace | null>(null);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true); setError(null);
    void Promise.all([
      getJson<{ visitors: Visitor[] }>('/crm/visitors?limit=200'),
      getJson<StudentsIndex>('/students'),
      getJson<AcademicWorkspace>('/academic/workspace'),
    ]).then(([crm, studentIndex, academicWorkspace]) => {
      setVisitors(crm.visitors ?? []);
      setStudents(studentIndex);
      setAcademic(academicWorkspace);
    }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Front Office operational data could not be loaded.')).finally(() => setLoading(false));
  }, []);

  const term = query.trim().toLowerCase();
  const visibleVisitors = useMemo(() => {
    const open = visitors.filter((visitor) => !['converted', 'closed', 'lost', 'rejected'].includes(visitor.status));
    if (!term) return open;
    return open.filter((visitor) => `${visitor.full_name} ${visitor.visitor_code} ${visitor.phone ?? ''} ${visitor.email ?? ''} ${visitor.status} ${visitor.interest ?? ''}`.toLowerCase().includes(term));
  }, [term, visitors]);
  const visibleStudents = useMemo(() => {
    if (!students) return [];
    if (!term) return students.students.slice(0, 10);
    return students.students.filter((student) => `${student.student_code} ${student.person?.legal_name ?? ''} ${student.current_status ?? ''}`.toLowerCase().includes(term)).slice(0, 10);
  }, [students, term]);
  const admissionQueue = useMemo(() => {
    if (!students) return [];
    const decisionByApplicant = new Map(students.decisions.map((decision) => [decision.applicant_id, decision]));
    return students.applicants.map((applicant) => ({ applicant, decision: decisionByApplicant.get(applicant.id), action: actionForApplicant(applicant, decisionByApplicant.get(applicant.id), students.capabilities) })).filter(({ applicant, decision }) => applicant.lifecycle_state === 'applicant' || applicant.lifecycle_state === 'rejected' || decision?.lifecycle_state === 'proposed' || decision?.lifecycle_state === 'reviewed');
  }, [students]);
  const activeStudents = students?.students.filter((student) => !['withdrawn', 'alumni'].includes(student.current_status ?? '')).length ?? 0;
  const classCount = academic?.classes?.length ?? 0;
  const sessionCount = academic?.sessions?.length ?? 0;

  if (loading) return <><AppShell current="crm" csrfToken={csrfToken} /><PageStatus>Loading the authorized Front Office brief…</PageStatus></>;
  return <>
    <AppShell current="crm" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace" aria-labelledby="front-office-title">
      <header className="workspace-header">
        <div><p className="eyebrow">Front Office · Reception desk</p><h1 id="front-office-title">The next action should be obvious.</h1><p className="lede">One operator view over CRM, admissions, and academic availability. Source systems remain authoritative.</p></div>
        <div className="detail-header-actions"><a className="button secondary" href="/crm">Open full CRM</a><a className="button secondary" href="/students?view=admissions">Open admissions</a></div>
      </header>
      {error && <div className="alert" role="alert">{error}</div>}
      <section className="summary-grid" aria-label="Front Office operating summary">
        <div className="panel"><span className="metric">{visibleVisitors.length}</span><span className="metric-label">Open visitor records</span></div>
        <div className="panel"><span className="metric">{admissionQueue.length}</span><span className="metric-label">Admissions requiring action</span></div>
        <div className="panel"><span className="metric">{activeStudents}</span><span className="metric-label">Students in current scope</span></div>
        <div className="panel"><span className="metric">{sessionCount}</span><span className="metric-label">Academic session facts</span></div>
      </section>

      <section className="front-office-toolbar panel" aria-label="Front Office search">
        <div><p className="eyebrow">Operator search</p><h2>Find the person who needs attention</h2></div>
        <label className="directory-filter">Search people<input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Name, phone, email, code, interest" /></label>
      </section>

      <div className="front-office-grid">
        <section className="panel" aria-labelledby="front-office-admissions-heading">
          <div className="section-heading"><div><p className="eyebrow">Admissions queue</p><h2 id="front-office-admissions-heading">Move applications to the next authority</h2></div><span className="source-note">Actions open the canonical Admissions workspace</span></div>
          {admissionQueue.length === 0 ? <p className="empty">No admissions record currently requires an operator action.</p> : <div className="front-office-list">{admissionQueue.slice(0, 10).map(({ applicant, decision, action }) => <article className="front-office-item" key={applicant.id}><div><strong>{applicant.person?.legal_name ?? 'Applicant'}</strong><span>{applicant.program_interest} · {humanize(decision?.lifecycle_state ?? applicant.lifecycle_state)}</span>{decision && <small>Decision: {humanize(decision.outcome)}</small>}</div><a className={`button ${action.tone === 'secondary' ? 'secondary' : ''}`} href={action.href}>{action.label}</a></article>)}</div>}
        </section>

        <section className="panel" aria-labelledby="front-office-visitors-heading">
          <div className="section-heading"><div><p className="eyebrow">Visitor pipeline</p><h2 id="front-office-visitors-heading">People already talking to us</h2></div><span className="source-note">CRM owns stage and timeline</span></div>
          {visibleVisitors.length === 0 ? <p className="empty">No open visitor records match the current search.</p> : <div className="front-office-list">{visibleVisitors.slice(0, 10).map((visitor) => <article className="front-office-item" key={visitor.id}><div><strong>{visitor.full_name}</strong><span>{humanize(visitor.status)} · {humanize(visitor.preferred_channel)}</span><small>{visitor.interest || visitor.phone || visitor.email || visitor.visitor_code}</small></div><a className="button secondary" href={`/crm?visitor=${encodeURIComponent(visitor.id)}`}>Open CRM</a></article>)}</div>}
        </section>
      </div>

      <section className="panel" aria-labelledby="front-office-students-heading">
        <div className="section-heading"><div><p className="eyebrow">Student lookup</p><h2 id="front-office-students-heading">Existing learners in scope</h2></div><span className="source-note">Directory facts · server scope enforced</span></div>
        {visibleStudents.length === 0 ? <p className="empty">No student record matches the current search.</p> : <div className="front-office-list">{visibleStudents.map((student) => <article className="front-office-item" key={student.id}><div><strong>{student.person?.legal_name ?? 'Student'}</strong><span>{student.student_code} · {humanize(student.current_status ?? 'status unavailable')}</span></div><a className="button secondary" href={`/students/${encodeURIComponent(student.id)}`}>Open student</a></article>)}</div>}
      </section>

      <section className="panel" aria-labelledby="front-office-context-heading">
        <div className="section-heading"><div><p className="eyebrow">Operating context</p><h2 id="front-office-context-heading">What the desk can see right now</h2></div><span className="source-note">Read projection only · no business decisions in React</span></div>
        <div className="front-office-context-grid"><div><strong>{classCount}</strong><span>authorized class facts</span></div><div><strong>{sessionCount}</strong><span>authorized session facts</span></div><div><strong>{students?.applicants.length ?? 0}</strong><span>applicant records</span></div><div><strong>{visitors.length}</strong><span>CRM visitor records</span></div></div>
      </section>
    </main>
  </>;
}
