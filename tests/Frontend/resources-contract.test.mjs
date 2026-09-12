import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { JSDOM } from 'jsdom';
import { build } from 'esbuild';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const literalPattern = (value) => new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

test('Library & Resources is mounted in the unified React console', () => {
  const app = read('resources/js/app.tsx');
  const library = read('resources/js/library.tsx');
  assert.match(app, /import \{ LibraryApp \} from ['"]\.\/library['"]/);
  assert.match(app, /case ['"]library['"]:/);
  assert.match(library, /AppShell current="library"/);
  // LibraryApp is imported by the canonical app entrypoint. It must stay a
  // pure feature component: a second root on #react-console races the shared
  // root and can leave unrelated routes blank after React reconciliation.
  assert.doesNotMatch(library, /from ['"]react-dom\/client['"]/);
  assert.doesNotMatch(library, /\bcreateRoot\s*\(/);
  assert.doesNotMatch(library, /document\.getElementById\(['"]react-console['"]\)/);
  assert.equal((app.match(/\bcreateRoot\s*\(/g) ?? []).length, 1, 'the canonical app entrypoint owns exactly one shared-console root');
  assert.match(library, /\/resources\/workspace/);
});

test('Library API routes expose the full resource lifecycle through v1', () => {
  const routes = read('routes/resources-api.php');
  for (const token of [
    "Route::get('/workspace'",
    "Route::post('/books'",
    "Route::post('/books/{copyId}/issue'",
    "Route::post('/issuances/{issuanceId}/return'",
    "Route::post('/issuances/{issuanceId}/loss'",
    "Route::post('/assets'",
    "Route::post('/assets/{assetId}/custody'",
    "Route::post('/assets/{assetId}/custody/release'",
    "Route::post('/assets/{assetId}/disposal'",
    "Route::post('/disposals/{requestId}/approve'",
    "Route::post('/disposals/{requestId}/withdraw'",
    "Route::post('/disposals/{requestId}/execute'",
    "Route::post('/work-orders'",
    "Route::post('/work-orders/{orderId}/approve'",
    "Route::post('/work-orders/{orderId}/start'",
    "Route::post('/work-orders/{orderId}/complete'",
    "Route::post('/work-orders/{orderId}/cancel'",
  ]) assert.match(routes, literalPattern(token));
});

test('Library API is mounted under the canonical authenticated v1 route stack', () => {
  const bootstrap = read('bootstrap/app.php');
  const controller = read('app/Http/Controllers/Api/ResourcesApiController.php');
  assert.match(bootstrap, /routes\/resources-api\.php/);
  assert.match(controller, /final class ResourcesApiController/);
  assert.match(controller, /authorizedBranches\('resources\.books'\)/);
  assert.match(controller, /authorizedBranches\('resources\.asset'\)/);
  assert.match(controller, /authorizedBranches\('facilities\.work'\)/);
  assert.match(controller, /idempotencyKey\(/);
});

// ---------------------------------------------------------------------------
// Behavioural contract: the real LibraryApp component rendered in a real DOM
// against a scripted API client. Static greps cannot prove what the UI sends
// or which actions it offers per lifecycle state; these tests render the
// component and observe it.
// ---------------------------------------------------------------------------

const KABUL_TODAY = '2026-09-12';

function workspaceFixture() {
  const branch = { id: 'branch-1', name: 'Central Branch' };
  const borrower = { id: 'person-1', legal_name: 'Borrower One' };
  return {
    assets: [{ id: 'asset-1', code: 'ASSET-1', name: 'Projector', location: 'Room 1', lifecycle_state: 'in_service', originating_branch_id: 'branch-1' }],
    copies: [{ id: 'copy-1', code: 'COPY-1', title: 'Volume One', originating_branch_id: 'branch-1' }],
    issuances: [
      { id: 'issuance-open', copy_id: 'copy-1', borrower_person_id: 'person-1', issued_on: '2026-09-01', due_on: '2026-09-20', lifecycle_state: 'issued' },
      { id: 'issuance-closed', copy_id: 'copy-1', borrower_person_id: 'person-1', issued_on: '2026-08-01', due_on: '2026-08-20', returned_on: '2026-08-15', lifecycle_state: 'returned' },
    ],
    work_orders: [
      { id: 'work-requested', facility_note: 'HVAC requested', description: 'd', lifecycle_state: 'requested' },
      { id: 'work-approved', facility_note: 'HVAC approved', description: 'd', lifecycle_state: 'approved' },
      { id: 'work-progress', facility_note: 'HVAC progress', description: 'd', lifecycle_state: 'in_progress' },
      { id: 'work-completed', facility_note: 'HVAC completed', description: 'd', lifecycle_state: 'completed' },
      { id: 'work-cancelled', facility_note: 'HVAC cancelled', description: 'd', lifecycle_state: 'cancelled' },
    ],
    open_custodies: [{ id: 'custody-1', asset_id: 'asset-1', custodian_person_id: 'person-1', assigned_on: '2026-09-01' }],
    disposal_requests: [
      { id: 'disposal-requested', asset_id: 'asset-1', method: 'scrap', lifecycle_state: 'requested' },
      { id: 'disposal-approved', asset_id: 'asset-1', method: 'sale', lifecycle_state: 'approved' },
      { id: 'disposal-completed', asset_id: 'asset-1', method: 'sale', lifecycle_state: 'completed' },
      { id: 'disposal-withdrawn', asset_id: 'asset-1', method: 'donation', lifecycle_state: 'withdrawn' },
    ],
    disposals: [],
    book_branches: [branch],
    asset_branches: [branch],
    work_branches: [branch],
    borrowers: [borrower],
    custodians: [borrower],
  };
}

let bundlePromise = null;
function libraryBundle() {
  bundlePromise ??= build({
    stdin: {
      contents: `
        import { createRoot } from 'react-dom/client';
        import { LibraryApp } from './library';

        const api = window.__libraryApi;
        createRoot(document.getElementById('library-root'))
          .render(<LibraryApp getJson={api.getJson} postJson={api.postJson} csrfToken="test-token" />);
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

async function renderLibrary(fixture = workspaceFixture()) {
  const code = await libraryBundle();
  const dom = new JSDOM('<!doctype html><html><body><div id="library-root"></div></body></html>', {
    runScripts: 'outside-only',
    url: 'https://app.test/library',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  const posts = [];
  window.__libraryApi = {
    getJson: (path) => (path === '/calendar/today'
      ? Promise.resolve({ data: { gregorian: KABUL_TODAY } })
      : Promise.resolve(fixture)),
    postJson: (path, payload) => {
      posts.push({ path, payload });

      return Promise.resolve({ status: 'ok' });
    },
  };
  const confirmMessages = [];
  const promptMessages = [];
  window.confirm = (message) => {
    confirmMessages.push(message);

    return window.__confirmAnswer ?? true;
  };
  window.prompt = (message, fallback) => {
    promptMessages.push(message);

    return window.__promptAnswer !== undefined ? window.__promptAnswer : (fallback ?? 'prompted');
  };
  window.eval(code);
  await new Promise((resolveTick) => setTimeout(resolveTick, 200));

  const document = window.document;
  const rowsFor = (sectionHeading) => {
    const section = [...document.querySelectorAll('section')]
      .find((node) => (node.querySelector('h2')?.textContent || '').includes(sectionHeading));
    assert.ok(section, `section "${sectionHeading}" is rendered`);

    return [...section.querySelectorAll('tbody tr')];
  };
  const rowBy = (sectionHeading, needle) => {
    const row = rowsFor(sectionHeading).find((node) => (node.textContent || '').includes(needle));
    assert.ok(row, `row "${needle}" is rendered in "${sectionHeading}"`);

    return row;
  };
  const buttonsOf = (row) => [...row.querySelectorAll('button')].map((button) => (button.textContent || '').trim());
  const setInput = (element, value) => {
    const proto = element instanceof window.HTMLTextAreaElement ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(element, value);
    element.dispatchEvent(new window.Event('input', { bubbles: true }));
  };
  const formInput = (submitText, labelText) => {
    const form = [...document.querySelectorAll('form')]
      .find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(submitText));
    assert.ok(form, `form "${submitText}" is rendered`);
    const label = [...form.querySelectorAll('label')]
      .find((node) => (node.firstChild?.textContent || '').trim() === labelText);
    assert.ok(label, `label "${labelText}" is rendered in form "${submitText}"`);

    return label.querySelector('input, textarea, select');
  };

  return { window, document, posts, confirmMessages, promptMessages, rowsFor, rowBy, buttonsOf, setInput, formInput };
}

const tick = async () => new Promise((resolveTick) => setTimeout(resolveTick, 120));

test('the rendered console offers exactly the state-legal actions and counts', async () => {
  const ui = await renderLibrary();
  const { document, rowBy, buttonsOf } = ui;

  const metrics = [...document.querySelectorAll('.summary-grid .panel')].map((panel) => ({
    value: panel.querySelector('.metric')?.textContent,
    label: panel.querySelector('.metric-label')?.textContent,
  }));
  assert.deepEqual(metrics, [
    { value: '1', label: 'Book copies' },
    { value: '1', label: 'Open loans' },
    { value: '1', label: 'Assets' },
    { value: '1', label: 'Open custody' },
    { value: '2', label: 'Pending disposal' },
    { value: '3', label: 'Work orders' },
  ], 'pending disposal counts only requested/approved; work orders only non-terminal');

  assert.deepEqual(buttonsOf(rowBy('Approval and execution queue', 'disposal-requested')), ['Approve', 'Withdraw']);
  assert.deepEqual(buttonsOf(rowBy('Approval and execution queue', 'disposal-approved')), ['Execute']);
  assert.deepEqual(buttonsOf(rowBy('Approval and execution queue', 'disposal-completed')), []);
  assert.deepEqual(buttonsOf(rowBy('Approval and execution queue', 'disposal-withdrawn')), [], 'a withdrawn request is terminal and offers nothing');

  assert.deepEqual(buttonsOf(rowBy('Work orders with evidence', 'HVAC requested')), ['Approve', 'Cancel']);
  assert.deepEqual(buttonsOf(rowBy('Work orders with evidence', 'HVAC approved')), ['Start', 'Cancel']);
  assert.deepEqual(buttonsOf(rowBy('Work orders with evidence', 'HVAC progress')), ['Complete', 'Cancel']);
  assert.deepEqual(buttonsOf(rowBy('Work orders with evidence', 'HVAC completed')), []);
  assert.deepEqual(buttonsOf(rowBy('Work orders with evidence', 'HVAC cancelled')), []);

  const ledgerRow = [...document.querySelectorAll('tbody tr')].find((row) => (row.textContent || '').includes('issuance-open') || (row.textContent || '').includes('Issued'));
  assert.deepEqual([...ledgerRow.querySelectorAll('button')].map((button) => (button.textContent || '').trim()), ['Return', 'Report loss']);
});

test('the register-copy form posts the canonical payload and surfaces the notice', async () => {
  const ui = await renderLibrary();
  const { document, posts, setInput, formInput } = ui;

  setInput(formInput('Register copy', 'Copy code'), 'COPY-NEW');
  setInput(formInput('Register copy', 'Title'), 'New Volume');
  setInput(formInput('Register copy', 'Acquired on'), KABUL_TODAY);
  const branchSelect = formInput('Register copy', 'Owning branch');
  Object.getOwnPropertyDescriptor(ui.window.HTMLSelectElement.prototype, 'value').set.call(branchSelect, 'branch-1');
  branchSelect.dispatchEvent(new ui.window.Event('change', { bubbles: true }));

  [...document.querySelectorAll('form')]
    .find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes('Register copy'))
    .querySelector('button[type="submit"]').click();
  await tick();

  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/resources/books');
  // The payload object originates in the JSDOM realm; spread it so the strict
  // comparison checks structure, not cross-realm prototypes.
  assert.deepEqual({ ...posts[0].payload }, {
    code: 'COPY-NEW',
    title: 'New Volume',
    acquired_on: KABUL_TODAY,
    branch_id: 'branch-1',
  });
  assert.match(document.querySelector('.notice')?.textContent || '', /Book copy registered\./);
});

test('withdrawal demands an irreversible confirm and posts the canonical path', async () => {
  const ui = await renderLibrary();
  const { document, posts, confirmMessages, rowBy } = ui;

  // A refused confirm must not reach the API.
  ui.window.__confirmAnswer = false;
  [...rowBy('Approval and execution queue', 'disposal-requested').querySelectorAll('button')]
    .find((button) => (button.textContent || '').trim() === 'Withdraw').click();
  await tick();
  assert.equal(posts.length, 0, 'a cancelled confirm posts nothing');
  assert.match(confirmMessages[0], /Withdraw disposal request disposal-requested\?/);
  assert.match(confirmMessages[0], /This action is irreversible\./);

  ui.window.__confirmAnswer = true;
  [...rowBy('Approval and execution queue', 'disposal-requested').querySelectorAll('button')]
    .find((button) => (button.textContent || '').trim() === 'Withdraw').click();
  await tick();
  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/resources/disposals/disposal-requested/withdraw');
  assert.deepEqual({ ...posts[0].payload }, {});
  assert.match(document.querySelector('.notice')?.textContent || '', /Disposal request withdrawn\./);
});

test('issuing posts the selected borrower with the server calendar dates', async () => {
  const ui = await renderLibrary();
  const { posts, promptMessages } = ui;

  const borrowerSelect = [...ui.document.querySelectorAll('label')]
    .find((node) => (node.firstChild?.textContent || '').trim() === 'Borrower')
    .querySelector('select');
  Object.getOwnPropertyDescriptor(ui.window.HTMLSelectElement.prototype, 'value').set.call(borrowerSelect, 'person-1');
  borrowerSelect.dispatchEvent(new ui.window.Event('change', { bubbles: true }));

  ui.window.__promptAnswer = '2026-09-30';
  const issueButton = [...ui.document.querySelectorAll('tbody tr')]
    .find((row) => (row.textContent || '').includes('COPY-1'))
    .querySelectorAll('button')[0];
  issueButton.click();
  await tick();

  assert.equal(posts.length, 1);
  assert.equal(posts[0].path, '/resources/books/copy-1/issue');
  assert.deepEqual({ ...posts[0].payload }, {
    borrower_id: 'person-1',
    issued_on: KABUL_TODAY,
    due_on: '2026-09-30',
  });
  assert.ok(promptMessages.some((message) => message.includes('Due date')), 'the due date is asked for explicitly');
});

test('a rejected command surfaces the server message as an alert', async () => {
  const fixture = workspaceFixture();
  const code = await libraryBundle();
  const dom = new JSDOM('<!doctype html><html><body><div id="library-root"></div></body></html>', {
    runScripts: 'outside-only',
    url: 'https://app.test/library',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  window.__libraryApi = {
    getJson: (path) => (path === '/calendar/today'
      ? Promise.resolve({ data: { gregorian: KABUL_TODAY } })
      : Promise.resolve(fixture)),
    // The component checks `reason instanceof Error` inside the JSDOM realm,
    // exactly like production where api.ts throws in the same realm.
    postJson: () => Promise.reject(new window.Error('no active authority grants resources.dispose_approve in scope (resources.disposal_denied)')),
  };
  window.confirm = () => true;
  window.prompt = (message, fallback) => fallback ?? 'prompted';
  window.eval(code);
  await tick();
  await tick();

  const approve = [...window.document.querySelectorAll('tbody tr')]
    .find((row) => (row.textContent || '').includes('disposal-requested'))
    .querySelectorAll('button')[0];
  approve.click();
  await tick();

  assert.match(window.document.querySelector('.alert')?.textContent || '', /no active authority grants resources\.dispose_approve/);
  assert.equal(window.document.querySelector('.notice'), null, 'a rejection never reports success');
});
