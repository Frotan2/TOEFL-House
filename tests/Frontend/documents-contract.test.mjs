import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { JSDOM } from 'jsdom';
import { build } from 'esbuild';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const literalPattern = (value) => new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

test('Documents & Evidence is mounted by the canonical React console', () => {
  const app = read('resources/js/app.tsx');
  const documents = read('resources/js/documents.tsx');

  assert.match(app, /import \{ DocumentsApp \} from ['"]\.\/documents['"]/);
  assert.match(app, /case ['"]documents['"]:/);
  assert.match(documents, /AppShell current="documents"/);
  assert.match(documents, /\/documents/);
  assert.match(documents, /available_actions/);
  assert.match(documents, /\/history/);
  assert.doesNotMatch(documents, /from ['"]react-dom\/client['"]/);
  assert.doesNotMatch(documents, /\bcreateRoot\s*\(/);
  assert.doesNotMatch(documents, /\bfetch\s*\(/);
});

test('Documents API routes expose the canonical lifecycle and history contract', () => {
  const routes = read('routes/documents-api.php');

  for (const token of [
    "Route::prefix('documents')->name('api.documents.')",
    "Route::get('/', [DocumentsApiController::class, 'workspace'])",
    "Route::post('/classifications', [DocumentsApiController::class, 'defineClassification'])",
    "Route::post('/retention-rules', [DocumentsApiController::class, 'defineRetentionRule'])",
    "Route::post('/', [DocumentsApiController::class, 'register'])",
    "Route::get('/{documentId}/history', [DocumentsApiController::class, 'history'])",
    "Route::post('/{documentId}/submit', [DocumentsApiController::class, 'submit'])",
    "Route::post('/{documentId}/verify', [DocumentsApiController::class, 'verify'])",
    "Route::post('/{documentId}/activate', [DocumentsApiController::class, 'activate'])",
    "Route::post('/{documentId}/expire', [DocumentsApiController::class, 'expire'])",
    "Route::post('/{documentId}/archive', [DocumentsApiController::class, 'archive'])",
    "Route::post('/{documentId}/retention', [DocumentsApiController::class, 'decideRetention'])",
  ]) assert.match(routes, literalPattern(token));
});

test('Documents read projection is server-scoped, minimal, and command-owned', () => {
  const bootstrap = read('bootstrap/app.php');
  const controller = read('app/Http/Controllers/Api/DocumentsApiController.php');
  const webRoutes = read('routes/web.php');

  assert.match(bootstrap, /routes\/documents-api\.php/);
  for (const capability of ['RegisterDocument::CAPABILITY', 'TransitionDocument::CAPABILITY', 'DecideRetention::CAPABILITY']) {
    assert.match(controller, literalPattern(capability));
  }
  assert.match(controller, /defineClassification.*null/s);
  assert.match(controller, /DocumentHistoryQuery::class/);
  assert.match(controller, /Per-record affordances prevent a capability in branch A/);
  assert.match(controller, /Storage references, content fingerprints, and verification/);
  assert.doesNotMatch(controller, /use App\\Modules\\Documents\\Models\\DocumentVersion/);
  assert.match(webRoutes, /Route::view\('\/', 'workspace', \['view' => 'documents'\]\)->name\('index'\)/);
  assert.ok(!fs.existsSync(path.join(root, 'resources/views/documents/index.blade.php')), 'Documents must not retain a second Blade read model.');
});

// ---------------------------------------------------------------------------
// Behavioural contract: the real DocumentsApp component rendered in a real
// DOM against a scripted API client. Static greps cannot prove which actions
// the UI offers per lifecycle state, what payloads it sends, or how it
// surfaces server rejections; these tests render the component and observe.
// ---------------------------------------------------------------------------

function workspaceFixture() {
  const person = { id: 'person-1', legal_name: 'Subject One', branch_id: 'branch-1' };
  const classification = { id: 'class-1', category: 'identity_evidence', owner_module: 'identity', access_class: 'restricted' };
  const doc = (id, state, actions, title) => ({
    id,
    subject_person_id: 'person-1',
    classification_id: 'class-1',
    title,
    lifecycle_state: state,
    available_actions: { submit: false, verify: false, retention: false, ...actions },
    created_at: '2026-09-01T08:00:00.000Z',
    updated_at: '2026-09-02T08:00:00.000Z',
  });

  return {
    data: {
      scope: { branch_ids: ['branch-1'] },
      available_actions: { classify: true, register: true, verify: true, retention: true },
      people: [person],
      classifications: [classification],
      retention_rules: [{ id: 'rule-1', category: 'identity_evidence', retention_days: 365, legal_basis: 'statute-7y', operational_basis: null }],
      documents: [
        doc('doc-draft', 'draft', { submit: true }, 'Draft evidence'),
        doc('doc-submitted', 'submitted', { verify: true }, 'Submitted evidence'),
        doc('doc-rejected', 'rejected', { submit: true }, 'Rejected evidence'),
        doc('doc-verified', 'verified', { verify: true }, 'Verified evidence'),
        doc('doc-active', 'active', { verify: true, retention: true }, 'Active evidence'),
        doc('doc-expired', 'expired', { verify: true }, 'Expired evidence'),
        doc('doc-archived', 'archived', {}, 'Archived evidence'),
        doc('doc-locked', 'draft', {}, 'Locked draft'),
      ],
      retention_decisions: [
        { id: 'decision-1', document_id: 'doc-active', rule_id: 'rule-1', action: 'retain', basis: 'statute-7y', decided_by: 'person-2', created_at: '2026-09-02T08:00:00.000Z' },
      ],
      policy: { authority: 'server_access_decision', history: 'append_only_versions_verifications_and_retention', correction: 'new_version_or_lifecycle_transition_only' },
    },
  };
}

function historyFixture() {
  return {
    data: {
      document_id: 'doc-submitted',
      lifecycle_state: 'submitted',
      versions: [
        { version_no: 1, content_hash: 'hash-version-one-value', uploaded_by: 'person-1', verifications: [] },
        {
          version_no: 2,
          content_hash: 'hash-version-two-value',
          uploaded_by: 'person-1',
          verifications: [{ verifier: 'person-2', result: 'pass', reason: 'Matches the registry.', at: '2026-09-02 10:00:00' }],
        },
      ],
      policy: { authority: 'server_access_decision', history: 'immutable_append_only', correction: 'new_version_or_lifecycle_transition_only' },
    },
  };
}

let bundlePromise = null;
function documentsBundle() {
  bundlePromise ??= build({
    stdin: {
      contents: `
        import { createRoot } from 'react-dom/client';
        import { DocumentsApp } from './documents';

        const api = window.__documentsApi;
        createRoot(document.getElementById('documents-root'))
          .render(<DocumentsApp getJson={api.getJson} postJson={api.postJson} csrfToken="test-token" />);
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

async function renderDocuments({ fixture = workspaceFixture(), workspaceErrorMessage = null, postErrorMessage = null } = {}) {
  const code = await documentsBundle();
  const dom = new JSDOM('<!doctype html><html><body><div id="documents-root"></div></body></html>', {
    runScripts: 'outside-only',
    url: 'https://app.test/documents',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  // JSDOM has no layout engine; the component's deliberate focus-management
  // effect calls scrollIntoView on the opened command/history panels.
  window.Element.prototype.scrollIntoView = function scrollIntoView() {};
  // The component checks `reason instanceof Error` inside the JSDOM realm,
  // exactly like production where api.ts throws in-page; rejection errors
  // must therefore be created in the window realm, not the Node realm.
  const workspaceError = workspaceErrorMessage === null ? null : new window.Error(workspaceErrorMessage);
  const postError = postErrorMessage === null ? null : new window.Error(postErrorMessage);
  const posts = [];
  const gets = [];
  window.__documentsApi = {
    getJson: (path) => {
      gets.push(path);
      if (workspaceError && path === '/documents') return Promise.reject(workspaceError);
      if (path.endsWith('/history')) return Promise.resolve(historyFixture());

      return Promise.resolve(fixture);
    },
    postJson: (path, payload) => {
      posts.push({ path, payload });
      if (postError) return Promise.reject(postError);

      return Promise.resolve({ data: {} });
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
  const rowBy = (needle) => {
    const row = [...document.querySelectorAll('tbody tr')].find((node) => (node.textContent || '').includes(needle));
    assert.ok(row, `row "${needle}" is rendered`);

    return row;
  };
  const buttonsOf = (row) => [...row.querySelectorAll('button')].map((button) => (button.textContent || '').trim());
  const setInput = (element, value) => {
    const proto = element instanceof window.HTMLTextAreaElement
      ? window.HTMLTextAreaElement.prototype
      : element instanceof window.HTMLSelectElement
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

    return label.querySelector('input, textarea, select');
  };

  return { window, document, posts, gets, confirmMessages, rowBy, buttonsOf, setInput, clickButton, openTab, labeledControl };
}

const tick = async () => new Promise((resolveTick) => setTimeout(resolveTick, 120));

test('the rendered registry offers exactly the state-legal actions and counts', async () => {
  const ui = await renderDocuments();
  const { document, rowBy, buttonsOf } = ui;

  const metrics = [...document.querySelectorAll('.summary-grid .panel')].map((panel) => ({
    value: panel.querySelector('.metric')?.textContent,
    label: panel.querySelector('.metric-label')?.textContent,
  }));
  assert.deepEqual(metrics, [
    { value: '8', label: 'Visible documents' },
    { value: '5', label: 'In lifecycle review' },
    { value: '1', label: 'Active documents' },
    { value: '1', label: 'Retention decisions' },
  ]);

  assert.deepEqual(buttonsOf(rowBy('Draft evidence')), ['History', 'Submit version']);
  assert.deepEqual(buttonsOf(rowBy('Submitted evidence')), ['History', 'Verify']);
  assert.deepEqual(buttonsOf(rowBy('Rejected evidence')), ['History', 'Submit version']);
  assert.deepEqual(buttonsOf(rowBy('Verified evidence')), ['History', 'Activate']);
  assert.deepEqual(buttonsOf(rowBy('Active evidence')), ['History', 'Expire', 'Archive', 'Retention']);
  assert.deepEqual(buttonsOf(rowBy('Expired evidence')), ['History', 'Archive']);
  assert.deepEqual(buttonsOf(rowBy('Archived evidence')), ['History']);
  // A draft the server did not authorize for submission offers no submit:
  // the affordance matrix is the server's, never the client's guess.
  assert.deepEqual(buttonsOf(rowBy('Locked draft')), ['History']);
});

test('registration posts the canonical payload and surfaces the notice', async () => {
  const ui = await renderDocuments();
  const { window, document, posts, setInput, openTab, labeledControl } = ui;

  openTab('Register document');
  await tick();
  const form = [...document.querySelectorAll('form')].find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes('Register immutable version 1'));
  assert.ok(form, 'the registration form is rendered');
  setInput(labeledControl(form, 'Subject'), 'person-1');
  setInput(labeledControl(form, 'Classification'), 'class-1');
  setInput(labeledControl(form, 'Document title'), 'Passport scan');
  setInput(labeledControl(form, 'Content hash'), 'sha256:abc123');
  setInput(labeledControl(form, 'Storage reference'), 'storage/private/passport-1');
  await tick();
  form.querySelector('button[type="submit"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  await tick();

  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/documents');
  assert.deepEqual({ ...posts[0].payload }, {
    subject_person_id: 'person-1',
    classification_id: 'class-1',
    title: 'Passport scan',
    content_hash: 'sha256:abc123',
    storage_ref: 'storage/private/passport-1',
  });
  assert.match(document.querySelector('.notice')?.textContent || '', /Document registered as a draft with immutable version 1\./);
});

test('verification posts the selected result and surfaces server rejections as alerts', async () => {
  // The component must show the server's message and never claim success.
  const ui = await renderDocuments({ postErrorMessage: 'the verifier may not be the uploader of the version under review (documents.verifier_is_uploader)' });
  const { window, document, posts, setInput, clickButton, rowBy, labeledControl } = ui;

  clickButton(rowBy('Submitted evidence'), 'Verify');
  await tick();
  const panel = document.querySelector('.documents-command-panel');
  assert.ok(panel, 'the verification command panel opens');
  const form = panel.querySelector('form');
  setInput(labeledControl(form, 'Verification result'), 'fail');
  setInput(labeledControl(form, 'Evidence reason'), 'Illegible scan.');
  await tick();
  form.querySelector('button[type="submit"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  await tick();

  assert.equal(posts.length, 1, 'the intended verification POST was attempted');
  assert.equal(posts[0].path, '/documents/doc-submitted/verify');
  assert.deepEqual({ ...posts[0].payload }, { result: 'fail', reason: 'Illegible scan.' });
  assert.match(document.querySelector('.alert')?.textContent || '', /the verifier may not be the uploader of the version under review/);
  assert.equal(document.querySelector('.notice'), null, 'a rejection must never show a success notice');
});

test('archive demands an irreversible confirm and posts the canonical path', async () => {
  const ui = await renderDocuments();
  const { window, document, posts, confirmMessages, clickButton, rowBy } = ui;

  window.__confirmAnswer = false;
  clickButton(rowBy('Active evidence'), 'Archive');
  await tick();
  assert.equal(posts.length, 0, 'a refused confirm must post nothing');
  assert.equal(confirmMessages.length, 1);
  assert.match(confirmMessages[0], /Archive \u201CActive evidence\u201D\?/);
  assert.match(confirmMessages[0], /Archiving does not erase immutable evidence\./);

  window.__confirmAnswer = true;
  clickButton(rowBy('Active evidence'), 'Archive');
  await tick();
  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/documents/doc-active/archive');
  assert.match(document.querySelector('.notice')?.textContent || '', /Document archived; immutable evidence remains retained\./);
});

test('history renders the immutable timeline and never a storage reference', async () => {
  const ui = await renderDocuments();
  const { document, gets, clickButton, rowBy } = ui;

  clickButton(rowBy('Submitted evidence'), 'History');
  await tick();
  assert.ok(gets.some((path) => path === '/documents/doc-submitted/history'), 'history is fetched from the canonical endpoint');

  const panel = document.querySelector('.documents-history');
  assert.ok(panel, 'the history panel opens');
  const text = panel.textContent || '';
  assert.match(text, /Version 1/);
  assert.match(text, /Version 2/);
  assert.match(text, /hash-version-one-value/);
  assert.match(text, /uploaded by person-1/);
  assert.match(text, /Pass/);
  assert.match(text, /Matches the registry\./);
  assert.match(text, /person-2/);
  // Storage references are write-only facts: neither the workspace nor the
  // history projection may ever surface one.
  assert.ok(!text.includes('storage/'), 'the history projection never shows a storage reference');
});

test('a denied workspace fails closed with the server message and a retry', async () => {
  const ui = await renderDocuments({ workspaceErrorMessage: 'no effective document capability scope is available for this workspace (api.organization_read_denied)' });
  const { document } = ui;

  assert.match(document.querySelector('h1')?.textContent || '', /Documents workspace unavailable/);
  assert.match(document.querySelector('.alert')?.textContent || '', /no effective document capability scope is available for this workspace/);
  const retry = [...document.querySelectorAll('button')].find((button) => (button.textContent || '').trim() === 'Retry');
  assert.ok(retry, 'a denied workspace offers an explicit retry');
});
