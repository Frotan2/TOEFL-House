import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { JSDOM } from 'jsdom';
import { build } from 'esbuild';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const literalPattern = (value) => new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

test('Privacy & Consent is mounted by the canonical React console', () => {
  const app = read('resources/js/app.tsx');
  const privacy = read('resources/js/privacy.tsx');

  assert.match(app, /import \{ PrivacyApp \} from ['"]\.\/privacy['"]/);
  assert.match(app, /case ['"]privacy['"]:/);
  assert.match(privacy, /AppShell current="privacy"/);
  assert.match(privacy, /\/privacy\/workspace/);
  assert.match(privacy, /\/privacy\/subjects\//);
  assert.match(privacy, /available_actions/);
  // The component renders the server's state-legal matrix verbatim: it must
  // consume every consent verb and the staged export chain flags, and never
  // re-derive lifecycle legality from a row's lifecycle_state.
  for (const verb of ['submit', 'verify', 'activate', 'expire', 'revoke', 'archive']) {
    assert.match(privacy, literalPattern(`available_actions.${verb}`), `consent verb ${verb} must come from the server projection`);
  }
  assert.match(privacy, /available_actions\.approve/);
  assert.match(privacy, /available_actions\.execute/);
  assert.doesNotMatch(privacy, /available_actions\.\w+ && \w+\.lifecycle_state/);
  assert.doesNotMatch(privacy, /lifecycle_state === ['"](draft|submitted|verified|active|expired|revoked|archived)['"]/);
  assert.doesNotMatch(privacy, /\['draft', 'submitted'\]\.includes\(/);
  assert.doesNotMatch(privacy, /from ['"]react-dom\/client['"]/);
  assert.doesNotMatch(privacy, /\bcreateRoot\s*\(/);
  assert.doesNotMatch(privacy, /\bfetch\s*\(/);
  assert.match(privacy, /role="tablist"/);
  assert.match(privacy, /role="tabpanel"/);
  assert.match(privacy, /aria-live="polite"/);
});

test('Privacy API routes expose the canonical lifecycle, disclosure and export contract', () => {
  const routes = read('routes/privacy-api.php');

  for (const token of [
    "Route::prefix('privacy')->name('api.privacy.')",
    "Route::get('/workspace', [PrivacyApiController::class, 'workspace'])",
    "Route::get('/subjects/{subjectId}', [PrivacyApiController::class, 'subject'])",
    "Route::post('/purposes', [PrivacyApiController::class, 'definePurpose'])",
    "Route::post('/consents', [PrivacyApiController::class, 'recordConsent'])",
    "Route::post('/consents/{consentId}/submit', [PrivacyApiController::class, 'submitConsent'])",
    "Route::post('/consents/{consentId}/verify', [PrivacyApiController::class, 'verifyConsent'])",
    "Route::post('/consents/{consentId}/activate', [PrivacyApiController::class, 'activateConsent'])",
    "Route::post('/consents/{consentId}/expire', [PrivacyApiController::class, 'expireConsent'])",
    "Route::post('/consents/{consentId}/revoke', [PrivacyApiController::class, 'revokeConsent'])",
    "Route::post('/consents/{consentId}/archive', [PrivacyApiController::class, 'archiveConsent'])",
    "Route::post('/disclosures', [PrivacyApiController::class, 'recordDisclosure'])",
    "Route::post('/exports', [PrivacyApiController::class, 'exportSubject'])",
    "Route::post('/exports/bulk', [PrivacyApiController::class, 'requestExport'])",
    "Route::post('/exports/{requestId}/approve', [PrivacyApiController::class, 'approveExport'])",
    "Route::post('/exports/{requestId}/execute', [PrivacyApiController::class, 'executeExport'])",
  ]) assert.match(routes, literalPattern(token));
});

test('Privacy read projection is server-scoped, minimal, and command-owned', () => {
  const bootstrap = read('bootstrap/app.php');
  const controller = read('app/Http/Controllers/Api/PrivacyApiController.php');
  const query = read('app/Modules/Privacy/Queries/SubjectPrivacyQuery.php');
  const webRoutes = read('routes/web.php');

  assert.match(bootstrap, /routes\/privacy-api\.php/);
  for (const capability of [
    'RecordConsent::CAPABILITY',
    'RecordDisclosure::CAPABILITY',
    'ExportSubjectData::CAPABILITY',
    'ExportSubjectData::CAPABILITY_BULK_APPROVE',
    'DefineConsentPurpose::CAPABILITY',
  ]) assert.match(controller, literalPattern(capability));
  assert.match(controller, /SubjectPrivacyQuery::class/);
  assert.match(controller, /PersonBranchScope::resolve/);
  assert.match(controller, /consentAffordances/);
  assert.match(controller, /ConsentLifecycle::allowsTransition/);
  assert.match(controller, /ExportApprovalChain::acceptsSignature/);
  assert.match(controller, /ExportApprovalChain::allowsExecution/);
  // Consent evidence locators are write-only: accepted by the recording
  // command as validated input, never projected by any read model.
  const evidenceMentions = controller.match(/'evidence_ref' =>/g) ?? [];
  const evidenceValidations = controller.match(/'evidence_ref' => \[/g) ?? [];
  assert.equal(evidenceMentions.length, evidenceValidations.length, 'every controller evidence_ref mention must be an input rule, never a projection');
  assert.equal(evidenceValidations.length, 1, 'exactly one command accepts a consent evidence locator');
  assert.doesNotMatch(query, /'evidence_ref'/);
  // The browser never supplies a tenant: an organization-wide request resolves
  // the organization from the subject's own provenance.
  assert.match(controller, /PersonBranchScope::resolve\(\$input\['subject_person_id'\]\)->organizationId/);
  assert.match(webRoutes, /Route::view\('\/', 'workspace', \['view' => 'privacy'\]\)->name\('index'\)/);
  assert.ok(!fs.existsSync(path.join(root, 'resources/views/privacy/index.blade.php')), 'Privacy must not retain a second Blade read model.');
  assert.ok(!fs.existsSync(path.join(root, 'resources/views/privacy')), 'Privacy must not retain a Blade view directory.');
});

// ---------------------------------------------------------------------------
// Behavioural contract: the real PrivacyApp component rendered in a real DOM
// against a scripted API client. Static greps cannot prove which actions the
// UI offers per lifecycle state, what payloads it sends, or how it surfaces
// server rejections; these tests render the component and observe.
// ---------------------------------------------------------------------------

const PURPOSES = [
  { id: 'p-updates', name: 'enrollment-updates', channel: 'email', category: 'communication' },
  { id: 'p-reminders', name: 'exam-reminders', channel: 'sms', category: 'communication' },
  { id: 'p-newsletter', name: 'alumni-newsletter', channel: 'email', category: 'marketing' },
  { id: 'p-fees', name: 'fee-notices', channel: 'email', category: 'operations' },
  { id: 'p-referrals', name: 'placement-referrals', channel: 'phone', category: 'operations' },
  { id: 'p-statutory', name: 'statutory-reporting', channel: 'post', category: 'statutory' },
  { id: 'p-attendance', name: 'attendance-alerts', channel: 'sms', category: 'communication' },
  { id: 'p-survey', name: 'research-survey', channel: 'email', category: 'marketing' },
];

function workspaceFixture() {
  // `evidence_ref` is deliberately present here even though the server never
  // projects it: the rendered surface must not display a consent evidence
  // locator even if a read model regressed and leaked one.
  const consent = (id, purposeId, state, actions, from = '2026-01-05', to = null) => ({
    id,
    subject_person_id: 'person-1',
    purpose_id: purposeId,
    lifecycle_state: state,
    effective_from: from,
    effective_to: to,
    evidence_ref: 'evidence/signed-form-2026',
    available_actions: { submit: false, verify: false, activate: false, expire: false, revoke: false, archive: false, ...actions },
    created_at: '2026-09-01T08:00:00.000Z',
    updated_at: '2026-09-02T08:00:00.000Z',
  });

  return {
    data: {
      scope: { branch_ids: ['branch-1'] },
      available_actions: { define_purpose: true, consent: true, disclose: true, export: true, approve_bulk_export: true },
      actor: { person_id: 'officer-9' },
      people: [
        { id: 'person-1', legal_name: 'Subject One', branch_id: 'branch-1' },
        { id: 'person-2', legal_name: 'Subject Two', branch_id: 'branch-1' },
      ],
      purposes: PURPOSES,
      consents: [
        consent('c-draft', 'p-updates', 'draft', { submit: true }),
        consent('c-submitted', 'p-reminders', 'submitted', { verify: true }),
        consent('c-verified', 'p-newsletter', 'verified', { activate: true }),
        consent('c-active', 'p-fees', 'active', { expire: true, revoke: true, archive: true }),
        consent('c-expired', 'p-referrals', 'expired', { archive: true }, '2025-01-05', '2026-01-05'),
        consent('c-revoked', 'p-statutory', 'revoked', { archive: true }),
        consent('c-archived', 'p-attendance', 'archived', {}),
        consent('c-locked', 'p-survey', 'draft', {}),
      ],
      revocations: [
        { id: 'rev-1', consent_id: 'c-revoked', revoked_by: 'officer-9', scope: 'all-channels', effect: 'immediate-cessation', created_at: '2026-09-03T08:00:00.000Z' },
      ],
      disclosures: [
        { id: 'disc-1', subject_person_id: 'person-1', recipient: 'Ministry of Education', purpose: 'statutory-reporting', authority: 'privacy.disclose', scope: 'subject:person-1', disclosed_category: 'academic-records', disclosed_by: 'officer-9', created_at: '2026-09-02T08:00:00.000Z' },
        { id: 'disc-2', subject_person_id: 'person-1', recipient: 'Subject One', purpose: 'subject-data-request', authority: 'privacy.export', scope: 'branch:branch-1', disclosed_category: 'privacy-dataset', disclosed_by: 'officer-9', created_at: '2026-09-04T08:00:00.000Z' },
      ],
      export_requests: [
        { id: 'req-open', subject_person_id: 'person-1', purpose: 'organization-wide audit', organization_id: 'org-1', lifecycle_state: 'requested', requested_by: 'officer-9', approver_one_id: null, approver_two_id: null, exported_by: null, disclosure_id: null, available_actions: { approve: true, execute: false }, created_at: '2026-09-05T08:00:00.000Z', updated_at: '2026-09-05T08:00:00.000Z' },
        { id: 'req-self', subject_person_id: 'person-1', purpose: 'regulator-request', organization_id: 'org-1', lifecycle_state: 'requested', requested_by: 'officer-9', approver_one_id: 'officer-9', approver_two_id: null, exported_by: null, disclosure_id: null, available_actions: { approve: false, execute: false }, created_at: '2026-09-05T08:00:00.000Z', updated_at: '2026-09-05T08:00:00.000Z' },
        { id: 'req-approved', subject_person_id: 'person-1', purpose: 'annual-review', organization_id: 'org-1', lifecycle_state: 'approved', requested_by: 'officer-9', approver_one_id: 'approver-a', approver_two_id: 'officer-9', exported_by: null, disclosure_id: null, available_actions: { approve: false, execute: true }, created_at: '2026-09-06T08:00:00.000Z', updated_at: '2026-09-06T08:00:00.000Z' },
        { id: 'req-exported', subject_person_id: 'person-1', purpose: 'closed-release', organization_id: 'org-1', lifecycle_state: 'exported', requested_by: 'officer-9', approver_one_id: 'approver-a', approver_two_id: 'officer-9', exported_by: 'officer-9', disclosure_id: 'disc-bulk', available_actions: { approve: false, execute: false }, created_at: '2026-09-07T08:00:00.000Z', updated_at: '2026-09-07T08:00:00.000Z' },
      ],
      policy: {
        authority: 'server_access_decision',
        history: 'append_only_evidence',
        correction: 'lifecycle_or_new_evidence_only',
        erasure: 'revocation_expiry_and_archive_never_delete',
      },
    },
  };
}

function dossierFixture() {
  const history = (consentId, purposeId, state, revocations = []) => ({
    consent_id: consentId,
    purpose_id: purposeId,
    lifecycle_state: state,
    effective_from: '2026-01-05',
    effective_to: null,
    recorded_by: 'officer-9',
    evidence_ref: 'evidence/withdrawal-form-9',
    revocations,
  });

  return {
    data: {
      subject_person_id: 'person-1',
      as_of: '2026-09-13',
      consents: [history('c-active', 'p-fees', 'active')],
      consent_history: [
        history('c-active', 'p-fees', 'active'),
        history('c-expired', 'p-referrals', 'expired'),
        history('c-revoked', 'p-statutory', 'revoked', [
          { revocation_id: 'rev-1', revoked_by: 'officer-9', scope: 'all-channels', effect: 'immediate-cessation', at: '2026-09-03 08:00:00' },
        ]),
        history('c-archived', 'p-attendance', 'archived'),
      ],
      disclosures: [
        { disclosure_id: 'disc-1', recipient: 'Ministry of Education', purpose: 'statutory-reporting', authority: 'privacy.disclose', scope: 'subject:person-1', disclosed_category: 'academic-records', disclosed_by: 'officer-9', at: '2026-09-02 08:00:00' },
      ],
      export_requests: [
        { request_id: 'req-open', purpose: 'organization-wide audit', organization_id: 'org-1', lifecycle_state: 'requested', requested_by: 'officer-9', approver_one_id: null, approver_two_id: null, exported_by: null, disclosure_id: null, closed: false, at: '2026-09-05 08:00:00' },
        { request_id: 'req-exported', purpose: 'closed-release', organization_id: 'org-1', lifecycle_state: 'exported', requested_by: 'officer-9', approver_one_id: 'approver-a', approver_two_id: 'officer-9', exported_by: 'officer-9', disclosure_id: 'disc-bulk', closed: true, at: '2026-09-07 08:00:00' },
      ],
      provenance: { organization_id: 'org-1', campus_id: 'campus-1', branch_id: 'branch-1', department_id: null },
      policy: {
        authority: 'server_access_decision',
        history: 'append_only_evidence',
        correction: 'lifecycle_or_new_evidence_only',
        erasure: 'revocation_expiry_and_archive_never_delete',
      },
    },
  };
}

let bundlePromise = null;
function privacyBundle() {
  bundlePromise ??= build({
    stdin: {
      contents: `
        import { createRoot } from 'react-dom/client';
        import { PrivacyApp } from './privacy';

        const api = window.__privacyApi;
        createRoot(document.getElementById('privacy-root'))
          .render(<PrivacyApp getJson={api.getJson} postJson={api.postJson} csrfToken="test-token" />);
      `,
      resolveDir: path.join(root, 'resources/js'),
      loader: 'jsx',
    },
    bundle: true,
    write: false,
    format: 'iife',
    jsx: 'automatic',
    loader: { '.css': 'empty' },
    define: { 'process.env.NODE_ENV': '"production"' },
    logLevel: 'silent',
  }).then((result) => result.outputFiles[0].text);

  return bundlePromise;
}

async function renderPrivacy({ fixture = workspaceFixture(), workspaceErrorMessage = null, postErrorMessage = null, postResponse = { data: {} } } = {}) {
  const code = await privacyBundle();
  const dom = new JSDOM('<!doctype html><html><body><div id="privacy-root"></div></body></html>', {
    runScripts: 'outside-only',
    url: 'https://app.test/privacy',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  // JSDOM has no layout engine; the component's deliberate focus-management
  // effect calls scrollIntoView on the opened command/dossier panels.
  window.Element.prototype.scrollIntoView = function scrollIntoView() {};
  // The component checks `reason instanceof Error` inside the JSDOM realm,
  // exactly like production where api.ts throws in-page; rejection errors
  // must therefore be created in the window realm, not the Node realm.
  const workspaceError = workspaceErrorMessage === null ? null : new window.Error(workspaceErrorMessage);
  const postError = postErrorMessage === null ? null : new window.Error(postErrorMessage);
  const posts = [];
  const gets = [];
  window.__privacyApi = {
    getJson: (path) => {
      gets.push(path);
      if (workspaceError && path === '/privacy/workspace') return Promise.reject(workspaceError);
      if (path.startsWith('/privacy/subjects/')) return Promise.resolve(dossierFixture());

      return Promise.resolve(fixture);
    },
    postJson: (path, payload) => {
      posts.push({ path, payload });
      if (postError) return Promise.reject(postError);

      return Promise.resolve(typeof postResponse === 'function' ? postResponse(path, payload) : postResponse);
    },
  };
  const confirmMessages = [];
  window.confirm = (message) => {
    confirmMessages.push(message);

    return window.__confirmAnswer ?? true;
  };
  window.eval(code);
  await new Promise((resolveTick) => setTimeout(resolveTick, 200));

  const document = window.document;
  const tables = (scope = document) => [...scope.querySelectorAll('.table-wrap table')];
  const rowBy = (needle, table = tables()[0]) => {
    const row = [...table.querySelectorAll('tbody tr')].find((node) => (node.textContent || '').includes(needle));
    assert.ok(row, `row "${needle}" is rendered`);

    return row;
  };
  const buttonsOf = (row) => [...row.querySelectorAll('button')].map((button) => (button.textContent || '').trim());
  const setInput = (element, value) => {
    const proto = element instanceof window.HTMLSelectElement
      ? window.HTMLSelectElement.prototype
      : window.HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(element, value);
    element.dispatchEvent(new window.Event(element instanceof window.HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
  };
  const clickButton = (row, label) => {
    const button = [...row.querySelectorAll('button')].find((node) => (node.textContent || '').trim() === label);
    assert.ok(button, `button "${label}" exists in the row`);
    button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  };
  const openTab = (label) => {
    const tab = [...document.querySelectorAll('[role="tab"]')].find((node) => (node.textContent || '').trim() === label);
    assert.ok(tab, `tab "${label}" is rendered`);
    tab.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  };
  const labeledControl = (form, labelText) => {
    const label = [...form.querySelectorAll('label')]
      .find((node) => (node.firstChild?.textContent || '').trim().startsWith(labelText));
    assert.ok(label, `label "${labelText}" is rendered`);

    return label.querySelector('input, select');
  };
  const formBy = (submitLabel) => {
    const form = [...document.querySelectorAll('form')].find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(submitLabel));
    assert.ok(form, `the form submitting "${submitLabel}" is rendered`);

    return form;
  };
  const submit = (form) => form.querySelector('button[type="submit"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));

  return { window, document, posts, gets, confirmMessages, tables, rowBy, buttonsOf, setInput, clickButton, openTab, labeledControl, formBy, submit };
}

const tick = async () => new Promise((resolveTick) => setTimeout(resolveTick, 120));

test('the rendered consent register offers exactly the state-legal actions', async () => {
  const ui = await renderPrivacy();
  const { document, rowBy, buttonsOf } = ui;

  const metrics = [...document.querySelectorAll('.summary-grid .panel')].map((panel) => ({
    value: panel.querySelector('.metric')?.textContent,
    label: panel.querySelector('.metric-label')?.textContent,
  }));
  assert.deepEqual(metrics, [
    { value: '2', label: 'Visible subjects' },
    { value: '8', label: 'Consent records' },
    { value: '2', label: 'Disclosure evidence' },
    { value: '4', label: 'Export requests' },
  ]);

  // Every row also offers the subject dossier: it is a read, never a mutation.
  assert.deepEqual(buttonsOf(rowBy('c-draft')), ['Dossier', 'Submit']);
  assert.deepEqual(buttonsOf(rowBy('c-submitted')), ['Dossier', 'Verify']);
  assert.deepEqual(buttonsOf(rowBy('c-verified')), ['Dossier', 'Activate']);
  assert.deepEqual(buttonsOf(rowBy('c-active')), ['Dossier', 'Expire', 'Revoke', 'Archive']);
  assert.deepEqual(buttonsOf(rowBy('c-expired')), ['Dossier', 'Archive']);
  assert.deepEqual(buttonsOf(rowBy('c-revoked')), ['Dossier', 'Archive']);
  assert.deepEqual(buttonsOf(rowBy('c-archived')), ['Dossier']);
  // A draft the server did not authorize for submission offers no submit:
  // the affordance matrix is the server's, never the client's guess.
  assert.deepEqual(buttonsOf(rowBy('c-locked')), ['Dossier']);

  // Withdrawal evidence is rendered as its own append-only record.
  const withdrawalRow = rowBy('all-channels', ui.tables()[1]);
  assert.match(withdrawalRow.textContent || '', /immediate-cessation/);
  assert.match(withdrawalRow.textContent || '', /officer-9/);
});

test('recording a consent posts the canonical payload with its write-only evidence locator', async () => {
  const ui = await renderPrivacy();
  const { document, posts, setInput, openTab, labeledControl, formBy, submit } = ui;

  openTab('Purpose & consent');
  await tick();
  const form = formBy('Record consent draft');
  setInput(labeledControl(form, 'Subject'), 'person-1');
  setInput(labeledControl(form, 'Purpose'), 'p-updates');
  setInput(labeledControl(form, 'Effective from'), '2026-09-13');
  setInput(labeledControl(form, 'Evidence reference'), 'evidence/signed-form-2026');
  await tick();
  submit(form);
  await tick();

  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/privacy/consents');
  assert.deepEqual({ ...posts[0].payload }, {
    subject_person_id: 'person-1',
    purpose_id: 'p-updates',
    evidence_ref: 'evidence/signed-form-2026',
    effective_from: '2026-09-13',
    effective_to: null,
  });
  assert.match(document.querySelector('.notice')?.textContent || '', /Consent recorded as a draft with its evidence/);
  // The evidence locator is write-only: it is sent once and never rendered.
  assert.ok(!(document.body.textContent || '').includes('evidence/signed-form-2026'), 'the workspace must not display a consent evidence locator');
});

test('revocation opens a command panel and posts its scope and effect', async () => {
  // A withdrawal without a scope and an effect is not evidence, so the panel
  // requires both and the component posts nothing until they are supplied.
  const ui = await renderPrivacy({ postErrorMessage: 'the subject is outside your authorized consent branch scope (privacy.consent_scope_denied)' });
  const { window, document, posts, setInput, clickButton, rowBy, labeledControl } = ui;

  clickButton(rowBy('c-active'), 'Revoke');
  await tick();
  const panel = document.querySelector('.privacy-command-panel');
  assert.ok(panel, 'the revocation command panel opens');
  assert.match(panel.textContent || '', /Subject One/);
  assert.match(panel.textContent || '', /fee-notices · Email/);
  assert.match(panel.textContent || '', /Revocation stops future use and is recorded as append-only evidence; nothing is erased\./);

  const form = panel.querySelector('form');
  setInput(labeledControl(form, 'Withdrawal scope'), 'all-channels');
  setInput(labeledControl(form, 'Effect'), 'immediate-cessation');
  await tick();
  form.querySelector('button[type="submit"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  await tick();

  assert.equal(posts.length, 1, 'the intended revocation POST was attempted');
  assert.equal(posts[0].path, '/privacy/consents/c-active/revoke');
  assert.deepEqual({ ...posts[0].payload }, { scope: 'all-channels', effect: 'immediate-cessation' });
  // The component must show the server's message and never claim success.
  assert.match(document.querySelector('.alert')?.textContent || '', /the subject is outside your authorized consent branch scope/);
  assert.equal(document.querySelector('.notice'), null, 'a rejection must never show a success notice');
});

test('activation and archival demand a consequential confirm and post the canonical path', async () => {
  const ui = await renderPrivacy();
  const { window, document, posts, confirmMessages, clickButton, rowBy } = ui;

  window.__confirmAnswer = false;
  clickButton(rowBy('c-verified'), 'Activate');
  await tick();
  assert.equal(posts.length, 0, 'a refused confirm must post nothing');
  assert.equal(confirmMessages.length, 1);
  assert.match(confirmMessages[0], /Activate the consent for “Subject One”/);
  assert.match(confirmMessages[0], /Activation is what makes it current use authority for personal data\./);

  window.__confirmAnswer = true;
  clickButton(rowBy('c-verified'), 'Activate');
  await tick();
  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/privacy/consents/c-verified/activate');
  assert.match(document.querySelector('.notice')?.textContent || '', /Consent active; it now carries current use authority\./);

  clickButton(rowBy('c-expired'), 'Archive');
  await tick();
  assert.equal(posts.length, 2);
  assert.equal(posts[1].path, '/privacy/consents/c-expired/archive');
  assert.equal(confirmMessages.length, 3, 'the refused and accepted activations plus the archive each asked once');
  assert.match(confirmMessages[2], /Archive this consent\?/);
  assert.match(confirmMessages[2], /Archiving does not erase consent evidence\./);
  assert.match(document.querySelector('.notice')?.textContent || '', /Consent archived; consent history is retained\./);

  // Forward steps an operator initiates deliberately need no second dialog.
  clickButton(rowBy('c-draft'), 'Submit');
  await tick();
  assert.equal(posts.length, 3);
  assert.equal(posts[2].path, '/privacy/consents/c-draft/submit');
  assert.equal(confirmMessages.length, 3, 'submission is not gated behind a confirm dialog');
});

test('a bulk export signature renders the server chain state, never a client-side guess', async () => {
  const first = await renderPrivacy({ postResponse: { data: { request_id: 'req-open', lifecycle_state: 'requested', correlation_id: 'corr-1' } } });
  first.openTab('Disclosure & export');
  await tick();
  const exportTable = first.tables()[1];
  first.clickButton(first.rowBy('req-open', exportTable), 'Sign approval');
  await tick();
  assert.equal(first.posts.length, 1);
  assert.equal(first.posts[0].path, '/privacy/exports/req-open/approve');
  assert.equal(first.posts[0].payload, undefined, 'a signature carries no client-supplied payload');
  assert.match(first.document.querySelector('.notice')?.textContent || '', /First approval signed; a distinct second approver must sign before execution\./);

  const second = await renderPrivacy({ postResponse: { data: { request_id: 'req-open', lifecycle_state: 'approved', correlation_id: 'corr-2' } } });
  second.openTab('Disclosure & export');
  await tick();
  second.clickButton(second.rowBy('req-open', second.tables()[1]), 'Sign approval');
  await tick();
  assert.match(second.document.querySelector('.notice')?.textContent || '', /Second approval signed; the request is approved for execution\./);

  // A request this actor already signed first offers no second signature, and
  // an executed request is closed: both come from the server projection.
  assert.deepEqual(second.buttonsOf(second.rowBy('req-self', second.tables()[1])), []);
  assert.deepEqual(second.buttonsOf(second.rowBy('req-exported', second.tables()[1])), []);
  assert.match(second.rowBy('req-exported', second.tables()[1]).textContent || '', /Exported/);
  assert.deepEqual(second.buttonsOf(second.rowBy('req-approved', second.tables()[1])), ['Execute export']);
});

test('executing an approved export posts the path and renders a receipt, never the dataset', async () => {
  const ui = await renderPrivacy({
    postResponse: {
      data: {
        export_id: 'exp-1',
        disclosure_id: 'disc-bulk',
        correlation_id: 'corr-9',
        dataset: {
          subject: { person_id: 'person-1', legal_name: 'Subject One' },
          consents: [{ consent_id: 'c-active', purpose_id: 'p-fees', lifecycle_state: 'active' }],
          disclosures: [{ disclosure_id: 'disc-1', recipient: 'Leaked-Recipient-Token', purpose: 'statutory-reporting' }],
        },
      },
    },
  });
  const { window, document, posts, confirmMessages, openTab, tables, rowBy, clickButton } = ui;

  openTab('Disclosure & export');
  await tick();
  window.__confirmAnswer = false;
  clickButton(rowBy('req-approved', tables()[1]), 'Execute export');
  await tick();
  assert.equal(posts.length, 0, 'a refused confirm must release nothing');
  assert.match(confirmMessages[0], /This releases personal data and records an immutable disclosure\./);

  window.__confirmAnswer = true;
  clickButton(rowBy('req-approved', tables()[1]), 'Execute export');
  await tick();
  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/privacy/exports/req-approved/execute');

  const receipt = document.querySelector('.privacy-receipt');
  assert.ok(receipt, 'the release receipt panel opens');
  const text = receipt.textContent || '';
  assert.match(text, /disc-bulk/);
  assert.match(text, /corr-9/);
  assert.match(text, /Consent records released/);
  assert.match(text, /1/);
  assert.match(text, /Disclosure records released/);
  // The exported dataset is never rendered: the disclosure is the evidence.
  assert.ok(!text.includes('Leaked-Recipient-Token'), 'the receipt must not render released dataset content');
  assert.match(text, /The exported dataset is not rendered in the browser/);
});

test('a disclosure and an export resolve their declared scope from the subject, never a client tenant', async () => {
  const ui = await renderPrivacy();
  const { document, posts, setInput, openTab, labeledControl, formBy, submit } = ui;

  openTab('Disclosure & export');
  await tick();
  const disclosureForm = formBy('Record disclosure');
  setInput(labeledControl(disclosureForm, 'Subject'), 'person-1');
  setInput(labeledControl(disclosureForm, 'Recipient'), 'Ministry of Education');
  setInput(labeledControl(disclosureForm, 'Purpose of release'), 'statutory-reporting');
  setInput(labeledControl(disclosureForm, 'Authority relied on'), 'privacy.disclose');
  setInput(labeledControl(disclosureForm, 'Disclosed category'), 'academic-records');
  await tick();
  submit(disclosureForm);
  await tick();

  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/privacy/disclosures');
  assert.deepEqual({ ...posts[0].payload }, {
    subject_person_id: 'person-1',
    recipient: 'Ministry of Education',
    purpose: 'statutory-reporting',
    authority: 'privacy.disclose',
    scope_type: 'subject',
    scope_id: 'person-1',
    disclosed_category: 'academic-records',
  });
  assert.match(document.querySelector('.notice')?.textContent || '', /Disclosure recorded as immutable release evidence\./);

  const exportForm = formBy('Export subject data');
  setInput(labeledControl(exportForm, 'Subject'), 'person-1');
  setInput(labeledControl(exportForm, 'Purpose of export'), 'subject-data-request');
  setInput(labeledControl(exportForm, 'Declared scope'), 'branch');
  await tick();
  submit(exportForm);
  await tick();

  assert.equal(posts.length, 2);
  assert.equal(posts[1].path, '/privacy/exports');
  // A branch-scoped declaration resolves to the subject's own branch; the
  // browser never types a tenant identifier.
  assert.deepEqual({ ...posts[1].payload }, {
    subject_person_id: 'person-1',
    purpose: 'subject-data-request',
    scope_type: 'branch',
    scope_id: 'branch-1',
  });

  const bulkForm = formBy('Request organization-wide export');
  setInput(labeledControl(bulkForm, 'Subject'), 'person-2');
  setInput(labeledControl(bulkForm, 'Purpose'), 'organization-wide audit');
  await tick();
  submit(bulkForm);
  await tick();

  assert.equal(posts.length, 3);
  assert.equal(posts[2].path, '/privacy/exports/bulk');
  assert.deepEqual({ ...posts[2].payload }, { subject_person_id: 'person-2', purpose: 'organization-wide audit' });
  assert.match(document.querySelector('.notice')?.textContent || '', /it executes only after two distinct approvers sign in their own sessions/);
});

test('the subject dossier renders current authority, history and withdrawal evidence', async () => {
  const ui = await renderPrivacy();
  const { window, document, gets, setInput, clickButton, rowBy } = ui;

  clickButton(rowBy('c-active'), 'Dossier');
  await tick();
  assert.ok(gets.some((path) => path === '/privacy/subjects/person-1'), 'the dossier is fetched from the canonical endpoint');

  const panel = document.querySelector('.privacy-dossier');
  assert.ok(panel, 'the dossier panel opens');
  const text = panel.textContent || '';
  assert.match(text, /Subject One/);
  assert.match(text, /Current use authority/);
  assert.match(text, /fee-notices · Email/);
  assert.match(text, /Complete consent history/);
  assert.match(text, /enrollment-updates|statutory-reporting/);
  assert.match(text, /Withdrawn by/);
  assert.match(text, /all-channels \/ immediate-cessation/);
  assert.match(text, /Ministry of Education/);
  assert.match(text, /organization-wide audit/);
  assert.match(text, /closed/);
  assert.match(text, /As of 2026-09-13/);
  // Neither the workspace nor the dossier may ever surface a consent evidence
  // locator, even when a projection regresses and includes one.
  assert.ok(!text.includes('evidence/signed-form-2026'), 'the register projection never shows an evidence locator');
  assert.ok(!text.includes('evidence/withdrawal-form-9'), 'the dossier projection never shows an evidence locator');

  // Re-querying as of another day is an explicit operator act.
  const asOf = panel.querySelector('form input[type="date"]');
  assert.ok(asOf, 'the dossier can be re-read as of another day');
  setInput(asOf, '2026-01-01');
  await tick();
  panel.querySelector('form button[type="submit"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  await tick();
  assert.ok(gets.some((path) => path === '/privacy/subjects/person-1?as_of=2026-01-01'), 'the as-of day is sent to the canonical endpoint');
});

test('a denied workspace fails closed with the server message and a retry', async () => {
  const ui = await renderPrivacy({ workspaceErrorMessage: 'no effective privacy capability scope is available for this workspace (api.organization_read_denied)' });
  const { document } = ui;

  assert.match(document.querySelector('h1')?.textContent || '', /Privacy workspace unavailable/);
  assert.match(document.querySelector('.alert')?.textContent || '', /no effective privacy capability scope is available for this workspace/);
  const retry = [...document.querySelectorAll('button')].find((button) => (button.textContent || '').trim() === 'Retry');
  assert.ok(retry, 'a denied workspace offers an explicit retry');
  assert.equal(document.querySelector('tbody'), null, 'a denied workspace renders no privacy rows');
});

test('a scope without mutation authority renders the reference catalog and no capture forms', async () => {
  const fixture = workspaceFixture();
  fixture.data.available_actions = { define_purpose: false, consent: false, disclose: false, export: false, approve_bulk_export: false };
  fixture.data.consents = fixture.data.consents.map((row) => ({ ...row, available_actions: { submit: false, verify: false, activate: false, expire: false, revoke: false, archive: false } }));

  const ui = await renderPrivacy({ fixture });
  const { document, openTab } = ui;

  openTab('Purpose & consent');
  await tick();
  assert.match(document.body.textContent || '', /Purpose definitions are unavailable in your current server-authorized scope/);
  assert.match(document.body.textContent || '', /does not include recording consent for another person/);
  // The catalog stays readable: reference data is not a mutation authority.
  assert.match(document.body.textContent || '', /enrollment-updates/);

  openTab('Disclosure & export');
  await tick();
  assert.match(document.body.textContent || '', /does not include recording disclosures/);
  assert.match(document.body.textContent || '', /does not include subject-data exports/);
  assert.match(document.body.textContent || '', /No approval authority in scope/);
  assert.match(document.body.textContent || '', /No action available to you/);
});
