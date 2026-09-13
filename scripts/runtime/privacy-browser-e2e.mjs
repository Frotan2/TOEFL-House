#!/usr/bin/env node
/**
 * Privacy & Consent browser E2E journey (puppeteer-core, no new deps).
 *
 * Drives the REAL server-rendered React UI in headless Chromium against the
 * disposable verification database. Four isolated browser sessions prove the
 * complete consent lifecycle plus the authority boundaries that matter most
 * for this domain:
 *
 *   officer (define_purpose+consent+disclose+export):
 *     - the workspace gate renders server-projected privacy metrics and states
 *       the authority boundary (evidence locators are never projected);
 *     - defines communication and marketing purposes as SEPARATE catalog rows;
 *     - records a lapsed-window consent and drives it draft → submitted →
 *       verified → active → expired → archived, asserting at every step that
 *       the action matrix is the SERVER's projection (a draft offers Submit
 *       only — no Verify/Activate/Revoke affordance exists at all);
 *     - records an open-ended consent, provokes the expiry business rejection
 *       ("a consent expires only after its recorded effective window has
 *       passed") and observes that the record does NOT move;
 *     - revokes it through the withdrawal panel and reads the append-only
 *       revocation evidence back;
 *     - records an immutable disclosure, exports one subject directly (receipt
 *       with disclosure + correlation evidence), and requests an
 *       organization-wide export — for which it is offered NO signature and NO
 *       execution, because the requester holds no approval capability;
 *     - a forged declared scope is rejected: the server re-resolves the
 *       subject's own provenance instead of trusting the browser;
 *     - reads the subject dossier: no current use authority, complete consent
 *       history with withdrawal evidence, disclosures and the closed chain;
 *   approver one (approve_bulk_export only):
 *     - sees the signature affordance but never execution; signs first (the
 *       request stays `requested`), is then never offered a second signature,
 *       and the server refuses the same actor's second signature outright;
 *   approver two (approve_bulk_export only):
 *     - signs second; the chain closes as `approved` with two distinct slots;
 *   subject (no capabilities at all):
 *     - the workspace projection is DENIED and renders fail-closed;
 *     - yet the subject may submit its OWN consent (no staff capability), a
 *       replay of that act is idempotent, a fresh repeat is refused by the
 *       lifecycle, and every officer-only act (define purpose, verify, sign an
 *       organization-wide export) is refused.
 *
 * Every observed Privacy mutation is asserted to be a POST on the canonical
 * /api/v1/privacy route family (no PUT/PATCH/DELETE anywhere), each session
 * must stay free of console/network errors outside its deliberately provoked
 * denial window, and confirm dialogs are answered from an explicit in-page
 * queue — never blanket-accepted — with their exact messages asserted.
 *
 * Environment (same contract as the other browser journeys):
 *   BASE_URL          default http://127.0.0.1:8999
 *   DB_DATABASE       default toefl_house_e2e (actor provisioning DB)
 *   CHROMIUM_PATH     default /usr/bin/chromium
 *   CHROMIUM_LIB_DIR  optional directory prepended to LD_LIBRARY_PATH
 *   PHP_BINARY        default php (used for the provisioning script)
 *
 * Usage:
 *   DB_DATABASE=toefl_house_e2e BASE_URL=http://127.0.0.1:8999 \
 *     node scripts/runtime/privacy-browser-e2e.mjs
 */
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8999';
const PASSWORD = process.env.PRIVACY_E2E_PASSWORD || 'employee-password-1';
const EXECUTABLE = process.env.CHROMIUM_PATH?.trim() || '/usr/bin/chromium';
const LIB_DIR = process.env.CHROMIUM_LIB_DIR?.trim();
const phpBin = process.env.PHP_BINARY?.trim() || 'php';

const results = [];
const record = (name, pass, detail = '') => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

const EVIDENCE_FILE = '/tmp/privacy-browser-e2e-summary.md';
try {
  fs.writeFileSync(EVIDENCE_FILE, '');
} catch {
  // Best-effort evidence; never mask the original failure.
}

function writeStepSummary(markdown) {
  for (const target of [process.env.GITHUB_STEP_SUMMARY, EVIDENCE_FILE]) {
    if (!target) continue;
    try {
      fs.appendFileSync(target, `${markdown}\n`);
    } catch {
      // The summary is best-effort evidence; never mask the original failure.
    }
  }
}

/** Every observed Privacy mutation MUST be a POST on a canonical route. */
const PRIVACY_MUTATION = /^\/api\/v1\/privacy\/(purposes|consents|disclosures|exports|exports\/bulk|consents\/[0-9a-f-]+\/(submit|verify|activate|expire|revoke|archive)|exports\/[0-9a-f-]+\/(approve|execute))$/;
const privacyMutations = [];
const privacyMutationViolations = [];

// Provision (idempotently) the journey actors through the same canonical
// access model the feature suite uses.
let provisionOutput;
try {
  provisionOutput = execFileSync(phpBin, ['scripts/runtime/privacy-browser-provision.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE || 'toefl_house_e2e' },
    encoding: 'utf8',
  });
} catch (error) {
  console.error('PRIVACY BROWSER E2E PROVISIONING FAILED');
  console.error(`status=${error.status ?? 'n/a'} stdout=${(error.stdout ?? '').slice(-2_000)}`);
  console.error(`stderr=${(error.stderr ?? '').slice(-2_000)}`);
  writeStepSummary(`## Privacy browser E2E\n\n**Provisioning failed** (exit ${error.status ?? 'n/a'})\n\n\`\`\`\n${(error.stderr ?? error.stdout ?? String(error)).slice(-3_000)}\n\`\`\`\n`);
  process.exit(1);
}
console.log(provisionOutput.split('\n').filter((line) => line.includes('privacy browser E2E')).join('\n'));

