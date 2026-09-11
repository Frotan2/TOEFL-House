import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { AppShell, PageStatus } from './ui';
// Calendar authority: business date from server Kabul, not browser UTC
type CalendarToday = { gregorian: string; shamsi?: string; shamsi_month_name?: string; kabul_timezone?: string; kabul_offset_minutes?: number; version?: string };
const CALENDAR_FALLBACK: CalendarToday = { gregorian: '1970-01-01', shamsi: '1348-10-11', shamsi_month_name: 'Jadi', kabul_timezone: 'Asia/Kabul', kabul_offset_minutes: 270, version: 'fallback' };
let calendarTodayCache: CalendarToday = CALENDAR_FALLBACK;
const authoritativeToday = (): string => {
  if (typeof console !== 'undefined' && calendarTodayCache === CALENDAR_FALLBACK) {
    console.warn('[CAL-01] Using fallback static date for CRM — should be server Kabul date from /api/v1/calendar/today');
  }
  return calendarTodayCache.gregorian;
};
import type { ApiClient } from './core/api';

export type CrmAppProps = ApiClient & { csrfToken: string };

type CrmVisitor = {
  id: string;
  visitor_code: string;
  full_name: string;
  phone: string | null;
  email: string | null;
  preferred_channel: string;
  visitor_type: string;
  status: string;
  captured_at?: string | null;
  capture_time_basis?: 'database_insert' | null;
  capture_evidence_status?: 'database_recorded' | 'historic_unclassified';
  available_transitions: string[];
  rating: string | null;
  interest: string | null;
  notes: string | null;
  person_id: string | null;
  assigned_to: string | null;
  origin_branch_id: string | null;
  origin_branch?: { id: string; name: string } | null;
  source?: { name: string } | null;
  campaign?: { name: string } | null;
};
type CrmTimelineItem = { kind: string; id: string; at: string | null; time_basis?: string | null; time_evidence_status?: 'authority_event_recorded' | 'database_recorded' | 'database_transition' | 'historic_unclassified'; direction?: string; type?: string; outcome?: string; summary?: string; scheduled_for?: string; title?: string; status?: string; assigned_to?: string; completed_by?: string | null; completed_at?: string | null };
type CrmTimelineResponse = { timeline: CrmTimelineItem[] };
type CrmCatalogItem = { id: string; key: string; name: string; category?: string | null; channel?: string | null; source_id?: string | null };
type CrmBranch = { id: string; name: string; lifecycle_state: string };
type CrmBranchesResponse = { branches: CrmBranch[]; allow_unassigned: boolean };

type CrmFormState = { full_name: string; phone: string; email: string; preferred_channel: string; visitor_type: string; origin_branch_id: string; source_id: string; campaign_id: string; interest: string; notes: string };
const emptyCrmForm: CrmFormState = { full_name: '', phone: '', email: '', preferred_channel: 'phone', visitor_type: 'walk_in', origin_branch_id: '', source_id: '', campaign_id: '', interest: '', notes: '' };
const interactionTypes = ['call', 'whatsapp', 'email', 'sms', 'visit', 'meeting', 'form_submission', 'document', 'note', 'other', 'payment', 'assessment', 'placement'];
const interactionOutcomes = ['no_answer', 'connected', 'positive', 'neutral', 'negative', 'unreachable', 'requested_info', 'scheduled_visit', 'followup_required', 'not_interested', 'qualified', 'converted', 'other'];

function humanize(key: string): string { return key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }

