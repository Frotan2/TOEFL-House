import { useEffect, useMemo, useState } from 'react';
import { AppShell, Icon, PageStatus } from './ui';
import type { AcademicClass } from './academic';

type AcademicSetupData = {
  generated_at: string;
  scope: { branches: Array<{ id: string; name: string; organization_id: string | null; campus_id: string | null }>; branch_ids: string[] };
  classes: AcademicClass[];
  offerings: Array<{ id: string; branch_id: string; program_version_level_id: string; academic_period_id: string; lifecycle_state: string; classes_count: number }>;
  availabilities: Array<{ id: string; branch_id: string; program_version_level_id: string; academic_period_id: string; lifecycle_state: string }>;
  sessions: Array<{ id: string; class_id: string; scheduled_on: string; starts_at: string; ends_at: string; room_id: string | null }>;
  teachers: Array<{ id: string; name: string; branch_id: string }>;
  rooms: Array<{ id: string; branch_id: string; name: string; code: string; capacity: number }>;
  periods: Array<{ id: string; name: string; starts_on: string; ends_on: string; lifecycle_state: string }>;
  program_versions: Array<{ id: string; program_id: string; program_name: string; version_no: number; summary: string }>;
  levels: Array<{ id: string; program_version_id: string; level_key: string; ordinal: number; title: string; cefr_ref: string | null; lifecycle_state: string }>;
  skills: Array<{ id: string; key: string; name: string }>;
};

type PhaseKey = 'infrastructure' | 'curriculum' | 'delivery';

type Phase = {
  key: PhaseKey;
  number: string;
  title: string;
  description: string;
  href: string;
};

const phases: Phase[] = [
  { key: 'infrastructure', number: '01', title: 'Infrastructure', description: 'Establish the academic calendar and the organizational delivery context.', href: '/academic' },
  { key: 'curriculum', number: '02', title: 'Curriculum', description: 'Define program versions, levels, skills and progression structure.', href: '/academic' },
  { key: 'delivery', number: '03', title: 'Course delivery', description: 'Turn the curriculum into offerings, classes, people, rooms and sessions.', href: '/academic' },
];