const stamp = `${Date.now()}`;
const PURPOSE_A = `e2e-privacy-enrollment-updates-${stamp}`;
const PURPOSE_B = `e2e-privacy-partner-marketing-${stamp}`;
const PURPOSE_C = `e2e-privacy-subject-request-${stamp}`;
const EVIDENCE_A = `evidence/privacy/e2e-signed-form-a-${stamp}`;
const EVIDENCE_B = `evidence/privacy/e2e-signed-form-b-${stamp}`;
const EVIDENCE_C = `evidence/privacy/e2e-subject-portal-c-${stamp}`;
const DISCLOSURE_RECIPIENT = `Ministry of Education e2e ${stamp}`;
const DISCLOSURE_PURPOSE = `statutory-reporting-${stamp}`;
const RELEASE_PURPOSE = `subject-data-request-${stamp}`;
const BULK_PURPOSE = `organization-wide-audit-${stamp}`;
const SUBJECT = 'Authority Fixture e2e-privacy-subject';
const LAPSED_FROM = '2020-01-01';
const LAPSED_TO = '2020-12-31';
const TODAY = new Date().toISOString().slice(0, 10);

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: LIB_DIR ? ['--no-sandbox', '--disable-dev-shm-usage', `--library-path=${LIB_DIR}`] : ['--no-sandbox', '--disable-dev-shm-usage'],
  env: LIB_DIR ? { ...process.env, LD_LIBRARY_PATH: `${LIB_DIR}:${process.env.LD_LIBRARY_PATH || ''}` } : undefined,
  headless: true,
  timeout: 90_000,
});

/** @type {puppeteer.BrowserContext[]} */
const contexts = [];

