import { FormEvent, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { AppShell, PageStatus } from './ui';
import { createApiClient } from './core/api';
import type { ApiClient } from './core/api';
import './app.css';

type UnitActions = Partial<Record<string, boolean>>;

type OrganizationRow = {
  id: string;
  name: string;
  lifecycle_state: string;
  available_actions: UnitActions;
};

type CampusRow = OrganizationRow & { organization_id: string };
type BranchRow = OrganizationRow & { organization_id: string; campus_id: string };
type DepartmentRow = OrganizationRow & {
  scope_type: 'organization' | 'campus' | 'branch';
  scope_id: string;
  campus_id: string | null;
  branch_id: string | null;
  organization_id: string | null;
};

type RequestActions = { review: boolean; approve: boolean; reject: boolean; withdraw: boolean };

type ChangeRequest = {
  id: string;
  change_type: string;
  unit_type: string;
  description: string;
  lifecycle_state: string;
  proposed_by: string;
  reviewed_by: string | null;
  owner_one_id: string | null;
  owner_two_id: string | null;
  closed_by: string | null;
  closure_reason: string | null;
  updated_at?: string;
  available_actions: RequestActions;
};

type Person = { id: string; legal_name: string };
type Position = { id: string; name: string; organization_id: string };

type Workspace = {
  organizations: OrganizationRow[];
  campuses: CampusRow[];
  branches: BranchRow[];
  departments: DepartmentRow[];
  change_requests: ChangeRequest[];
  people: Person[];
  positions: Position[];
  available_actions: { create_organization: boolean };
  scope: { organization_ids: string[]; branch_ids: string[] };
};

type ChangeKind =
  | 'create_organization'
  | 'create_campus'
  | 'create_branch'
  | 'create_department'
  | 'rename_unit'
  | 'transition_unit'
  | 'transfer_branch';

type Tab = 'structure' | 'propose' | 'approvals' | 'positions';

const humanize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const today = () => new Date().toISOString().slice(0, 10);
const STATUS_TONE: Record<string, string> = {
  active: 'status-chip',
  draft: 'status-chip status-draft',
  suspended: 'status-chip status-suspended',
  closed: 'status-chip status-closed',
  reopened: 'status-chip status-reopened',
  proposed: 'status-chip status-draft',
  reviewed: 'status-chip status-suspended',
  executed: 'status-chip',
  rejected: 'status-chip status-closed',
  withdrawn: 'status-chip status-closed',
};

const CHANGE_LABELS: Record<ChangeKind, string> = {
  create_organization: 'Create organization',
  create_campus: 'Create campus',
  create_branch: 'Create branch',
  create_department: 'Create department',
  rename_unit: 'Rename unit',
  transition_unit: 'Change lifecycle state',
  transfer_branch: 'Transfer branch to another campus',
};

const LIFECYCLE_ACTION_LABELS: Record<string, string> = {
  activate: 'Activate',
  suspend: 'Suspend',
  close: 'Close (retire)',
  reopen: 'Reopen',
};

export function OrganizationApp({ getJson, postJson, csrfToken }: ApiClient & { csrfToken: string }) {
  const [data, setData] = useState<Workspace | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('structure');
  const [formKind, setFormKind] = useState<ChangeKind>('create_organization');

  // Proposal form drafts
  const [newOrganization, setNewOrganization] = useState({ name: '' });
  const [newCampus, setNewCampus] = useState({ organization_id: '', name: '' });
  const [newBranch, setNewBranch] = useState({ campus_id: '', name: '', effective_from: today() });
  const [newDepartment, setNewDepartment] = useState<{ scope_type: 'organization' | 'campus' | 'branch'; scope_id: string; name: string }>({ scope_type: 'organization', scope_id: '', name: '' });
  const [rename, setRename] = useState<{ unit_type: string; unit_id: string; new_name: string }>({ unit_type: 'organization', unit_id: '', new_name: '' });
  const [transition, setTransition] = useState<{ unit_type: string; unit_id: string; action: string }>({ unit_type: 'organization', unit_id: '', action: '' });
  const [transfer, setTransfer] = useState({ branch_id: '', campus_id: '', effective_from: today() });

  const load = () => {
    setLoading(true);
    setError(null);
    void getJson<Workspace>('/organization/workspace')
      .then(setData)
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Organization structure could not be loaded.'))
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const command = (request: Promise<unknown>, success: string) => {
    setBusy(true);
    setError(null);
    setMessage(null);
    void request
      .then(() => { setMessage(success); load(); })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'The organization authority rejected this action.'))
      .finally(() => setBusy(false));
  };

  const submit = (event: FormEvent<HTMLFormElement>, path: string, body: Record<string, unknown>, success: string) => {
    event.preventDefault();
    command(postJson(path, body), success);
  };

  const peopleById = useMemo(() => Object.fromEntries((data?.people ?? []).map((person) => [person.id, person.legal_name])), [data]);
  const organizationsById = useMemo(() => Object.fromEntries((data?.organizations ?? []).map((item) => [item.id, item])), [data]);
  const campusesById = useMemo(() => Object.fromEntries((data?.campuses ?? []).map((item) => [item.id, item])), [data]);

  /** Opens the proposal tab with a form pre-selected and, when given, pre-filled. */
  const openProposal = (kind: ChangeKind, preset?: Record<string, string>) => {
    setFormKind(kind);
    if (preset) {
      if (kind === 'create_campus') setNewCampus({ organization_id: preset.organization_id ?? '', name: '' });
      if (kind === 'create_branch') setNewBranch({ campus_id: preset.campus_id ?? '', name: '', effective_from: today() });
      if (kind === 'create_department') {
        const scopeType = (preset.scope_type as 'organization' | 'campus' | 'branch') ?? 'organization';
        setNewDepartment({ scope_type: scopeType, scope_id: preset.scope_id ?? '', name: '' });
      }
      if (kind === 'rename_unit') setRename({ unit_type: preset.unit_type ?? 'organization', unit_id: preset.unit_id ?? '', new_name: '' });
      if (kind === 'transition_unit') setTransition({ unit_type: preset.unit_type ?? 'organization', unit_id: preset.unit_id ?? '', action: preset.action ?? '' });
      if (kind === 'transfer_branch') setTransfer({ branch_id: preset.branch_id ?? '', campus_id: '', effective_from: today() });
    }
    setTab('propose');
  };

  const actorName = (id: string | null | undefined): string => {
    if (!id) return 'Pending';
    return peopleById[id] ?? 'Another authorized operator';
  };

  if (loading) return <><AppShell current="organization" csrfToken={csrfToken} /><PageStatus>Loading authorized organization topology…</PageStatus></>;
  if (!data) return <><AppShell current="organization" csrfToken={csrfToken} /><main id="workspace-main" className="workspace"><div className="alert" role="alert">{error ?? 'Organization structure is unavailable.'}</div></main></>;

  const openRequests = data.change_requests.filter((item) => item.lifecycle_state === 'proposed' || item.lifecycle_state === 'reviewed');
  const historyRequests = data.change_requests.filter((item) => item.lifecycle_state !== 'proposed' && item.lifecycle_state !== 'reviewed');

  const unitsForType = (unitType: string): Array<{ id: string; name: string }> => {
    if (unitType === 'organization') return data.organizations;
    if (unitType === 'campus') return data.campuses;
    if (unitType === 'branch') return data.branches;
    return data.departments;
  };

  const departmentScopeOptions = () => {
    if (newDepartment.scope_type === 'organization') {
      return data.organizations.map((item) => ({ value: item.id, label: item.name }));
    }
    if (newDepartment.scope_type === 'campus') {
      return data.campuses.map((item) => ({ value: item.id, label: `${item.name} · ${organizationsById[item.organization_id]?.name ?? ''}` }));
    }
    return data.branches.map((item) => ({ value: item.id, label: `${item.name} · ${campusesById[item.campus_id]?.name ?? ''}` }));
  };

  /** Small button that stages a proposal for a single-unit action. */
  function ProposeAction({ label, kind, preset, tone = false }: { label: string; kind: ChangeKind; preset?: Record<string, string>; tone?: boolean }) {
    return <button type="button" className={tone ? 'text-button danger-text' : 'text-button'} disabled={busy} onClick={() => openProposal(kind, preset)}>{label}</button>;
  }

  function UnitLifecycleActions({ unitType, unit }: { unitType: string; unit: OrganizationRow }) {
    const entries = (['activate', 'suspend', 'close', 'reopen'] as const).filter((action) => unit.available_actions[action]);
    if (entries.length === 0) return null;
    return <span className="inline-actions">
      {entries.map((action) => (
        <ProposeAction
          key={action}
          label={LIFECYCLE_ACTION_LABELS[action]}
          kind="transition_unit"
          preset={{ unit_type: unitType, unit_id: unit.id, action }}
          tone={action === 'close'}
        />
      ))}
    </span>;
  }

  const statusChip = (state: string) => <span className={STATUS_TONE[state] ?? 'status-chip'}>{humanize(state)}</span>;

  const campusLabel = (campus: CampusRow) => `${campus.name} · ${organizationsById[campus.organization_id]?.name ?? 'Unknown organization'}`;

  const branchDestinations = (branchId: string) => {
    const branch = data.branches.find((item) => item.id === branchId);
    return data.campuses.filter((campus) => campus.lifecycle_state === 'active' && campus.id !== branch?.campus_id);
  };

  const rejectRequest = (request: ChangeRequest) => {
    const reason = window.prompt('A rejection requires a recorded reason (at least 3 characters). Why is this change rejected?');
    if (reason === null) return;
    if (reason.trim().length < 3) { setError('The rejection reason must be at least 3 characters.'); return; }
    command(postJson(`/organization/changes/${encodeURIComponent(request.id)}/reject`, { reason: reason.trim() }), 'Change rejected and recorded.');
  };

  const approveRequest = (request: ChangeRequest) => {
    const isSecondSignature = request.owner_one_id !== null;
    if (isSecondSignature && !window.confirm('This is the second owner signature: the canonical topology command will execute immediately after approval.\n\nThe server re-validates every rule under lock. Continue?')) return;
    command(postJson(`/organization/changes/${encodeURIComponent(request.id)}/approve`), isSecondSignature ? 'Approval signatures complete; the change was executed.' : 'First owner signature recorded; one more distinct owner is required.');
  };

  const renderRequestRow = (request: ChangeRequest) => <tr key={request.id}>
    <td>
      <strong>{request.description}</strong>
      <span className="row-meta">{humanize(request.change_type)} · {humanize(request.unit_type)}</span>
    </td>
    <td>{statusChip(request.lifecycle_state)}</td>
    <td>{actorName(request.proposed_by)}</td>
    <td>{actorName(request.reviewed_by)}</td>
    <td>{actorName(request.owner_one_id)}<br />{actorName(request.owner_two_id)}</td>
    <td>
      {request.available_actions.withdraw && <button type="button" className="text-button" disabled={busy} onClick={() => command(postJson(`/organization/changes/${encodeURIComponent(request.id)}/withdraw`), 'Proposal withdrawn.')}>Withdraw</button>}
      {request.available_actions.review && <button type="button" className="text-button" disabled={busy} onClick={() => command(postJson(`/organization/changes/${encodeURIComponent(request.id)}/review`), 'Independent review recorded.')}>Mark reviewed</button>}
      {request.available_actions.approve && <button type="button" className="text-button" disabled={busy} onClick={() => approveRequest(request)}>{request.owner_one_id === null ? 'Sign as owner 1' : 'Sign as owner 2 & execute'}</button>}
      {request.available_actions.reject && <button type="button" className="text-button danger-text" disabled={busy} onClick={() => rejectRequest(request)}>Reject</button>}
      {request.closure_reason && <span className="row-meta">Reason: {request.closure_reason}</span>}
      {!request.available_actions.review && !request.available_actions.approve && !request.available_actions.withdraw && !request.closure_reason && <span className="muted">—</span>}
    </td>
  </tr>;

  return <><AppShell current="organization" csrfToken={csrfToken} />
    <main id="workspace-main" className="workspace" aria-labelledby="organization-title">
      <header className="workspace-header">
        <div>
          <p className="eyebrow">Organization topology governance</p>
          <h1 id="organization-title">Structure changes are proposed, reviewed and signed.</h1>
          <p className="lede">Organizations, campuses, branches and departments are managed through a four-actor separation-of-duties chain: an initiator proposes, a distinct reviewer reviews, and two distinct owners sign — the second signature executes the canonical domain command. No structure row is ever deleted.</p>
        </div>
        <button className="button secondary" type="button" onClick={load} disabled={busy}>Refresh topology</button>
      </header>

      {error && <div className="alert" role="alert">{error}</div>}
      {message && <div className="notice" role="status">{message}</div>}

      <div className="reporting-boundary">
        <strong>Effective governance scope</strong>
        <span>{data.scope.organization_ids.length} organization(s) · {data.scope.branch_ids.length} branch(es). Only actions the server authorizes for your identity are offered; the browser never grants authority.</span>
      </div>

      <section className="summary-grid">
        <div className="panel"><span className="metric">{data.organizations.length}</span><span className="metric-label">Organizations</span></div>
        <div className="panel"><span className="metric">{data.campuses.length}</span><span className="metric-label">Campuses</span></div>
        <div className="panel"><span className="metric">{data.branches.length}</span><span className="metric-label">Branches</span></div>
        <div className="panel"><span className="metric">{openRequests.length}</span><span className="metric-label">Open change proposals</span></div>
      </section>

      <div className="student-tabs" role="tablist" aria-label="Organization governance areas">
        <button role="tab" aria-selected={tab === 'structure'} aria-controls="org-structure" className={tab === 'structure' ? 'active' : ''} type="button" onClick={() => setTab('structure')}>Topology</button>
        <button role="tab" aria-selected={tab === 'propose'} aria-controls="org-propose" className={tab === 'propose' ? 'active' : ''} type="button" onClick={() => setTab('propose')}>Propose a change</button>
        <button role="tab" aria-selected={tab === 'approvals'} aria-controls="org-approvals" className={tab === 'approvals' ? 'active' : ''} type="button" onClick={() => setTab('approvals')}>Governance queue{openRequests.length > 0 ? ` (${openRequests.length})` : ''}</button>
        <button role="tab" aria-selected={tab === 'positions'} aria-controls="org-positions" className={tab === 'positions' ? 'active' : ''} type="button" onClick={() => setTab('positions')}>Position catalog</button>
      </div>

      {tab === 'structure' && <section id="org-structure" role="tabpanel" className="panel" tabIndex={0} aria-labelledby="org-structure-heading">
        <div className="section-heading">
          <div><p className="eyebrow">Canonical structure</p><h2 id="org-structure-heading">Organization hierarchy</h2></div>
          <span className="source-note">Branch campus provenance comes from the open campus attribution, never from an editable column.</span>
        </div>
        {data.organizations.length === 0
          ? <p className="empty">No organization is visible in your governance scope. A platform-level initiator can propose the first organization.</p>
          : <div className="fact-list">
            {data.organizations.map((organization) => {
              const orgCampuses = data.campuses.filter((campus) => campus.organization_id === organization.id);
              return <div key={organization.id} className="panel topology-unit">
                <div className="section-heading">
                  <div><p className="eyebrow">Organization</p><h3>{organization.name}</h3></div>
                  <div className="heading-actions">{statusChip(organization.lifecycle_state)}<UnitLifecycleActions unitType="organization" unit={organization} />
                    {organization.available_actions.rename && <ProposeAction label="Rename" kind="rename_unit" preset={{ unit_type: 'organization', unit_id: organization.id }} />}
                    {organization.available_actions.create_campus && <ProposeAction label="Add campus" kind="create_campus" preset={{ organization_id: organization.id }} />}
                    {organization.available_actions.create_department && <ProposeAction label="Add department" kind="create_department" preset={{ scope_type: 'organization', scope_id: organization.id }} />}
                  </div>
                </div>
                {data.departments.filter((department) => department.scope_type === 'organization' && department.scope_id === organization.id).map((department) => (
                  <DepartmentRowView key={department.id} department={department} />
                ))}
                {orgCampuses.length === 0
                  ? <p className="empty">No campus is recorded in this organization.</p>
                  : <ul className="fact-list topology-children">
                    {orgCampuses.map((campus) => {
                      const campusBranches = data.branches.filter((branch) => branch.campus_id === campus.id);
                      return <li key={campus.id} className="panel topology-unit nested">
                        <div className="section-heading">
                          <div><p className="eyebrow">Campus</p><h4>{campus.name}</h4></div>
                          <div className="heading-actions">{statusChip(campus.lifecycle_state)}<UnitLifecycleActions unitType="campus" unit={campus} />
                            {campus.available_actions.rename && <ProposeAction label="Rename" kind="rename_unit" preset={{ unit_type: 'campus', unit_id: campus.id }} />}
                            {campus.available_actions.create_branch && <ProposeAction label="Add branch" kind="create_branch" preset={{ campus_id: campus.id }} />}
                            {campus.available_actions.create_department && <ProposeAction label="Add department" kind="create_department" preset={{ scope_type: 'campus', scope_id: campus.id }} />}
                          </div>
                        </div>
                        {data.departments.filter((department) => department.scope_type === 'campus' && department.scope_id === campus.id).map((department) => (
                          <DepartmentRowView key={department.id} department={department} />
                        ))}
                        {campusBranches.length === 0
                          ? <p className="empty">No branch is attributed to this campus.</p>
                          : <ul className="fact-list topology-children">
                            {campusBranches.map((branch) => (
                              <li key={branch.id} className="panel topology-unit nested">
                                <div className="section-heading">
                                  <div><p className="eyebrow">Branch</p><h4>{branch.name}</h4></div>
                                  <div className="heading-actions">{statusChip(branch.lifecycle_state)}<UnitLifecycleActions unitType="branch" unit={branch} />
                                    {branch.available_actions.rename && <ProposeAction label="Rename" kind="rename_unit" preset={{ unit_type: 'branch', unit_id: branch.id }} />}
                                    {branch.available_actions.transfer && <ProposeAction label="Transfer campus" kind="transfer_branch" preset={{ branch_id: branch.id }} />}
                                    {branch.available_actions.create_department && <ProposeAction label="Add department" kind="create_department" preset={{ scope_type: 'branch', scope_id: branch.id }} />}
                                  </div>
                                </div>
                                {data.departments.filter((department) => department.scope_type === 'branch' && department.scope_id === branch.id).length === 0
                                  ? <p className="empty">No department in this branch.</p>
                                  : <ul className="fact-list topology-children">
                                    {data.departments.filter((department) => department.scope_type === 'branch' && department.scope_id === branch.id).map((department) => (
                                      <li key={department.id}><DepartmentRowView department={department} /></li>
                                    ))}
                                  </ul>}
                              </li>
                            ))}
                          </ul>}
                      </li>;
                    })}
                  </ul>}
              </div>;
            })}
          </div>}
      </section>}

      {tab === 'propose' && <section id="org-propose" role="tabpanel" tabIndex={0}>
        <div className="section-heading">
          <div><p className="eyebrow">Stage a governed change</p><h2>Propose a topology change</h2></div>
          <span className="source-note">Proposing requires the initiate capability on the governing scope. It records a proposal — nothing changes until review and two owner signatures.</span>
        </div>
        <label className="directory-filter">Change type
          <select value={formKind} onChange={(event) => setFormKind(event.target.value as ChangeKind)}>
            {(Object.keys(CHANGE_LABELS) as ChangeKind[]).map((kind) => <option key={kind} value={kind}>{CHANGE_LABELS[kind]}</option>)}
          </select>
        </label>

        {formKind === 'create_organization' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'create_organization', ...newOrganization }, 'Organization creation proposed.')}>
          <p className="form-note">A new organization starts as a <strong>draft</strong> and must be activated through the same governance chain before it can hold campuses.</p>
          <div className="compact-grid"><label>Organization name<input required minLength={2} maxLength={255} value={newOrganization.name} onChange={(event) => setNewOrganization({ name: event.target.value })} /></label></div>
          <button className="button" type="submit" disabled={busy || !data.available_actions.create_organization}>{data.available_actions.create_organization ? 'Propose organization creation' : 'You do not hold the platform-level initiate capability'}</button>
        </form>}

        {formKind === 'create_campus' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'create_campus', ...newCampus }, 'Campus creation proposed.')}>
          <div className="compact-grid">
            <label>Parent organization<select required value={newCampus.organization_id} onChange={(event) => setNewCampus({ ...newCampus, organization_id: event.target.value })}><option value="">Select organization…</option>{data.organizations.filter((item) => item.lifecycle_state === 'active').map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
            <label>Campus name<input required minLength={2} maxLength={255} value={newCampus.name} onChange={(event) => setNewCampus({ ...newCampus, name: event.target.value })} /></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose campus creation</button>
        </form>}

        {formKind === 'create_branch' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'create_branch', ...newBranch }, 'Branch creation proposed.')}>
          <div className="compact-grid">
            <label>Attributed campus<select required value={newBranch.campus_id} onChange={(event) => setNewBranch({ ...newBranch, campus_id: event.target.value })}><option value="">Select campus…</option>{data.campuses.filter((item) => item.lifecycle_state === 'active').map((item) => <option key={item.id} value={item.id}>{campusLabel(item)}</option>)}</select></label>
            <label>Branch name<input required minLength={2} maxLength={255} value={newBranch.name} onChange={(event) => setNewBranch({ ...newBranch, name: event.target.value })} /></label>
            <label>Attribution effective from<input required type="date" value={newBranch.effective_from} onChange={(event) => setNewBranch({ ...newBranch, effective_from: event.target.value })} /></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose branch creation</button>
        </form>}

        {formKind === 'create_department' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'create_department', ...newDepartment }, 'Department creation proposed.')}>
          <div className="compact-grid">
            <label>Department belongs to<select required value={newDepartment.scope_type} onChange={(event) => setNewDepartment({ scope_type: event.target.value as 'organization' | 'campus' | 'branch', scope_id: '', name: newDepartment.name })}><option value="organization">An organization</option><option value="campus">A campus</option><option value="branch">A branch</option></select></label>
            <label>{humanize(newDepartment.scope_type)}<select required value={newDepartment.scope_id} onChange={(event) => setNewDepartment({ ...newDepartment, scope_id: event.target.value })}><option value="">Select {newDepartment.scope_type}…</option>{departmentScopeOptions().map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <label>Department name<input required minLength={2} maxLength={255} value={newDepartment.name} onChange={(event) => setNewDepartment({ ...newDepartment, name: event.target.value })} /></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose department creation</button>
        </form>}

        {formKind === 'rename_unit' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'rename_unit', ...rename }, 'Rename proposed.')}>
          <div className="compact-grid">
            <label>Unit type<select required value={rename.unit_type} onChange={(event) => setRename({ ...rename, unit_type: event.target.value, unit_id: '' })}><option value="organization">Organization</option><option value="campus">Campus</option><option value="branch">Branch</option><option value="department">Department</option></select></label>
            <label>Unit<select required value={rename.unit_id} onChange={(event) => setRename({ ...rename, unit_id: event.target.value })}><option value="">Select unit…</option>{unitsForType(rename.unit_type).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
            <label>New name<input required minLength={2} maxLength={255} value={rename.new_name} onChange={(event) => setRename({ ...rename, new_name: event.target.value })} /></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose rename</button>
        </form>}

        {formKind === 'transition_unit' && <form className="panel proposal-form" onSubmit={(event) => {
          submit(event, '/organization/changes', { change_type: 'transition_unit', ...transition }, `Proposal to ${transition.action} the unit was recorded.`);
        }}>
          <p className="form-note">Suspension is a reversible, fail-closed freeze. <strong>Closing</strong> is terminal: it is allowed only bottom-up and is refused while active structure, students, teachers or employment remain in scope.</p>
          <div className="compact-grid">
            <label>Unit type<select required value={transition.unit_type} onChange={(event) => setTransition({ ...transition, unit_type: event.target.value, unit_id: '', action: '' })}><option value="organization">Organization</option><option value="campus">Campus</option><option value="branch">Branch</option><option value="department">Department</option></select></label>
            <label>Unit<select required value={transition.unit_id} onChange={(event) => setTransition({ ...transition, unit_id: event.target.value, action: '' })}><option value="">Select unit…</option>{unitsForType(transition.unit_type).map((item) => <option key={item.id} value={item.id}>{item.name} · {humanize((item as OrganizationRow).lifecycle_state)}</option>)}</select></label>
            <label>Lifecycle action<select required value={transition.action} onChange={(event) => setTransition({ ...transition, action: event.target.value })}><option value="">Select action…</option>{(['activate', 'suspend', 'close', 'reopen'] as const).filter((action) => {
              const unit = unitsForType(transition.unit_type).find((item) => item.id === transition.unit_id) as OrganizationRow | undefined;
              return unit?.available_actions[action];
            }).map((action) => <option key={action} value={action}>{LIFECYCLE_ACTION_LABELS[action]}</option>)}</select></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose lifecycle change</button>
        </form>}

        {formKind === 'transfer_branch' && <form className="panel proposal-form" onSubmit={(event) => submit(event, '/organization/changes', { change_type: 'transfer_branch', ...transfer }, 'Branch transfer proposed.')}>
          <p className="form-note">A transfer preserves the full attribution history: the current campus attribution is closed on the effective date and a new one opens. Cross-organization transfers require authority on both structures.</p>
          <div className="compact-grid">
            <label>Branch<select required value={transfer.branch_id} onChange={(event) => setTransfer({ ...transfer, branch_id: event.target.value, campus_id: '' })}><option value="">Select branch…</option>{data.branches.filter((item) => item.available_actions.transfer).map((item) => <option key={item.id} value={item.id}>{`${item.name} · ${campusesById[item.campus_id]?.name ?? 'unattributed'}`}</option>)}</select></label>
            <label>Destination campus<select required value={transfer.campus_id} onChange={(event) => setTransfer({ ...transfer, campus_id: event.target.value })}><option value="">Select destination campus…</option>{branchDestinations(transfer.branch_id).map((item) => <option key={item.id} value={item.id}>{campusLabel(item)}</option>)}</select></label>
            <label>Effective from<input required type="date" value={transfer.effective_from} onChange={(event) => setTransfer({ ...transfer, effective_from: event.target.value })} /></label>
          </div>
          <button className="button" type="submit" disabled={busy}>Propose branch transfer</button>
        </form>}
      </section>}

      {tab === 'approvals' && <section id="org-approvals" role="tabpanel" tabIndex={0}>
        <section className="panel">
          <div className="section-heading">
            <div><p className="eyebrow">Governance chain</p><h2>Open topology change proposals</h2></div>
            <span className="source-note">Each signature must come from a distinct person; the second owner signature executes the canonical command in one transaction.</span>
          </div>
          {openRequests.length === 0 ? <p className="empty">No proposal is awaiting a signature in your scope.</p> : <div className="table-wrap"><table>
            <thead><tr><th>Proposed change</th><th>State</th><th>Initiator</th><th>Reviewer</th><th>Owners</th><th>Your action</th></tr></thead>
            <tbody>{openRequests.map(renderRequestRow)}</tbody>
          </table></div>}
        </section>
        <section className="panel">
          <div className="section-heading">
            <div><p className="eyebrow">Audit history</p><h2>Executed, rejected and withdrawn changes</h2></div>
            <span className="source-note">Requests are append-only evidence; they are never deleted or rewritten.</span>
          </div>
          {historyRequests.length === 0 ? <p className="empty">No completed proposal is visible.</p> : <div className="table-wrap"><table>
            <thead><tr><th>Change</th><th>State</th><th>Initiator</th><th>Reviewer</th><th>Owners / closer</th><th>Detail</th></tr></thead>
            <tbody>{historyRequests.map((request) => <tr key={request.id}>
              <td><strong>{request.description}</strong><span className="row-meta">{humanize(request.change_type)}</span></td>
              <td>{statusChip(request.lifecycle_state)}</td>
              <td>{actorName(request.proposed_by)}</td>
              <td>{actorName(request.reviewed_by)}</td>
              <td>{actorName(request.owner_one_id)}<br />{actorName(request.owner_two_id)}{request.closed_by && <><br /><span className="muted">Closed by {actorName(request.closed_by)}</span></>}</td>
              <td>{request.closure_reason ? <span className="row-meta">{request.closure_reason}</span> : <span className="muted">Executed by the domain authority</span>}</td>
            </tr>)}</tbody>
          </table></div>}
        </section>
      </section>}

      {tab === 'positions' && <section id="org-positions" role="tabpanel" className="panel" tabIndex={0}>
        <div className="section-heading">
          <div><p className="eyebrow">Position catalog</p><h2>Positions within authorized organizations</h2></div>
          <span className="source-note">Position definition is distinct from assignment and permission; positions are maintained in Access Governance.</span>
        </div>
        {data.positions.length === 0 ? <p className="empty">No position is visible.</p> : <div className="table-wrap"><table>
          <thead><tr><th>Position</th><th>Organization</th></tr></thead>
          <tbody>{data.positions.map((position) => <tr key={position.id}><td><strong>{position.name}</strong></td><td>{organizationsById[position.organization_id]?.name ?? position.organization_id}</td></tr>)}</tbody>
        </table></div>}
      </section>}
    </main>
  </>;

  function DepartmentRowView({ department }: { department: DepartmentRow }) {
    return <div className="topology-unit department-row">
      <div className="heading-actions">
        <span className="unit-heading"><span className="eyebrow">Department · {humanize(department.scope_type)}-scoped</span> <strong>{department.name}</strong></span>
        {statusChip(department.lifecycle_state)}
        <UnitLifecycleActions unitType="department" unit={department} />
        {department.available_actions.rename && <ProposeAction label="Rename" kind="rename_unit" preset={{ unit_type: 'department', unit_id: department.id }} />}
      </div>
    </div>;
  }
}

const root = document.getElementById('organization-console');
if (root) {
  const csrfToken = root.getAttribute('data-csrf-token') ?? '';
  const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken });
  createRoot(root).render(<OrganizationApp {...api} csrfToken={csrfToken} />);
}