export function CrmApp({ getJson, postJson, csrfToken }: CrmAppProps) {
  const [visitors, setVisitors] = useState<CrmVisitor[]>([]);
  const [sources, setSources] = useState<CrmCatalogItem[]>([]);
  const [campaigns, setCampaigns] = useState<CrmCatalogItem[]>([]);
  const [branches, setBranches] = useState<CrmBranch[]>([]);
  const [branchesLoaded, setBranchesLoaded] = useState(false);
  const [selected, setSelected] = useState<CrmVisitor | null>(null);
  const [timeline, setTimeline] = useState<CrmTimelineItem[]>([]);
  const [form, setForm] = useState<CrmFormState>(emptyCrmForm);
  const [filter, setFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [interaction, setInteraction] = useState({ direction: 'outbound', type: 'call', outcome: 'connected', summary: '' });
  const [followup, setFollowup] = useState({ assigned_to: '', scheduled_for: '', title: '', notes: '' });
  const [personId, setPersonId] = useState('');
  const [transitionReason, setTransitionReason] = useState('');
  const requestKeys = useRef(new Map<string, string>());
  const requestKey = (slot: string): string => {
    const existing = requestKeys.current.get(slot);
    if (existing !== undefined) return existing;
    const created = `crm-${crypto.randomUUID()}`;
    requestKeys.current.set(slot, created);
    return created;
  };
  const clearRequestKey = (slot: string): void => { requestKeys.current.delete(slot); };

  const loadVisitors = () => {
    setLoading(true); setError(null);
    void getJson<{ visitors: CrmVisitor[] }>('/crm/visitors?limit=200')
      .then((response) => setVisitors(response.visitors))
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The CRM directory could not be loaded.'))
      .finally(() => setLoading(false));
  };
  useEffect(() => {
    loadVisitors();
    void getJson<{ data: CalendarToday }>('/calendar/today').then((response) => { calendarTodayCache = { gregorian: response.data.gregorian }; }).catch(() => { calendarTodayCache = CALENDAR_FALLBACK; });
    void getJson<{ sources: CrmCatalogItem[] }>('/crm/sources').then((response) => setSources(response.sources)).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'CRM sources could not be loaded.'));
    void getJson<{ campaigns: CrmCatalogItem[] }>('/crm/campaigns').then((response) => setCampaigns(response.campaigns)).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'CRM campaigns could not be loaded.'));
    void getJson<CrmBranchesResponse>('/crm/branches').then((response) => {
      setBranches(response.branches);
      setForm((current) => current.origin_branch_id === '' ? { ...current, origin_branch_id: response.branches[0]?.id ?? '' } : current);
    }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Authorized CRM branches could not be loaded.')).finally(() => setBranchesLoaded(true));
  }, []);

  const openVisitor = (visitor: CrmVisitor) => {
    setSelected(visitor); setPersonId(visitor.person_id ?? ''); setTransitionReason('');
    setFollowup((current) => ({ ...current, assigned_to: visitor.assigned_to ?? current.assigned_to }));
    setError(null);
    void getJson<CrmTimelineResponse>(`/crm/visitors/${encodeURIComponent(visitor.id)}/timeline`)
      .then((response) => setTimeline(response.timeline))
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The visitor timeline could not be loaded.'));
  };
  const capture = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setSaving(true); setError(null); setMessage(null);
    const body = { ...form, origin_branch_id: form.origin_branch_id || undefined, source_id: form.source_id || undefined, campaign_id: form.campaign_id || undefined };
    const slot = `capture-${JSON.stringify(body)}`;
    void postJson('/crm/visitors', body, requestKey(slot)).then(() => { clearRequestKey(slot); setForm({ ...emptyCrmForm, origin_branch_id: branches[0]?.id ?? '' }); setMessage('Visitor captured in the CRM source of truth.'); loadVisitors(); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The visitor could not be captured.')).finally(() => setSaving(false));
  };
  const linkPerson = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); if (selected === null || personId.trim() === '') return;
    setSaving(true); setError(null);
    const slot = `link-person-${selected.id}-${personId.trim()}`;
    void postJson(`/crm/visitors/${encodeURIComponent(selected.id)}/link-person`, { person_id: personId.trim() }, requestKey(slot)).then(() => { clearRequestKey(slot); setVisitors((current) => current.map((item) => item.id === selected.id ? { ...item, person_id: personId.trim() } : item)); setSelected((current) => current?.id === selected.id ? { ...current, person_id: personId.trim() } : current); setMessage('Visitor linked to the verified identity.'); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The visitor identity could not be linked.')).finally(() => setSaving(false));
  };
  const transition = (visitor: CrmVisitor, status: string) => {
    setSaving(true); setError(null);
    const slot = `transition-${visitor.id}-${status}-${transitionReason.trim()}`;
    void postJson(`/crm/visitors/${encodeURIComponent(visitor.id)}/transition`, { status, reason: transitionReason.trim() || undefined }, requestKey(slot)).then(() => getJson<{ visitor: CrmVisitor }>(`/crm/visitors/${encodeURIComponent(visitor.id)}`)).then((response) => { clearRequestKey(slot); setVisitors((current) => current.map((item) => item.id === visitor.id ? response.visitor : item)); setSelected((current) => current?.id === visitor.id ? response.visitor : current); setTransitionReason(''); setMessage(`Visitor moved to ${humanize(status)}.`); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The visitor stage could not be changed.')).finally(() => setSaving(false));
  };
  const createFollowup = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); if (selected === null) return;
    setSaving(true); setError(null);
    const slot = `followup-${selected.id}-${JSON.stringify(followup)}`;
    void postJson(`/crm/visitors/${encodeURIComponent(selected.id)}/followups`, followup, requestKey(slot)).then(() => getJson<CrmTimelineResponse>(`/crm/visitors/${encodeURIComponent(selected.id)}/timeline`)).then((response) => { clearRequestKey(slot); setTimeline(response.timeline); setFollowup({ assigned_to: selected.assigned_to ?? '', scheduled_for: '', title: '', notes: '' }); setMessage('Follow-up scheduled in the CRM source of truth.'); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The follow-up could not be scheduled.')).finally(() => setSaving(false));
  };
  const transitionFollowup = (followupId: string, status: 'complete' | 'cancel') => {
    if (selected === null) return;
    setSaving(true); setError(null);
    const slot = `followup-transition-${followupId}-${status}`;
    void postJson(`/crm/followups/${encodeURIComponent(followupId)}/${status}`, undefined, requestKey(slot)).then(() => getJson<CrmTimelineResponse>(`/crm/visitors/${encodeURIComponent(selected.id)}/timeline`)).then((response) => { clearRequestKey(slot); setTimeline(response.timeline); setMessage(`Follow-up ${status === 'complete' ? 'completed' : 'cancelled'}.`); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The follow-up could not be updated.')).finally(() => setSaving(false));
  };
  const recordInteraction = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); if (selected === null || interaction.summary.trim() === '') return;
    setSaving(true); setError(null);
    const interactionBody = { ...interaction, occurred_on: authoritativeToday() };
    const slot = `interaction-${selected.id}-${JSON.stringify(interactionBody)}`;
    void postJson(`/crm/visitors/${encodeURIComponent(selected.id)}/interactions`, interactionBody, requestKey(slot)).then(() => getJson<CrmTimelineResponse>(`/crm/visitors/${encodeURIComponent(selected.id)}/timeline`)).then((response) => { clearRequestKey(slot); setInteraction((current) => ({ ...current, summary: '' })); setTimeline(response.timeline); setMessage('Interaction appended to the immutable timeline.'); }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The interaction could not be recorded.')).finally(() => setSaving(false));
  };
  const visibleVisitors = useMemo(() => { const term = filter.trim().toLowerCase(); if (term === '') return visitors; return visitors.filter((visitor) => [visitor.full_name, visitor.visitor_code, visitor.email ?? '', visitor.phone ?? '', visitor.status].some((value) => value.toLowerCase().includes(term))); }, [filter, visitors]);

  return <>
    <AppShell current="crm" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace" aria-labelledby="crm-title">
      <header className="workspace-header"><div><p className="eyebrow">Canonical CRM workspace</p><h1 id="crm-title">Know the next best conversation.</h1><p className="lede">Visitors, provenance, follow-up, and immutable interaction evidence in one authorized view.</p></div><button className="button secondary" type="button" onClick={loadVisitors}>Refresh directory</button></header>
      {error && <div className="alert" role="alert">{error}</div>}{message && <div className="notice" role="status">{message}</div>}
      <section className="crm-layout" aria-label="CRM workspace">
        <div className="crm-main">
          <form className="panel crm-capture" onSubmit={capture}>
            <div className="section-heading"><div><p className="eyebrow">Capture</p><h2>New visitor</h2></div><span className="source-note">Writes go to CRM through the versioned API.</span></div>
            {branchesLoaded && branches.length === 0 && <p className="form-help" role="status">No authorized active branch is available. Capture is disabled because CRM records require canonical branch provenance.</p>}
            {branchesLoaded && branches.length > 0 && <p className="form-help" role="status">The form defaults to an authorized branch. Change it only when the lead originated elsewhere.</p>}
            <div className="form-grid">
              <label>Full name<input required maxLength={160} value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} /></label>
              <label>Phone<input maxLength={40} value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label>
              <label>Email<input type="email" maxLength={160} value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
              <label>Preferred channel<select value={form.preferred_channel} onChange={(event) => setForm({ ...form, preferred_channel: event.target.value })}><option value="phone">Phone</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="in_person">In person</option></select></label>
              <label>Visitor type<select value={form.visitor_type} onChange={(event) => setForm({ ...form, visitor_type: event.target.value })}><option value="walk_in">Walk in</option><option value="online">Online</option><option value="referral">Referral</option><option value="admissions_event">Admissions event</option><option value="social">Social</option></select></label>
              <label htmlFor="crm-origin-branch">Origin branch<select id="crm-origin-branch" required value={form.origin_branch_id} onChange={(event) => setForm({ ...form, origin_branch_id: event.target.value })} disabled={branchesLoaded && branches.length === 0}><option value="">Select a branch…</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
              <label>Source<select value={form.source_id} onChange={(event) => { const sourceId = event.target.value; const selectedCampaign = campaigns.find((campaign) => campaign.id === form.campaign_id); setForm({ ...form, source_id: sourceId, campaign_id: selectedCampaign?.source_id && selectedCampaign.source_id !== sourceId ? '' : form.campaign_id }); }}><option value="">No source</option>{sources.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}</select></label>
              <label>Campaign<select value={form.campaign_id} onChange={(event) => setForm({ ...form, campaign_id: event.target.value })}><option value="">No campaign</option>{campaigns.filter((campaign) => form.source_id === '' || campaign.source_id === null || campaign.source_id === form.source_id).map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.name}</option>)}</select></label>
              <label>Interest<input maxLength={255} value={form.interest} onChange={(event) => setForm({ ...form, interest: event.target.value })} /></label>
            </div>
            <label>Notes<textarea maxLength={2000} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} rows={2} /></label>
            <button className="button" type="submit" disabled={saving || (branchesLoaded && branches.length === 0)}> {saving ? 'Saving…' : 'Capture visitor'} </button>
          </form>
          <section className="panel" aria-labelledby="directory-title"><div className="section-heading"><div><p className="eyebrow">Authorized directory</p><h2 id="directory-title">Visitors and leads</h2></div><span className="source-note">{visibleVisitors.length} visible · branch scope enforced server-side</span></div><label className="directory-filter">Filter visible visitors<input value={filter} onChange={(event) => setFilter(event.target.value)} placeholder="Name, code, phone, email, status" /></label>{loading ? <p className="empty">Loading authorized visitors…</p> : visibleVisitors.length === 0 ? <p className="empty">No visitors match the current scope or filter.</p> : <ul className="visitor-list">{visibleVisitors.map((visitor) => <li key={visitor.id} className={selected?.id === visitor.id ? 'selected' : ''}><button type="button" className="visitor-row" onClick={() => openVisitor(visitor)}><span><strong>{visitor.full_name}</strong><small>{visitor.visitor_code} · {visitor.email ?? visitor.phone ?? 'No contact'}{visitor.origin_branch ? ` · ${visitor.origin_branch.name}` : visitor.origin_branch_id ? ` · branch ${visitor.origin_branch_id}` : ''}</small></span><span className={`status-chip ${visitor.status}`}>{humanize(visitor.status)}</span></button>{visitor.interest && <p className="visitor-interest">{visitor.interest}</p>}</li>)}</ul>}</section>
        </div>
        <aside className="panel crm-detail" aria-labelledby="detail-title">
          {selected === null ? <div className="empty"><p className="eyebrow">Evidence panel</p><h2 id="detail-title">Select a visitor</h2><p>Review authorized timeline evidence and record the next action without leaving the owning CRM context.</p></div> : <>
            <div className="section-heading"><div><p className="eyebrow">{selected.visitor_code}</p><h2 id="detail-title">{selected.full_name}</h2></div><span className={`status-chip ${selected.status}`}>{humanize(selected.status)}</span></div>
            <dl className="detail-facts"><div><dt>Contact</dt><dd>{selected.email ?? selected.phone ?? 'Not supplied'}</dd></div><div><dt>Provenance</dt><dd>{selected.origin_branch?.name ?? selected.origin_branch_id ?? 'Unavailable — provenance unknown'}</dd></div><div><dt>Identity</dt><dd>{selected.person_id ? `Verified person ${selected.person_id}` : 'Anonymous visitor'}</dd></div><div><dt>Acquisition</dt><dd>{selected.campaign?.name ?? selected.source?.name ?? 'Direct'}</dd></div><div><dt>Capture evidence</dt><dd>{selected.capture_evidence_status === 'database_recorded' ? `${selected.captured_at ?? 'Database-recorded UTC time unavailable'} · database recorded` : 'Historic capture time unclassified'}</dd></div></dl>
            <h3>Verified identity</h3><form onSubmit={linkPerson} className="identity-link"><label>Person id<input required value={personId} onChange={(event) => setPersonId(event.target.value)} placeholder="Verified person id" /></label><button className="button secondary" type="submit" disabled={saving || selected.person_id !== null}>{selected.person_id ? 'Identity linked' : 'Link identity'}</button></form>
            <div className="stage-actions"><label>Move stage<select value={selected.status} disabled={saving || selected.available_transitions.length === 0} onChange={(event) => transition(selected, event.target.value)}><option value={selected.status}>{humanize(selected.status)}</option>{selected.available_transitions.map((status) => <option key={status} value={status}>{humanize(status)}</option>)}</select></label><label>Transition reason<input value={transitionReason} onChange={(event) => setTransitionReason(event.target.value)} placeholder="Required when marking lost" /></label></div>
            <h3>Record interaction</h3><form onSubmit={recordInteraction}><div className="compact-grid"><label>Direction<select value={interaction.direction} onChange={(event) => setInteraction({ ...interaction, direction: event.target.value })}><option value="outbound">Outbound</option><option value="inbound">Inbound</option></select></label><label>Type<select value={interaction.type} onChange={(event) => setInteraction({ ...interaction, type: event.target.value })}>{interactionTypes.map((type) => <option key={type} value={type}>{humanize(type)}</option>)}</select></label><label>Outcome<select value={interaction.outcome} onChange={(event) => setInteraction({ ...interaction, outcome: event.target.value })}>{interactionOutcomes.map((outcome) => <option key={outcome} value={outcome}>{humanize(outcome)}</option>)}</select></label></div><label>Summary<textarea required maxLength={2000} value={interaction.summary} onChange={(event) => setInteraction({ ...interaction, summary: event.target.value })} rows={3} /></label><button className="button" type="submit" disabled={saving}>Append interaction</button></form>
            <h3>Schedule follow-up</h3><form onSubmit={createFollowup}><div className="compact-grid"><label>Assigned person<input required value={followup.assigned_to} onChange={(event) => setFollowup({ ...followup, assigned_to: event.target.value })} placeholder="Verified person id" /></label><label>Scheduled for<input required type="datetime-local" value={followup.scheduled_for} onChange={(event) => setFollowup({ ...followup, scheduled_for: event.target.value })} /></label></div><label>Title<input required maxLength={160} value={followup.title} onChange={(event) => setFollowup({ ...followup, title: event.target.value })} /></label><label>Notes<textarea maxLength={2000} value={followup.notes} onChange={(event) => setFollowup({ ...followup, notes: event.target.value })} rows={2} /></label><button className="button" type="submit" disabled={saving}>Schedule follow-up</button></form>
            <h3>Timeline</h3>{timeline.length === 0 ? <p className="empty">No interaction or follow-up evidence yet.</p> : <ol className="timeline">{timeline.map((item) => <li key={`${item.kind}-${item.id}`}><strong>{item.title ?? `${humanize(item.type ?? item.kind)}${item.outcome ? ` · ${humanize(item.outcome)}` : ''}`}</strong><small>{item.at ?? 'Time withheld — historic clock unclassified'} · {humanize(item.kind)}{item.status ? ` · ${humanize(item.status)}` : ''}</small><span>{item.summary ?? `Scheduled for ${item.scheduled_for ?? 'later'}${item.assigned_to ? ` · assigned to ${item.assigned_to}` : ''}`}</span>{item.kind === 'followup' && item.status === 'open' && <span className="timeline-actions"><button className="text-button" type="button" disabled={saving} onClick={() => transitionFollowup(item.id, 'complete')}>Complete</button><button className="text-button" type="button" disabled={saving} onClick={() => transitionFollowup(item.id, 'cancel')}>Cancel</button></span>}</li>)}</ol>}
          </>}
        </aside>
      </section>
    </main>
  </>;
}
