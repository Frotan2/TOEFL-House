import { useEffect, useMemo, useState } from 'react';
import { AppShell, Icon, PageStatus } from './ui';

type TeacherDayProps = {
  getJson: <T>(path: string) => Promise<T>;
  csrfToken: string;
};

type TeacherWorkspace = {
  viewer: { person_id: string; teacher_profile_id: string | null };
  capabilities: { manage: boolean; approve: boolean; is_teacher: boolean };
  profiles: Array<{
    id: string;
    person_id: string;
    legal_name: string;
    professional_title: string;
    current_home_branch_id: string;
    effective_state: string;
    assignments: Array<{ id: string; class_id: string; effective_from: string; effective_to: string | null; lifecycle_state: string | null }>;
    availability: Array<{ id: string; branch_id: string; weekday: number; starts_at: string; ends_at: string; state: string }>;
    workload_limits: Array<{ id: string; branch_id: string; max_hours_per_week: string; state: string }>;
  }>;
};

type AcademicSnapshot = {
  generated_at: string;
  calendar?: { gregorian: string; shamsi: string; shamsi_month_name: string; kabul_timezone: string; kabul_offset_minutes: number; version: string };
  classes: Array<{ id: string; branch_id: string; program_version_level_id: string | null; period_id: string; lifecycle_state: string; sections: Array<{ id: string; name: string; lifecycle_state: string }> }>;
  sessions: Array<{ id: string; class_id: string; scheduled_on: string; starts_at: string; ends_at: string; room: { id: string; name: string; code: string } | null; section: { id: string; name: string } | null; skill_id?: string | null }>;
  enrollments: Array<{ id: string; student_id: string; class_id: string; lifecycle_state: string }>;
  levels: Array<{ id: string; title: string }>;
  skills: Array<{ id: string; name: string }>;
};

type DaySession = AcademicSnapshot['sessions'][number] & { classRow: AcademicSnapshot['classes'][number]; level: string; skill: string; rosterCount: number };

// Calendar authority: business date from server Kabul, not browser UTC
type CalendarToday = { gregorian: string; shamsi: string; shamsi_month: number; shamsi_day: number; shamsi_month_name: string; kabul_timezone?: string; kabul_offset_minutes?: number; version?: string };
const CALENDAR_FALLBACK: CalendarToday = { gregorian: '1970-01-01', shamsi: '1348-10-11', shamsi_month: 10, shamsi_day: 11, shamsi_month_name: 'Jadi', kabul_timezone: 'Asia/Kabul', kabul_offset_minutes: 270, version: 'fallback' };
let calendarTodayCache: CalendarToday = CALENDAR_FALLBACK;

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function localDateKey(date = new Date()): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function parseMinutes(value: string): number {
  const [hours, minutes] = value.split(':').map(Number);
  return hours * 60 + minutes;
}