function stateLabel(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function PhaseStatus({ ready }: { ready: boolean }) {
  return <span className={`setup-phase-status ${ready ? 'ready' : 'attention'}`}><span aria-hidden="true">{ready ? '✓' : '!'}</span>{ready ? 'Ready' : 'Needs setup'}</span>;
}

export function AcademicSetupApp({ getJson, csrfToken }: { getJson: <T>(path: string) => Promise<T>; csrfToken: string }) {
  const [data, setData] = useState<AcademicSetupData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [phase, setPhase] = useState<PhaseKey>(() => {
    const requested = new URLSearchParams(window.location.search).get('phase');
    return requested === 'curriculum' || requested === 'delivery' ? requested : 'infrastructure';
  });

  useEffect(() => {
    void getJson<AcademicSetupData>('/academic/workspace')
      .then(setData)
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The Academic Setup workspace could not be loaded.'))
      .finally(() => setLoading(false));
  }, [getJson]);

  const derived = useMemo(() => {
    if (!data) return null;
    const publishedPeriods = data.periods.filter((item) => item.lifecycle_state === 'published').length;
    const activePrograms = data.program_versions.length;
    const activeLevels = data.levels.filter((item) => item.lifecycle_state !== 'retired').length;
    const activeSkills = data.skills.length;
    const openOfferings = data.offerings.filter((item) => item.lifecycle_state === 'open').length;
    const liveClasses = data.classes.filter((item) => ['active', 'planned'].includes(item.lifecycle_state)).length;
    const rooms = data.rooms.length;
    const teachers = data.teachers.length;
    const scheduledSessions = data.sessions.length;
    return {
      infrastructureReady: publishedPeriods > 0 && data.scope.branch_ids.length > 0,
      curriculumReady: activePrograms > 0 && activeLevels > 0 && activeSkills > 0,
      deliveryReady: openOfferings > 0 && liveClasses > 0 && rooms > 0 && teachers > 0 && scheduledSessions > 0,
      publishedPeriods, activePrograms, activeLevels, activeSkills, openOfferings, liveClasses, rooms, teachers, scheduledSessions,
    };
  }, [data]);

  useEffect(() => {
    if (!data || !derived) return;
    const nextPhase: PhaseKey = !derived.infrastructureReady ? 'infrastructure' : !derived.curriculumReady ? 'curriculum' : !derived.deliveryReady ? 'delivery' : phase;
    if (nextPhase !== phase) setPhase(nextPhase);
  }, [data, derived, phase]);

  const selectPhase = (next: PhaseKey) => {
    setPhase(next);
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'setup');
    url.searchParams.set('phase', next);
    window.history.replaceState({}, '', url);
  };

  if (loading) return <><AppShell current="academic" csrfToken={csrfToken} /><PageStatus>Resolving academic setup readiness…</PageStatus></>;
  if (!data || !derived) return <><AppShell current="academic" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'No academic setup projection is available.'}</div></main></>;

  const phaseState: Record<PhaseKey, boolean> = { infrastructure: derived.infrastructureReady, curriculum: derived.curriculumReady, delivery: derived.deliveryReady };
  const phaseData = {
    infrastructure: [
      { label: 'Authorized branches', value: String(data.scope.branch_ids.length), detail: data.scope.branches.map((item) => item.name).join(' · ') || 'No branch is visible in scope', ready: data.scope.branch_ids.length > 0, href: '/organization' },
      { label: 'Published academic periods', value: String(derived.publishedPeriods), detail: data.periods.map((item) => `${item.name} · ${stateLabel(item.lifecycle_state)}`).join(' · ') || 'No period exists', ready: derived.publishedPeriods > 0, href: '/academic' },
    ],
    curriculum: [
      { label: 'Program versions', value: String(derived.activePrograms), detail: data.program_versions.slice(0, 3).map((item) => `${item.program_name} v${item.version_no}`).join(' · ') || 'No program version exists', ready: derived.activePrograms > 0, href: '/academic' },
      { label: 'Levels', value: String(derived.activeLevels), detail: data.levels.slice(0, 5).map((item) => item.title).join(' · ') || 'No level is defined', ready: derived.activeLevels > 0, href: '/academic' },
      { label: 'Skills', value: String(derived.activeSkills), detail: data.skills.slice(0, 5).map((item) => item.name).join(' · ') || 'No skill is defined', ready: derived.activeSkills > 0, href: '/academic' },
    ],
    delivery: [
      { label: 'Open offerings', value: String(derived.openOfferings), detail: `${data.offerings.length} total offering records`, ready: derived.openOfferings > 0, href: '/academic' },
      { label: 'Planned / active classes', value: String(derived.liveClasses), detail: `${data.classes.length} classes visible in scope`, ready: derived.liveClasses > 0, href: '/academic' },
      { label: 'Teachers', value: String(derived.teachers), detail: 'Visible faculty resource records', ready: derived.teachers > 0, href: '/teachers' },
      { label: 'Rooms', value: String(derived.rooms), detail: 'Visible teaching rooms', ready: derived.rooms > 0, href: '/organization' },
      { label: 'Scheduled sessions', value: String(derived.scheduledSessions), detail: 'Timetable rows visible in scope', ready: derived.scheduledSessions > 0, href: '/academic' },
    ],
  }[phase];

  return <>
    <AppShell current="academic" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace academic-setup" aria-labelledby="academic-setup-title">
      <header className="workspace-header setup-header">
        <div><p className="eyebrow">Academic Operations · Setup</p><h1 id="academic-setup-title">Build a delivery-ready academic operation.</h1><p className="lede">A guided control center over the existing Academic authority. Each step reads live server facts and sends you to the owning workspace for changes.</p></div>
        <div className="setup-header-actions"><span className="source-note">Snapshot · {(() => { try { return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'Asia/Kabul' }).format(new Date(data.generated_at)); } catch { return new Date(data.generated_at).toISOString(); } })()}</span><a className="button secondary" href="/academic">Open full academic workspace</a></div>
      </header>

      {error && <div className="alert" role="alert">{error}</div>}

      <section className="setup-hero panel" aria-label="Setup readiness summary">
        <div className="setup-hero-copy"><p className="eyebrow">Readiness</p><strong>{phaseState.infrastructure && phaseState.curriculum && phaseState.delivery ? 'Academic delivery is ready for operational use.' : 'Complete the highlighted setup step before relying on downstream delivery.'}</strong><span>Readiness is a product view only; domain policy and transitions remain server-authoritative.</span></div>
        <div className="setup-score"><strong>{Object.values(phaseState).filter(Boolean).length}/3</strong><span>phases ready</span></div>
      </section>

      <section className="setup-layout">
        <aside className="panel setup-rail" aria-label="Academic setup phases">
          <div className="section-heading"><div><p className="eyebrow">Setup path</p><h2>Three phases</h2></div></div>
          <div className="setup-steps">{phases.map((item, index) => <button key={item.key} type="button" className={`setup-step ${phase === item.key ? 'active' : ''}`} onClick={() => selectPhase(item.key)}><span className="setup-step-number">{item.number}</span><span className="setup-step-copy"><strong>{item.title}</strong><small>{item.description}</small></span><PhaseStatus ready={phaseState[item.key]} /></button>)}</div>
          <div className="setup-principle"><strong>Why this order?</strong><p>Delivery depends on curriculum, and curriculum depends on an active academic context. The UI keeps those dependencies visible instead of hiding them in configuration screens.</p></div>
        </aside>

        <section className="panel setup-content" aria-labelledby="setup-phase-title">
          <div className="section-heading"><div><p className="eyebrow">Phase {phases.findIndex((item) => item.key === phase) + 1}</p><h2 id="setup-phase-title">{phases.find((item) => item.key === phase)?.title}</h2><p className="muted">{phases.find((item) => item.key === phase)?.description}</p></div><span className="scope-badge">{phaseState[phase] ? 'Ready' : 'Attention required'}</span></div>
          <div className="setup-checklist">{phaseData.map((item) => <a className={`setup-check ${item.ready ? 'ready' : 'blocked'}`} href={item.href} key={item.label}><span className="setup-check-icon" aria-hidden="true">{item.ready ? '✓' : '!'}</span><span className="setup-check-body"><strong>{item.label}</strong><small>{item.detail}</small></span><span className="setup-check-value">{item.value}</span><Icon name="chevron" /></a>)}</div>

          {phase === 'infrastructure' && <div className="setup-next"><span className="setup-next-kicker">Next action</span><strong>{derived.infrastructureReady ? 'Move into Curriculum.' : 'Publish an Academic Period and confirm the delivery branch scope.'}</strong><p>The setup page does not duplicate period or organization forms. Use the owning workspace so every change follows the canonical command and audit path.</p><a className="button" href={derived.infrastructureReady ? '/academic?view=setup&phase=curriculum' : '/academic'}>{derived.infrastructureReady ? 'Continue to Curriculum' : 'Open Academic workspace'}</a></div>}
          {phase === 'curriculum' && <div className="setup-next"><span className="setup-next-kicker">Next action</span><strong>{derived.curriculumReady ? 'Curriculum foundation is complete.' : 'Complete program version, levels, and skills before creating delivery objects.'}</strong><p>Program versions and levels are the reusable curriculum contract; skills become the smallest teaching units that later appear in delivery.</p><a className="button" href={derived.curriculumReady ? '/academic?view=setup&phase=delivery' : '/academic'}>{derived.curriculumReady ? 'Continue to Course delivery' : 'Open Curriculum workspace'}</a></div>}
          {phase === 'delivery' && <div className="setup-next"><span className="setup-next-kicker">Next action</span><strong>{derived.deliveryReady ? 'Delivery chain has enough live evidence to operate.' : 'Finish offerings, classes, resources, faculty and timetable before calling delivery ready.'}</strong><p>Do not treat the readiness score as authorization. Every create/change action remains inside its owning server command surface.</p><a className="button" href="/academic">Open delivery workspace</a></div>}
        </section>
      </section>
    </main>
  </>;
}