try {
  const health = await fetch(`${BASE}/health`);
  record('Health endpoint is reachable', health.ok, `HTTP ${health.status}`);
  if (!health.ok) throw new Error('server is not reachable');

  /**
   * One isolated cookie jar per operator; each logs in once. Dialogs are
   * answered from an explicit queue so every confirm the React layer raises
   * is deliberately scripted (and its exact message asserted) instead of
   * blanket-accepted.
   *
   * `denialsExpected` marks a session whose very first workspace read is a
   * deliberate 403 (the subject with no privacy authority): HTTP statuses are
   * recorded but tolerated there, while JS crashes are never tolerated.
   */
  async function session(username, { denialsExpected = false } = {}) {
    const context = await browser.createBrowserContext();
    contexts.push(context);
    const page = await context.newPage();
    await page.evaluateOnNewDocument(() => {
      window.__dialogQueue = [];
      window.__dialogLog = [];
      const nextAnswer = (message) => {
        window.__dialogLog.push(String(message));
        if (window.__dialogQueue.length === 0) return false;
        return window.__dialogQueue.shift() !== false;
      };
      window.confirm = (message) => nextAnswer(message);
      window.prompt = (message) => {
        if (window.__dialogQueue.length === 0) return null;
        const answer = window.__dialogQueue.shift();
        window.__dialogLog.push(`${String(message)} => ${answer === false ? 'CANCELLED' : String(answer)}`);
        return answer === false ? null : String(answer);
      };
    });
    page.setDefaultNavigationTimeout(60_000);
    const consoleErrors = [];
    const failedRequests = [];
    const deniedResponses = [];
    let expectingDenial = denialsExpected;

    page.on('console', (message) => {
      if (message.type() === 'error' && !expectingDenial) consoleErrors.push(message.text().slice(0, 500));
    });
    page.on('pageerror', (error) => {
      if (!expectingDenial) consoleErrors.push(String(error).slice(0, 500));
    });
    page.on('requestfailed', (request) => {
      if (!expectingDenial) failedRequests.push(`${request.method()} ${request.url().slice(0, 200)} ${(request.failure()?.errorText || '')}`);
    });
    page.on('response', (response) => {
      const url = response.url();
      if (response.status() >= 400) {
        deniedResponses.push(`HTTP ${response.status()} ${url.slice(0, 220)}`);
        if (!expectingDenial) consoleErrors.push(`HTTP ${response.status()} ${url.slice(0, 220)}`);
      }
    });
    page.on('request', (request) => {
      const url = request.url();
      if (!url.includes('/api/v1/privacy')) return;
      const method = request.method();
      if (method === 'GET') return;
      const pathname = new URL(url).pathname;
      if (method === 'POST' && PRIVACY_MUTATION.test(pathname)) {
        privacyMutations.push(`POST ${pathname}`);
      } else {
        privacyMutationViolations.push(`${method} ${pathname}`);
      }
    });

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name="username"]', username);
    await page.type('input[name="password"]', PASSWORD);
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
    ]);
    await page.goto(`${BASE}/privacy`, { waitUntil: 'networkidle2' });
    if (page.url().includes('/login')) throw new Error(`${username}: privacy E2E login failed`);
    // The workspace heading renders in both the loaded and the fail-closed
    // states; waitIdle() below distinguishes them.
    await page.waitForSelector('#privacy-title', { timeout: 60_000 });
    return {
      context,
      page,
      consoleErrors,
      failedRequests,
      deniedResponses,
      expectDialogs: (...answers) => page.evaluate((queue) => { window.__dialogQueue = queue; }, answers),
      dialogLog: () => page.evaluate(() => window.__dialogLog),
      expectDenial: (value) => { expectingDenial = value; },
      expectingDenial: (operation) => {
        expectingDenial = true;
        return Promise.resolve()
          .then(operation)
          .finally(() => { expectingDenial = denialsExpected; });
      },
    };
  }

  /** Click an enabled page button by exact label (polls until actionable). */
  const clickPageButton = (page, label) => page.waitForFunction(
    (text) => {
      const button = Array.from(document.querySelectorAll('button')).find((element) => element.textContent.trim() === text);
      if (!button || button.disabled) return false;
      button.click();
      return true;
    },
    { timeout: 60_000, polling: 100 },
    label,
  );

  /**
   * Idle = workspace rendered, the initial-load placeholder gone, and no
   * command in flight (busy buttons carry an ellipsis label, e.g. "Saving…").
   */
  const waitIdle = (page) => page.waitForFunction(
    () => document.querySelector('main#workspace-main') !== null
      && !document.body.textContent.includes('Loading server-authorized')
      && Array.from(document.querySelectorAll('button')).every((button) => !button.textContent.trim().endsWith('…')),
    { timeout: 60_000 },
  );

  /** Wait for the sticky notice whose text contains the needle. */
  const waitForNotice = (page, needle) => page.waitForFunction(
    (text) => Array.from(document.querySelectorAll('.notice')).some((element) => element.textContent.includes(text)),
    { timeout: 60_000 },
    needle,
  );

  /** Wait for a rendered alert carrying the server's own message. */
  const waitForAlert = (page, needle) => page.waitForFunction(
    (text) => Array.from(document.querySelectorAll('.alert')).some((element) => element.textContent.includes(text)),
    { timeout: 60_000 },
    needle,
  );

  const openTab = (page, label) => page.evaluate((text) => {
    const tab = Array.from(document.querySelectorAll('[role="tab"]')).find((element) => element.textContent.trim() === text);
    if (!tab) throw new Error(`tab not found: ${text}`);
    tab.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  }, label);

  /**
   * The registry table that belongs to a section heading. Tables are matched
   * as DIRECT children of the section so a nested evidence block (the
   * revocations table inside the consent register) can never be mistaken for
   * the registry itself.
   */
  const sectionTable = (heading) => `
    const headingElement = Array.from(document.querySelectorAll('h2'))
      .find((element) => element.textContent.includes(${JSON.stringify(heading)}));
    const section = headingElement?.closest('section');
    const table = section ? Array.from(section.querySelectorAll(':scope > .table-wrap > table'))[0] : null;
  `;

  /** Click a non-disabled button with the exact label inside a registry row. */
  const clickRowButton = (page, heading, needle, label) => page.waitForFunction(
    new Function('options', `
      const { needle, text } = options;
      ${sectionTable(heading)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      if (!row) return false;
      const button = Array.from(row.querySelectorAll('button')).find((element) => element.textContent.trim() === text);
      if (button && !button.disabled) { button.click(); return true; }
      return false;
    `),
    { timeout: 60_000, polling: 100 },
    { needle, text: label },
  );

  /** Exact button labels offered by a registry row (the server's affordances). */
  const rowButtons = (page, heading, needle) => page.evaluate(
    new Function('needle', `
      ${sectionTable(heading)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      return Array.from(row?.querySelectorAll('.privacy-actions button') || []).map((button) => button.textContent.trim());
    `),
    needle,
  );

  /** A single registry cell's text (1-based column index). */
  const rowCell = (page, heading, needle, column) => page.evaluate(
    new Function('needle', 'column', `
      ${sectionTable(heading)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      return row?.querySelector(\`td:nth-child(\${column})\`)?.textContent.trim() ?? null;
    `),
    needle,
    column,
  );

  /**
   * Wait for a row's lifecycle chip to reach the expected state. Commands
   * refresh the workspace SILENTLY (load(false) shows no busy indicator), so
   * chip reads must poll until the reloaded projection arrives instead of
   * reading the stale pre-command view once.
   */
  const waitForChip = (page, heading, needle, chip, column = 4) => page.waitForFunction(
    new Function('options', `
      const { needle, expected, index } = options;
      ${sectionTable(heading)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      return row?.querySelector(\`td:nth-child(\${index}) .status-chip\`)?.textContent.trim() === expected;
    `),
    { timeout: 60_000, polling: 100 },
    { needle, expected: chip, index: column },
  );

  /**
   * Wait until a registry row exists in the REFRESHED projection. A command
   * that creates a row refreshes the workspace silently (`load(false)` paints
   * no busy state), so the row is absent until that refresh lands: every read
   * after a creating command gates here instead of racing the paint.
   */
  const waitForRow = (page, heading, needle) => page.waitForFunction(
    new Function('options', `
      const { needle } = options;
      ${sectionTable(heading)}
      return Array.from(table?.querySelectorAll('tbody tr') || [])
        .some((element) => element.textContent.includes(needle));
    `),
    { timeout: 60_000, polling: 100 },
    { needle },
  );

  /**
   * Wait until one registry cell no longer starts with `rejected`, then return
   * its text. Slot writes (an approver signature) are invisible until the
   * silent refresh lands; this is the positive signal that the projection the
   * neighbouring cells are read from is the post-command one.
   */
  const waitForRowCellSettled = (page, heading, needle, column, rejected) => page.waitForFunction(
    new Function('options', `
      const { needle, index, rejected } = options;
      ${sectionTable(heading)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      const text = row?.querySelector(\`td:nth-child(\${index})\`)?.textContent.trim() ?? null;
      return text !== null && !text.startsWith(rejected) ? text : false;
    `),
    { timeout: 60_000, polling: 100 },
    { needle, index: column, rejected },
  ).then((handle) => handle.jsonValue());

  /**
   * Fill a labelled input/textarea by its visible label text (polls until
   * mounted). The form is identified either by a CSS selector or by an anchor
   * label unique to that form — the release tab mounts three forms that all
   * contain a 'Subject' label bound to DIFFERENT component state.
   */
  const setLabeledInput = (page, form, label, value) => page.waitForFunction(
    new Function('options', `
      const { selector, anchor, labelText, next } = options;
      const forms = Array.from(document.querySelectorAll(selector || 'form'));
      const root = selector
        ? forms[0]
        : forms.find((element) => Array.from(element.querySelectorAll('label'))
          .some((candidate) => (candidate.firstChild?.textContent || '').trim() === anchor));
      if (!root) return false;
      // A <label> wrapping its control keeps the label text as its first text
      // node; match on that so 'Purpose' never matches 'Purpose of export'.
      const labelElement = Array.from(root.querySelectorAll('label'))
        .find((element) => (element.firstChild?.textContent || '').trim() === labelText);
      const input = labelElement?.querySelector('input, textarea, select');
      if (!input) return false;
      const proto = input.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
      Object.getOwnPropertyDescriptor(proto, 'value').set.call(input, next);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `),
    { timeout: 60_000, polling: 100 },
    { selector: form.selector ?? null, anchor: form.anchor ?? null, labelText: label, next: value },
  );

  /** Choose a select option by visible option text (polls until mounted). */
  const selectOption = async (page, form, label, optionText) => {
    const handle = await page.waitForFunction(
      new Function('options', `
        const { selector, anchor, labelText, needle } = options;
        const forms = Array.from(document.querySelectorAll(selector || 'form'));
        const root = selector
          ? forms[0]
          : forms.find((element) => Array.from(element.querySelectorAll('label'))
            .some((candidate) => (candidate.firstChild?.textContent || '').trim() === anchor));
        if (!root) return false;
        const labelElement = Array.from(root.querySelectorAll('label'))
          .find((element) => (element.firstChild?.textContent || '').trim() === labelText);
        const input = labelElement?.querySelector('select');
        if (!input) return false;
        const option = Array.from(input.options).find((element) => element.textContent.includes(needle));
        if (!option) return false;
        Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value').set.call(input, option.value);
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return option.textContent.trim();
      `),
      { timeout: 60_000, polling: 100 },
      { selector: form.selector ?? null, anchor: form.anchor ?? null, labelText: label, needle: optionText },
    );
    return handle.jsonValue();
  };

  /**
   * A canonical same-origin API call from inside the page: the session cookie,
   * the CSRF meta token and an explicit Idempotency-Key travel exactly as the
   * React transport sends them. Used for the boundaries a compliant UI cannot
   * express (a forged scope, a second signature from the same approver, the
   * subject's own acts while its workspace projection is denied).
   */
  const api = (page, apiPath, { body = null, key = null, method = 'POST' } = {}) => page.evaluate(
    async (options) => {
      const { path: target, payload, idempotencyKey, verb } = options;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
      const response = await fetch(`/api/v1${target}`, {
        method: verb,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          ...(payload ? { 'Content-Type': 'application/json' } : {}),
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
        },
        ...(payload ? { body: JSON.stringify(payload) } : {}),
      });
      const text = await response.text();
      let parsed = null;
      try {
        parsed = JSON.parse(text);
      } catch {
        parsed = { raw: text.slice(0, 300) };
      }
      return { status: response.status, payload: parsed };
    },
    { path: apiPath, payload: body, idempotencyKey: key, verb: method },
  );

  const CONSENTS = 'Consents in your authorized scope';
  const PURPOSES = 'Defined purposes of personal-data use';
  const DISCLOSURES = 'Record a disclosure';
  const EXPORTS = 'Organization-wide export requests';
  const PURPOSE_FORM = { anchor: 'Purpose name' };
  const CONSENT_FORM = { anchor: 'Evidence reference' };
  const DISCLOSURE_FORM = { anchor: 'Disclosed category' };
  const RELEASE_FORM = { anchor: 'Purpose of export' };
  // Both the purpose form (record tab) and the bulk form (release tab) carry
  // .privacy-inset, so the selector is scoped to the mounted release panel.
  const BULK_FORM = { selector: '#privacy-panel-release form.privacy-inset' };
  const REVOCATION_FORM = { anchor: 'Withdrawal scope' };

  // ── Officer session: workspace gate, purpose catalog, consent lifecycle ──
  const officer = await session('e2e-privacy-officer');
  const { page: officerPage } = officer;
  await waitIdle(officerPage);

  const officerMetrics = await officerPage.evaluate(() => Array.from(document.querySelectorAll('.summary-grid .panel')).map((panel) => panel.textContent.trim()));
  record('Officer workspace renders server-projected privacy metrics', officerMetrics.length === 4, officerMetrics.join(' | '));

  const boundary = await officerPage.evaluate(() => document.querySelector('.privacy-authority')?.textContent ?? '');
  const scopeBadge = await officerPage.evaluate(() => document.querySelector('.scope-badge')?.textContent.trim() ?? '');
  record(
    'The workspace states the authority boundary and the authorized branch scope',
    boundary.includes('server-projected affordances') && /\d+ authorized branch/.test(scopeBadge),
    `${scopeBadge} · ${boundary.slice(0, 140)}`,
  );

  await openTab(officerPage, 'Purpose & consent');
  await waitIdle(officerPage);

  // Communication and marketing are separate catalog definitions, never one
  // conflated "contact the person" purpose.
  await setLabeledInput(officerPage, PURPOSE_FORM, 'Purpose name', PURPOSE_A);
  await selectOption(officerPage, PURPOSE_FORM, 'Channel', 'Email');
  await selectOption(officerPage, PURPOSE_FORM, 'Category', 'Communication');
  await clickPageButton(officerPage, 'Define purpose');
  await waitForNotice(officerPage, 'Consent purpose defined and audit-recorded.');
  await waitIdle(officerPage);

  await setLabeledInput(officerPage, PURPOSE_FORM, 'Purpose name', PURPOSE_B);
  await selectOption(officerPage, PURPOSE_FORM, 'Channel', 'SMS');
  await selectOption(officerPage, PURPOSE_FORM, 'Category', 'Marketing');
  await clickPageButton(officerPage, 'Define purpose');
  await waitForNotice(officerPage, 'Consent purpose defined and audit-recorded.');
  await waitIdle(officerPage);

  await setLabeledInput(officerPage, PURPOSE_FORM, 'Purpose name', PURPOSE_C);
  await selectOption(officerPage, PURPOSE_FORM, 'Channel', 'Phone');
  await selectOption(officerPage, PURPOSE_FORM, 'Category', 'Communication');
  await clickPageButton(officerPage, 'Define purpose');
  await waitForNotice(officerPage, 'Consent purpose defined and audit-recorded.');
  await waitIdle(officerPage);

  // Each definition refreshes the catalog silently: gate the read on the last
  // row landing so the category assertions see the settled projection.
  await waitForRow(officerPage, PURPOSES, PURPOSE_C);
  const purposeRows = await officerPage.evaluate(
    new Function('needles', `
      ${sectionTable(PURPOSES)}
      const rows = Array.from(table?.querySelectorAll('tbody tr') || []).map((row) => row.textContent.trim());
      return needles.map((needle) => rows.find((row) => row.includes(needle)) ?? null);
    `),
    [PURPOSE_A, PURPOSE_B, PURPOSE_C],
  );
  record(
    'Communication and marketing purposes are separate catalog rows with their own channel and category',
    purposeRows.every((row) => row !== null)
      && purposeRows[0].includes('Communication') && purposeRows[1].includes('Marketing'),
    purposeRows.map((row) => (row ?? 'missing').slice(0, 90)).join(' || '),
  );

  // Consent A: a window that has already lapsed, so expiry is legal later.
  await selectOption(officerPage, CONSENT_FORM, 'Subject', SUBJECT);
  await selectOption(officerPage, CONSENT_FORM, 'Purpose', PURPOSE_A);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Effective from', LAPSED_FROM);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Effective to', LAPSED_TO);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Evidence reference', EVIDENCE_A);
  await clickPageButton(officerPage, 'Record consent draft');
  await waitForNotice(officerPage, 'Consent recorded as a draft with its evidence');
  await waitIdle(officerPage);

  await openTab(officerPage, 'Consent register');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Draft');
  const draftButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_A);
  record(
    'A draft consent offers Submit only — the server never projects Verify, Activate, Expire, Revoke or Archive on a draft',
    draftButtons.join(',') === 'Dossier,Submit',
    draftButtons.join(', '),
  );

  await clickRowButton(officerPage, CONSENTS, PURPOSE_A, 'Submit');
  await waitForNotice(officerPage, 'Consent submitted for verification.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Submitted');
  const submittedButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_A);
  record('A submitted consent offers Verify only', submittedButtons.join(',') === 'Dossier,Verify', submittedButtons.join(', '));

  await clickRowButton(officerPage, CONSENTS, PURPOSE_A, 'Verify');
  await waitForNotice(officerPage, 'Consent verified against its recorded evidence.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Verified');
  const verifiedButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_A);
  record('A verified consent offers Activate only', verifiedButtons.join(',') === 'Dossier,Activate', verifiedButtons.join(', '));

  await officer.expectDialogs(true);
  await clickRowButton(officerPage, CONSENTS, PURPOSE_A, 'Activate');
  await waitForNotice(officerPage, 'Consent active; it now carries current use authority.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Active');
  const activeButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_A);
  const activateDialogs = await officer.dialogLog();
  record(
    'Activation is behind an explicit confirm that names it as the source of use authority',
    activeButtons.join(',') === 'Dossier,Expire,Revoke,Archive'
      && activateDialogs.some((message) => message.includes('Activation is what makes it current use authority')),
    `${activeButtons.join(', ')} · ${activateDialogs.slice(-1)[0]?.slice(0, 90) ?? 'no dialog'}`,
  );

  await officer.expectDialogs(true);
  await clickRowButton(officerPage, CONSENTS, PURPOSE_A, 'Expire');
  await waitForNotice(officerPage, 'Consent expired; the record and its evidence are retained.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Expired');
  record('A consent whose recorded window has lapsed is closed as expired', true, `${LAPSED_FROM} → ${LAPSED_TO}`);

  await officer.expectDialogs(true);
  await clickRowButton(officerPage, CONSENTS, PURPOSE_A, 'Archive');
  await waitForNotice(officerPage, 'Consent archived; consent history is retained.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_A, 'Archived');
  const archivedButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_A);
  record(
    'An archived consent is terminal: it offers no mutation affordance at all',
    archivedButtons.join(',') === 'Dossier',
    archivedButtons.join(', '),
  );

  // Consent B: open-ended, so it can be activated, revoked — and never expired.
  await openTab(officerPage, 'Purpose & consent');
  await selectOption(officerPage, CONSENT_FORM, 'Subject', SUBJECT);
  await selectOption(officerPage, CONSENT_FORM, 'Purpose', PURPOSE_B);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Effective from', TODAY);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Evidence reference', EVIDENCE_B);
  await clickPageButton(officerPage, 'Record consent draft');
  await waitForNotice(officerPage, 'Consent recorded as a draft with its evidence');
  await waitIdle(officerPage);

  await openTab(officerPage, 'Consent register');
  await waitForChip(officerPage, CONSENTS, PURPOSE_B, 'Draft');
  await clickRowButton(officerPage, CONSENTS, PURPOSE_B, 'Submit');
  await waitForNotice(officerPage, 'Consent submitted for verification.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_B, 'Submitted');
  await clickRowButton(officerPage, CONSENTS, PURPOSE_B, 'Verify');
  await waitForNotice(officerPage, 'Consent verified against its recorded evidence.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_B, 'Verified');
  await officer.expectDialogs(true);
  await clickRowButton(officerPage, CONSENTS, PURPOSE_B, 'Activate');
  await waitForNotice(officerPage, 'Consent active; it now carries current use authority.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_B, 'Active');
  record('An open-ended consent is driven to active use authority', true, `${TODAY} → open-ended`);

  // Expiry is the passage of the window, not a second revocation: an
  // open-ended consent has nothing to lapse and the server says so.
  await officer.expectDialogs(true);
  await officer.expectDenial(true);
  await clickRowButton(officerPage, CONSENTS, PURPOSE_B, 'Expire');
  await waitForAlert(officerPage, 'privacy.consent_expiry_not_due');
  await officer.expectDenial(false);
  const stillActive = await rowCell(officerPage, CONSENTS, PURPOSE_B, 4);
  record(
    'An open-ended consent cannot be expired: the server rejects it as a business rule and the record does not move',
    (stillActive ?? '').includes('Active'),
    `${stillActive ?? 'no chip'} · ${(await officerPage.evaluate(() => document.querySelector('.alert')?.textContent ?? '')).slice(0, 160)}`,
  );

  await clickRowButton(officerPage, CONSENTS, PURPOSE_B, 'Revoke');
  await officerPage.waitForFunction(
    () => document.querySelector('#privacy-command-title')?.textContent.includes('Revoke consent'),
    { timeout: 60_000 },
  );
  await setLabeledInput(officerPage, REVOCATION_FORM, 'Withdrawal scope', 'all-channels');
  await setLabeledInput(officerPage, REVOCATION_FORM, 'Effect', 'immediate-cessation');
  await clickPageButton(officerPage, 'Record revocation');
  await waitForNotice(officerPage, 'Consent revoked; the withdrawal is recorded as append-only evidence.');
  await waitForChip(officerPage, CONSENTS, PURPOSE_B, 'Revoked');
  const revokedButtons = await rowButtons(officerPage, CONSENTS, PURPOSE_B);
  const revocationEvidence = await officerPage.evaluate(() => document.querySelector('.privacy-revocations')?.textContent ?? '');
  record(
    'Revocation stops future use and leaves append-only withdrawal evidence behind',
    revokedButtons.join(',') === 'Dossier,Archive'
      && revocationEvidence.includes('all-channels') && revocationEvidence.includes('immediate-cessation'),
    `${revokedButtons.join(', ')} · ${revocationEvidence.slice(0, 140)}`,
  );

  // Consent C: left a draft so the subject can perform its own act on it.
  await openTab(officerPage, 'Purpose & consent');
  await selectOption(officerPage, CONSENT_FORM, 'Subject', SUBJECT);
  await selectOption(officerPage, CONSENT_FORM, 'Purpose', PURPOSE_C);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Effective from', TODAY);
  await setLabeledInput(officerPage, CONSENT_FORM, 'Evidence reference', EVIDENCE_C);
  await clickPageButton(officerPage, 'Record consent draft');
  await waitForNotice(officerPage, 'Consent recorded as a draft with its evidence');
  await waitIdle(officerPage);

  // The subject's own act needs the consent id, which the UI deliberately
  // renders only in compacted form: read it from the canonical projection.
  const workspace = await api(officerPage, '/privacy/workspace', { method: 'GET' });
  const purposeC = (workspace.payload?.data?.purposes ?? []).find((row) => row.name === PURPOSE_C);
  const drafts = (workspace.payload?.data?.consents ?? [])
    .filter((row) => row.lifecycle_state === 'draft' && purposeC && row.purpose_id === purposeC.id);
  if (drafts.length !== 1) throw new Error(`expected exactly one draft consent for ${PURPOSE_C}, found ${drafts.length}`);
  const subjectConsentId = drafts[0].id;
  record(
    'Consent evidence references are write-only: no evidence locator is projected to the workspace',
    !(await officerPage.evaluate(() => document.querySelector('main#workspace-main')?.textContent ?? '')).includes('evidence/privacy')
      && !(await officerPage.evaluate(() => document.body.innerHTML)).includes(EVIDENCE_A),
    `${EVIDENCE_A.slice(0, 40)}… never rendered`,
  );

  // ── Officer session: disclosure evidence and the two release paths ───────
  await openTab(officerPage, 'Disclosure & export');
  await waitIdle(officerPage);
  await selectOption(officerPage, DISCLOSURE_FORM, 'Subject', SUBJECT);
  await setLabeledInput(officerPage, DISCLOSURE_FORM, 'Recipient', DISCLOSURE_RECIPIENT);
  await setLabeledInput(officerPage, DISCLOSURE_FORM, 'Purpose of release', DISCLOSURE_PURPOSE);
  await setLabeledInput(officerPage, DISCLOSURE_FORM, 'Disclosed category', 'academic-record');
  await selectOption(officerPage, DISCLOSURE_FORM, 'Declared scope', 'This subject only');
  await clickPageButton(officerPage, 'Record disclosure');
  await waitForNotice(officerPage, 'Disclosure recorded as immutable release evidence.');
  await waitIdle(officerPage);
  await waitForRow(officerPage, DISCLOSURES, DISCLOSURE_RECIPIENT);
  const disclosureRow = await rowCell(officerPage, DISCLOSURES, DISCLOSURE_RECIPIENT, 2);
  record(
    'A disclosure is recorded as immutable release evidence with its recipient, purpose and declared scope',
    disclosureRow === DISCLOSURE_RECIPIENT,
    `${disclosureRow ?? 'missing'} · ${DISCLOSURE_PURPOSE}`,
  );

  // A declared scope the subject does not belong to is a forged scope: the
  // server re-resolves provenance instead of trusting the browser payload.
  const subjectId = (workspace.payload?.data?.people ?? []).find((person) => person.legal_name === SUBJECT)?.id ?? null;
  if (!subjectId) throw new Error('the consent subject is not visible in the officer scope');
  const forgedScope = await officer.expectingDenial(() => api(officerPage, '/privacy/exports', {
    body: { subject_person_id: subjectId, purpose: `${RELEASE_PURPOSE}-forged`, scope_type: 'branch', scope_id: 'e2e-privacy-forged-branch' },
    key: `privacy-e2e-forged-scope-${stamp}`,
  }));
  record(
    'A forged declared scope is rejected: the server re-resolves the subject provenance (409)',
    forgedScope.status === 409,
    `HTTP ${forgedScope.status} ${JSON.stringify(forgedScope.payload).slice(0, 160)}`,
  );

  await selectOption(officerPage, RELEASE_FORM, 'Subject', SUBJECT);
  await setLabeledInput(officerPage, RELEASE_FORM, 'Purpose of export', RELEASE_PURPOSE);
  await selectOption(officerPage, RELEASE_FORM, 'Declared scope', 'This subject only');
  await clickPageButton(officerPage, 'Export subject data');
  await waitForNotice(officerPage, 'Subject data exported; the disclosure is recorded as the evidence of the release.');
  await officerPage.waitForFunction(
    () => document.querySelector('#privacy-receipt-title')?.textContent.includes('Export recorded'),
    { timeout: 60_000 },
  );
  const receipt = await officerPage.evaluate(() => document.querySelector('.privacy-receipt')?.textContent ?? '');
  record(
    'A direct subject export releases the dataset and renders its disclosure and correlation evidence',
    receipt.includes(RELEASE_PURPOSE) && receipt.includes('Disclosure evidence') && receipt.includes('Audit correlation')
      && receipt.includes('The exported dataset is not rendered in the browser'),
    receipt.slice(0, 220),
  );
  await clickPageButton(officerPage, 'Close receipt');

  await setLabeledInput(officerPage, BULK_FORM, 'Purpose', BULK_PURPOSE);
  await selectOption(officerPage, BULK_FORM, 'Subject', SUBJECT);
  await clickPageButton(officerPage, 'Request organization-wide export');
  await waitForNotice(officerPage, 'Organization-wide export requested');
  await waitIdle(officerPage);
  await waitForChip(officerPage, EXPORTS, BULK_PURPOSE, 'Requested');
  const requesterActions = await officerPage.evaluate(
    new Function('needle', `
      ${sectionTable(EXPORTS)}
      const row = Array.from(table?.querySelectorAll('tbody tr') || [])
        .find((element) => element.textContent.includes(needle));
      return row?.querySelector('td:nth-child(5)')?.textContent.trim() ?? null;
    `),
    BULK_PURPOSE,
  );
  record(
    'The requester holds no approval capability: it is offered neither a signature nor execution',
    (await rowButtons(officerPage, EXPORTS, BULK_PURPOSE)).length === 0
      && (requesterActions ?? '').includes('No action available to you'),
    requesterActions ?? 'no actions cell',
  );

  const chain = await api(officerPage, '/privacy/workspace', { method: 'GET' });
  const exportRequest = (chain.payload?.data?.export_requests ?? []).find((row) => row.purpose === BULK_PURPOSE);
  if (!exportRequest) throw new Error('the organization-wide export request is not visible to its requester');

  // ── Approver one: first signature, then the separation-of-duties wall ────
  const approverOne = await session('e2e-privacy-approver-one');
  const { page: onePage } = approverOne;
  await waitIdle(onePage);
  await openTab(onePage, 'Disclosure & export');
  await waitIdle(onePage);
  await waitForChip(onePage, EXPORTS, BULK_PURPOSE, 'Requested');
  const oneButtons = await rowButtons(onePage, EXPORTS, BULK_PURPOSE);
  const exportAlert = await onePage.evaluate(() => Array.from(document.querySelectorAll('#privacy-panel-release .alert')).map((element) => element.textContent.trim()).join(' | '));
  record(
    'An approver sees the signature affordance, never execution, and is told plainly it cannot export',
    oneButtons.join(',') === 'Sign approval' && exportAlert.includes('does not include subject-data exports'),
    `${oneButtons.join(', ')} · ${exportAlert.slice(0, 120)}`,
  );

  await approverOne.expectDialogs(true);
  await clickRowButton(approverOne.page, EXPORTS, BULK_PURPOSE, 'Sign approval');
  await waitForNotice(onePage, 'First approval signed; a distinct second approver must sign before execution.');
  await waitIdle(onePage);
  // The signature fills an approver slot; wait for that slot to appear in the
  // refreshed projection before reading the chain cells or the affordances.
  const signatures = await waitForRowCellSettled(onePage, EXPORTS, BULK_PURPOSE, 3, '—');
  const afterFirst = await rowCell(onePage, EXPORTS, BULK_PURPOSE, 4);
  const signDialogs = await approverOne.dialogLog();
  record(
    'The first signature fills one approver slot and leaves the request `requested`',
    (afterFirst ?? '').includes('Requested') && !(signatures ?? '').startsWith('—')
      && signDialogs.some((message) => message.includes('cannot be withdrawn')),
    `${afterFirst} · signatures ${signatures}`,
  );

  await waitIdle(onePage);
  const oneButtonsAfter = await rowButtons(onePage, EXPORTS, BULK_PURPOSE);
  record(
    'An approver who signed first is never offered the second signature',
    oneButtonsAfter.length === 0,
    oneButtonsAfter.length === 0 ? 'no signature affordance projected' : oneButtonsAfter.join(', '),
  );

  const sameActor = await approverOne.expectingDenial(() => api(onePage, `/privacy/exports/${exportRequest.id}/approve`, {
    key: `privacy-e2e-single-actor-${stamp}`,
  }));
  record(
    'The server refuses the same actor as both approvers: organization-wide exports require two distinct approvers',
    sameActor.status === 403 && JSON.stringify(sameActor.payload).includes('privacy.bulk_export_single_actor'),
    `HTTP ${sameActor.status} ${JSON.stringify(sameActor.payload).slice(0, 180)}`,
  );

  // ── Approver two: the distinct second signature closes the chain ─────────
  const approverTwo = await session('e2e-privacy-approver-two');
  const { page: twoPage } = approverTwo;
  await waitIdle(twoPage);
  await openTab(twoPage, 'Disclosure & export');
  await waitForChip(twoPage, EXPORTS, BULK_PURPOSE, 'Requested');
  await approverTwo.expectDialogs(true);
  await clickRowButton(approverTwo.page, EXPORTS, BULK_PURPOSE, 'Sign approval');
  await waitForNotice(twoPage, 'Second approval signed; the request is approved for execution.');
  await waitForChip(twoPage, EXPORTS, BULK_PURPOSE, 'Approved');
  const twoSignatures = await rowCell(twoPage, EXPORTS, BULK_PURPOSE, 3);
  const twoButtons = await rowButtons(twoPage, EXPORTS, BULK_PURPOSE);
  record(
    'A distinct second approver approves the chain, and an approver is never offered execution',
    (twoSignatures ?? '').split('/').filter((slot) => !slot.includes('—')).length === 2 && twoButtons.length === 0,
    `signatures ${twoSignatures} · buttons ${twoButtons.join(', ') || 'none'}`,
  );

  // ── Officer session: execution, closure and the subject dossier ──────────
  await clickPageButton(officerPage, 'Refresh facts');
  await waitForChip(officerPage, EXPORTS, BULK_PURPOSE, 'Approved');
  const executeButtons = await rowButtons(officerPage, EXPORTS, BULK_PURPOSE);
  record('Execution is offered to the exporter only once the chain is approved', executeButtons.join(',') === 'Execute export', executeButtons.join(', '));

  await officer.expectDialogs(true);
  await clickRowButton(officerPage, EXPORTS, BULK_PURPOSE, 'Execute export');
  await waitForNotice(officerPage, 'Export executed; the disclosure is recorded as the evidence of the release.');
  await waitForChip(officerPage, EXPORTS, BULK_PURPOSE, 'Exported');
  const executedActions = await rowCell(officerPage, EXPORTS, BULK_PURPOSE, 5);
  const executeDialogs = await officer.dialogLog();
  record(
    'An approved request executes once behind an explicit release confirmation and is then closed',
    (executedActions ?? '').includes('No action available to you')
      && executeDialogs.some((message) => message.includes('This releases personal data and records an immutable disclosure')),
    executedActions ?? 'no actions cell',
  );

  const replay = await officer.expectingDenial(() => api(officerPage, `/privacy/exports/${exportRequest.id}/execute`, {
    key: `privacy-e2e-replay-execute-${stamp}`,
  }));
  record(
    'An executed request is closed: re-execution is refused (409)',
    replay.status === 409,
    `HTTP ${replay.status} ${JSON.stringify(replay.payload).slice(0, 180)}`,
  );

  await openTab(officerPage, 'Subject dossiers');
  await waitIdle(officerPage);
  await clickRowButton(officerPage, 'People in your authorized scope', SUBJECT, 'Open dossier');
  await officerPage.waitForFunction(
    () => document.querySelector('#privacy-dossier-title')?.textContent.includes('e2e-privacy-subject'),
    { timeout: 60_000 },
  );
  await officerPage.waitForFunction(
    () => !(document.querySelector('.privacy-dossier')?.textContent ?? '').includes('Loading the subject dossier'),
    { timeout: 60_000 },
  );
  const dossier = await officerPage.evaluate(() => document.querySelector('.privacy-dossier')?.textContent ?? '');
  record(
    'The subject dossier is a separately authorized read: no current use authority, complete history, disclosures and the closed export chain',
    dossier.includes('No consent carries current use authority for this subject on this day.')
      && [PURPOSE_A, PURPOSE_B, PURPOSE_C].every((needle) => dossier.includes(needle))
      && dossier.includes('Withdrawn by') && dossier.includes('all-channels / immediate-cessation')
      && dossier.includes(DISCLOSURE_RECIPIENT) && dossier.includes(BULK_PURPOSE) && dossier.includes('closed')
      && dossier.toLowerCase().includes('never delete'),
    dossier.slice(0, 300),
  );
  record(
    'Consent evidence locators are never projected — not in the list, not in the separately authorized dossier',
    [EVIDENCE_A, EVIDENCE_B, EVIDENCE_C].every((needle) => !dossier.includes(needle)),
    'evidence_ref stays write-only on every read surface',
  );

  const erasureAffordances = await officerPage.evaluate(() => Array.from(document.querySelectorAll('button'))
    .map((button) => button.textContent.trim().toLowerCase())
    .filter((label) => /\b(delete|erase|remove|purge|destroy)\b/.test(label)));
  record(
    'No erasure affordance exists anywhere in the workspace: withdrawal, expiry and archive are the only closures',
    erasureAffordances.length === 0,
    erasureAffordances.length === 0 ? 'no delete/erase/purge control is rendered' : erasureAffordances.join(', '),
  );

  // ── Subject session: fail-closed projection, own acts, denied staff acts ─
  const subject = await session('e2e-privacy-subject', { denialsExpected: true });
  const { page: subjectPage } = subject;
  await waitIdle(subjectPage);
  const failClosed = await subjectPage.evaluate(() => document.querySelector('main#workspace-main')?.textContent ?? '');
  record(
    'A person with no privacy authority gets a fail-closed workspace, never an empty or partial one',
    failClosed.includes('Privacy workspace unavailable')
      && failClosed.includes('No privacy facts were displayed because the server did not authorize a workspace projection'),
    failClosed.slice(0, 200),
  );

  const submitKey = `privacy-e2e-subject-submit-${stamp}`;
  const ownSubmit = await api(subjectPage, `/privacy/consents/${subjectConsentId}/submit`, { key: submitKey });
  record(
    'The subject may submit its OWN consent with no staff capability at all',
    ownSubmit.status === 200 && ownSubmit.payload?.data?.lifecycle_state === 'submitted',
    `HTTP ${ownSubmit.status} ${JSON.stringify(ownSubmit.payload).slice(0, 160)}`,
  );

  const replaySubmit = await api(subjectPage, `/privacy/consents/${subjectConsentId}/submit`, { key: submitKey });
  record(
    'A replayed subject act is idempotent: the same key returns the same recorded outcome',
    replaySubmit.status === 200 && replaySubmit.payload?.data?.consent_id === ownSubmit.payload?.data?.consent_id,
    `HTTP ${replaySubmit.status} ${JSON.stringify(replaySubmit.payload).slice(0, 160)}`,
  );

  const repeatedSubmit = await api(subjectPage, `/privacy/consents/${subjectConsentId}/submit`, { key: `privacy-e2e-subject-repeat-${stamp}` });
  record(
    'A fresh repeat of an already-submitted consent is refused by the lifecycle registry (409)',
    repeatedSubmit.status === 409,
    `HTTP ${repeatedSubmit.status} ${JSON.stringify(repeatedSubmit.payload).slice(0, 180)}`,
  );

  const subjectPurpose = await api(subjectPage, '/privacy/purposes', {
    body: { name: `e2e-privacy-forged-purpose-${stamp}`, channel: 'email', category: 'marketing' },
    key: `privacy-e2e-subject-purpose-${stamp}`,
  });
  const subjectVerify = await api(subjectPage, `/privacy/consents/${subjectConsentId}/verify`, { key: `privacy-e2e-subject-verify-${stamp}` });
  const subjectApprove = await api(subjectPage, `/privacy/exports/${exportRequest.id}/approve`, { key: `privacy-e2e-subject-approve-${stamp}` });
  const subjectWorkspace = await api(subjectPage, '/privacy/workspace', { method: 'GET' });
  record(
    'Every officer-only act is refused for the subject: purpose definition, verification, export approval and the workspace read',
    subjectPurpose.status === 403 && subjectVerify.status === 403 && subjectApprove.status === 403 && subjectWorkspace.status === 403,
    `purposes=${subjectPurpose.status} verify=${subjectVerify.status} approve=${subjectApprove.status} workspace=${subjectWorkspace.status}`,
  );

  record(
    'Every observed Privacy mutation was a canonical POST (no PUT/PATCH/DELETE, no ad-hoc routes)',
    privacyMutations.length >= 18 && privacyMutationViolations.length === 0,
    `${privacyMutations.length} canonical mutations; violations=${JSON.stringify(privacyMutationViolations)}`,
  );

  function checkSession(label, { consoleErrors, failedRequests, deniedResponses }) {
    record(
      `${label} completed the journey without console/network errors outside the provoked denials`,
      consoleErrors.length === 0 && failedRequests.length === 0,
      `console=${consoleErrors.length ? JSON.stringify(consoleErrors.slice(0, 4)) : '0'}; failedRequests=${failedRequests.length ? JSON.stringify(failedRequests.slice(0, 4)) : '0'}; provoked=${deniedResponses.length}`,
    );
  }
  checkSession('Officer', officer);
  checkSession('Approver one', approverOne);
  checkSession('Approver two', approverTwo);
  // The subject session is denied by design on every staff surface, so only
  // page crashes and failed requests are failures there.
  record(
    'Subject completed the journey without any page error',
    subject.consoleErrors.filter((entry) => !entry.startsWith('HTTP')).length === 0 && subject.failedRequests.length === 0,
    `pageErrors=${JSON.stringify(subject.consoleErrors.filter((entry) => !entry.startsWith('HTTP')).slice(0, 4))}; provoked=${subject.deniedResponses.length}`,
  );
} catch (error) {
  console.error('PRIVACY BROWSER E2E FAILED');
  console.error(error?.stack || String(error));
  // An aborted journey is a failed journey: record it so the process exits
  // non-zero even when every check collected before the throw had passed.
  record('Journey ran to completion', false, String(error?.message || error).slice(0, 300));
  writeStepSummary(`## Privacy browser E2E\n\n**Journey aborted:** ${String(error?.message || error).slice(0, 2_000)}\n`);
  for (const context of contexts) {
    try {
      const pages = await context.pages();
      for (const page of pages) {
        writeStepSummary(`### ${page.url()}\n\n\`\`\`\n${(await page.content()).slice(0, 3_000)}\n\`\`\`\n`);
      }
    } catch {
      // Best-effort diagnostics; never mask the original failure.
    }
  }
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass);
writeStepSummary(`## Privacy browser E2E\n\n**${results.length - failed.length}/${results.length} checks passed** (${new Date().toISOString()})\n\n${failed.length === 0 ? '' : failed.map((result) => `- FAILED: ${result.name}`).join('\n')}\n`);
console.log(`\nPRIVACY BROWSER E2E: ${results.length - failed.length}/${results.length} checks passed`);
for (const result of failed) console.log(`FAILED: ${result.name}`);
if (failed.length > 0) process.exit(1);