export function TeacherDayApp({ getJson, csrfToken }: TeacherDayProps) {
  const [teacher, setTeacher] = useState<TeacherWorkspace | null>(null);
  const [academic, setAcademic] = useState<AcademicSnapshot | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [date, setDate] = useState(calendarTodayCache.gregorian);

  useEffect(() => {
    setLoading(true);
    setError(null);
    void getJson<{ data: CalendarToday }>('/calendar/today').then((response) => {
      calendarTodayCache = response.data;
      setDate((current) => (current === CALENDAR_FALLBACK.gregorian ? response.data.gregorian : current));
    }).catch(() => { calendarTodayCache = CALENDAR_FALLBACK; });
    void Promise.all([
      getJson<{ data: TeacherWorkspace }>('/teachers/workspace'),
      getJson<AcademicSnapshot>('/academic/workspace'),
    ]).then(([teacherResponse, academicResponse]) => {
      setTeacher(teacherResponse.data);
      setAcademic(academicResponse);
      if (academicResponse.calendar) {
        calendarTodayCache = { ...CALENDAR_FALLBACK, ...academicResponse.calendar } as CalendarToday;
        setDate((current) => (current === CALENDAR_FALLBACK.gregorian ? academicResponse.calendar!.gregorian : current));
      }
    }).catch((reason: unknown) => {
      setError(reason instanceof Error ? reason.message : 'The Teacher Day workspace could not be resolved from canonical sources.');
    }).finally(() => setLoading(false));
  }, [getJson]);

  const profile = useMemo(() => {
    if (!teacher) return null;
    return teacher.profiles.find((item) => item.id === teacher.viewer.teacher_profile_id) ?? null;
  }, [teacher]);

  const assignedClassIds = useMemo(() => new Set(profile?.assignments.filter((item) => item.lifecycle_state !== 'ended' && item.lifecycle_state !== 'cancelled').map((item) => item.class_id) ?? []), [profile]);

  const sessions = useMemo<DaySession[]>(() => {
    if (!academic) return [];
    return academic.sessions
      .filter((session) => session.scheduled_on === date && assignedClassIds.has(session.class_id))
      .map((session) => {
        const classRow = academic.classes.find((item) => item.id === session.class_id);
        if (!classRow) return null;
        const rosterCount = academic.enrollments.filter((item) => item.class_id === session.class_id && ['active', 'frozen'].includes(item.lifecycle_state)).length;
        const level = academic.levels.find((item) => item.id === classRow.program_version_level_id)?.title ?? classRow.program_version_level_id ?? 'Level unavailable';
        const skill = academic.skills.find((item) => item.id === session.skill_id)?.name ?? 'Skill not specified';
        return { ...session, classRow, level, skill, rosterCount };
      })
      .filter((item): item is DaySession => item !== null)
      .sort((a, b) => parseMinutes(a.starts_at) - parseMinutes(b.starts_at));
  }, [academic, assignedClassIds, date]);

  const nowMinutes = date === calendarTodayCache.gregorian ? new Date().getHours() * 60 + new Date().getMinutes() : -1;
  const currentIndex = sessions.findIndex((session) => nowMinutes >= parseMinutes(session.starts_at) && nowMinutes < parseMinutes(session.ends_at));
  const totalMinutes = sessions.reduce((sum, session) => sum + Math.max(0, parseMinutes(session.ends_at) - parseMinutes(session.starts_at)), 0);
  const readinessWarnings = [
    !profile ? 'No teacher profile is attached to the signed-in identity.' : null,
    profile && profile.effective_state !== 'active' ? `Teacher profile is ${humanize(profile.effective_state)}; delivery authority may be unavailable.` : null,
    sessions.some((session) => !session.room) ? 'One or more sessions have no room assigned.' : null,
    sessions.some((session) => session.skill === 'Skill not specified') ? 'One or more sessions do not expose a teaching skill.' : null,
  ].filter((item): item is string => item !== null);

  if (loading) return <><AppShell current="teachers" csrfToken={csrfToken} /><PageStatus>Building the teacher's day from current assignments and timetable authority…</PageStatus></>;
  if (!teacher || !academic) return <><AppShell current="teachers" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'Teacher Day data is unavailable.'}</div><a className="button secondary" href="/teachers">Open faculty workspace</a></main></>;

  return <>
    <AppShell current="teachers" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace teacher-day" aria-labelledby="teacher-day-title">
      <header className="workspace-header day-header">
        <div><p className="eyebrow">People & Faculty · Daily workspace · {calendarTodayCache.shamsi} {calendarTodayCache.shamsi_month_name} · {calendarTodayCache.gregorian} · Kabul {calendarTodayCache.kabul_timezone ?? 'Asia/Kabul'}</p><h1 id="teacher-day-title">Teach the day, not the database.</h1><p className="lede">{profile?.legal_name ?? 'Teacher'} · scheduled sessions are matched against effective class assignments from the canonical People and Academic projections. Calendar: Kabul AFT (UTC+04:30) · Shamsi via version-1 authority.</p></div>
        <div className="teacher-day-actions"><a className="button secondary" href="/teachers">Faculty profile</a><input aria-label="Teaching date" type="date" value={date} onChange={(event) => setDate(event.target.value)} /></div>
      </header>

      {error && <div className="alert" role="alert">{error}</div>}

      <section className="day-brief" aria-label="Teacher daily brief">
        <div className="day-brief-main"><span className="eyebrow">Daily brief · {calendarTodayCache.gregorian} · {calendarTodayCache.shamsi}</span><strong>{sessions.length === 0 ? 'No teaching sessions are scheduled for this date.' : `${sessions.length} teaching session${sessions.length === 1 ? '' : 's'} scheduled.`}</strong><span>{profile?.professional_title ?? 'Faculty member'} · {totalMinutes / 60 || 0} teaching hours projected · {(() => { try { return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'Asia/Kabul' }).format(new Date(academic.generated_at)); } catch { return academic.generated_at; } })()}</span></div>
        <div className="day-brief-stat"><strong>{sessions.length}</strong><span>Sessions</span></div>
        <div className="day-brief-stat"><strong>{sessions.reduce((sum, session) => sum + session.rosterCount, 0)}</strong><span>Active learners</span></div>
        <div className="day-brief-stat"><strong>{profile?.effective_state === 'active' ? 'Ready' : 'Review'}</strong><span>Faculty state</span></div>
      </section>

      {readinessWarnings.length > 0 && <div className="day-warning" role="status"><strong>Operational attention:</strong> {readinessWarnings.join(' ')}</div>}

      <section className="day-layout">
        <section className="panel" aria-labelledby="day-schedule-heading">
          <div className="section-heading"><div><p className="eyebrow">Today / selected date · Kabul {calendarTodayCache.kabul_timezone ?? 'Asia/Kabul'}</p><h2 id="day-schedule-heading">Teaching schedule</h2></div><span className="source-note">{sessions.length} session{sessions.length === 1 ? '' : 's'} · source-owned timetable</span></div>
          {sessions.length === 0 ? <div className="empty-state"><strong>No scheduled teaching.</strong><p>There are no sessions on {date} for the teacher's currently effective class assignments.</p><a className="button secondary" href="/academic">Review timetable</a></div> : <div className="day-sessions">{sessions.map((session, index) => <a className={`day-session ${index === currentIndex ? 'is-current' : ''}`} href={`/academic#session-${encodeURIComponent(session.id)}`} key={session.id}><span className="day-session-time"><strong>{session.starts_at}</strong><small>to {session.ends_at}</small></span><span className="day-session-main"><strong>{session.level}</strong><span>{session.classRow.branch_id} · {session.classRow.period_id}</span><span>{session.skill} · {session.rosterCount} active learners</span><small>{session.room ? `${session.room.code} · ${session.room.name}` : 'Room not assigned'}{session.section ? ` · ${session.section.name}` : ''}</small></span><span className="day-session-meta"><span className={`status-chip ${session.classRow.lifecycle_state}`}>{humanize(session.classRow.lifecycle_state)}</span>{index === currentIndex && <span className="status-chip active">Live now</span>}<Icon name="chevron" /></span></a>)}</div>}
        </section>

        <aside className="day-side">
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Faculty context</p><h2>Authority snapshot</h2></div></div><div className="day-facts"><div><strong>Home branch</strong><span>{profile?.current_home_branch_id ?? 'Not resolved'}</span></div><div><strong>Effective assignments</strong><span>{profile?.assignments.filter((item) => item.lifecycle_state !== 'ended' && item.lifecycle_state !== 'cancelled').length ?? 0}</span></div><div><strong>Weekly limit</strong><span>{profile?.workload_limits.find((item) => item.state === 'active')?.max_hours_per_week ?? 'Not declared'} hours</span></div><div><strong>Selected date</strong><span>{date} · {calendarTodayCache.shamsi} {calendarTodayCache.shamsi_month_name}</span></div><div><strong>Calendar authority</strong><span>{calendarTodayCache.kabul_timezone ?? 'Asia/Kabul'} +{calendarTodayCache.kabul_offset_minutes ?? 270}m · v{calendarTodayCache.version ?? '1'}</span></div></div></section>
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Operator checklist</p><h2>Before class</h2></div></div><div className="day-task-list"><div className="day-task"><strong>Verify room</strong><span>Confirm the scheduled room is available and matches the timetable authority.</span></div><div className="day-task"><strong>Know the roster</strong><span>{sessions.reduce((sum, session) => sum + session.rosterCount, 0)} active learner places are associated with today's visible sessions.</span></div><div className="day-task"><strong>Record attendance</strong><span>Attendance belongs to the Academic attendance command; this workspace should never invent or overwrite facts.</span></div><div className="day-task"><strong>Use the class workspace</strong><span>Assessment, progression and grades remain in the canonical Academic workflow.</span></div></div></section>
          <section className="panel"><div className="section-heading"><div><p className="eyebrow">Navigation</p><h2>Open the right authority</h2></div></div><div className="day-task-list"><a className="quick-action command-action" href="/academic"><span className="quick-icon"><Icon name="academic" /></span><span><strong>Academic operations</strong><small>Attendance, assessment and progression</small></span><Icon name="chevron" /></a><a className="quick-action command-action" href="/teachers"><span className="quick-icon"><Icon name="teacher" /></span><span><strong>Faculty profile</strong><small>Qualifications, capability and assignment history</small></span><Icon name="chevron" /></a></div></section>
        </aside>
      </section>
    </main>
  </>;
}
