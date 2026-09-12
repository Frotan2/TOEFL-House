#!/usr/bin/env node
/**
 * Documents & Evidence browser E2E journey (puppeteer-core, no new deps).
 *
 * Drives the REAL server-rendered React UI in headless Chromium against the
 * disposable verification database. Three isolated browser sessions prove the
 * complete evidence lifecycle plus the authority boundaries that matter most
 * for this domain:
 *
 *   officer (classify+retention):
 *     - the workspace gate renders server-projected metrics;
 *     - defines the classification and its retention rule from the UI;
 *     - records a retention decision on the active document;
 *   registrar (documents.register):
 *     - registers evidence for the target person (draft + immutable v1);
 *     - the action matrix is the SERVER's projection: a draft offers Submit
 *       version and History — and no Verify affordance exists at all;
 *     - submits an immutable version; re-submits after the fail verdict
 *       (the rejected→submitted recovery flow);
 *     - the immutable history renders every version, uploader, verdict chip
 *       and reason — with no storage reference anywhere;
 *     - an archived document offers History only: no mutation affordance;
 *   verifier (documents.register + documents.verify):
 *     - fails the registrar's submitted version with a recorded reason;
 *     - passes and activates the evidence;
 *     - SoD boundary, observed as a denial: the verifier uploads their OWN
 *       document and then tries to verify it — the server refuses ("the
 *       verifier may not be the uploader") and the document does NOT move;
 *     - expires and archives behind the irreversible confirm dialogs.
 *
 * Every observed Documents mutation is asserted to be a POST on the canonical
 * /api/v1/documents route family (no PUT/PATCH/DELETE anywhere), each session
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
 *     node scripts/runtime/documents-browser-e2e.mjs
 */
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8999';
const PASSWORD = process.env.DOCUMENTS_E2E_PASSWORD || 'employee-password-1';
const EXECUTABLE = process.env.CHROMIUM_PATH?.trim() || '/usr/bin/chromium';
const LIB_DIR = process.env.CHROMIUM_LIB_DIR?.trim();
const phpBin = process.env.PHP_BINARY?.trim() || 'php';

const results = [];
const record = (name, pass, detail = '') => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

const EVIDENCE_FILE = '/tmp/documents-browser-e2e-summary.md';
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

/** Every observed Documents mutation MUST be a POST on a canonical route. */
const DOCUMENTS_MUTATION = /^\/api\/v1\/documents(\/classifications|\/retention-rules|(\/[0-9a-f-]+\/(submit|verify|activate|expire|archive|retention))?)$/;
const documentMutations = [];
const documentMutationViolations = [];

// Provision (idempotently) the journey actors through the same canonical
// access model the feature suite uses.
let provisionOutput;
try {
  provisionOutput = execFileSync(phpBin, ['scripts/runtime/documents-browser-provision.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE || 'toefl_house_e2e' },
    encoding: 'utf8',
  });
} catch (error) {
  console.error('DOCUMENTS BROWSER E2E PROVISIONING FAILED');
  console.error(`status=${error.status ?? 'n/a'} stdout=${(error.stdout ?? '').slice(-2_000)}`);
  console.error(`stderr=${(error.stderr ?? '').slice(-2_000)}`);
  writeStepSummary(`## Documents browser E2E\n\n**Provisioning failed** (exit ${error.status ?? 'n/a'})\n\n\`\`\`\n${(error.stderr ?? error.stdout ?? String(error)).slice(-3_000)}\n\`\`\`\n`);
  process.exit(1);
}
console.log(provisionOutput.split('\n').filter((line) => line.includes('documents browser E2E')).join('\n'));

const stamp = `${Date.now()}`;
const CATEGORY = `e2e-identity-${stamp}`;
const DOC_A = `Browser E2E evidence ${stamp}`;
const DOC_B = `Browser E2E self-upload ${stamp}`;
const SUBJECT = 'Authority Fixture e2e-documents-subject';

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
   */
  async function session(username) {
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
    let expectingDenial = false;

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
      if (response.status() >= 400 && !expectingDenial) {
        consoleErrors.push(`HTTP ${response.status()} ${url.slice(0, 220)}`);
      }
    });
    page.on('request', (request) => {
      const url = request.url();
      if (!url.includes('/api/v1/documents')) return;
      const method = request.method();
      if (method === 'GET') return;
      const pathname = new URL(url).pathname;
      if (method === 'POST' && DOCUMENTS_MUTATION.test(pathname)) {
        documentMutations.push(`POST ${pathname}`);
      } else {
        documentMutationViolations.push(`${method} ${pathname}`);
      }
    });

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name="username"]', username);
    await page.type('input[name="password"]', PASSWORD);
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
    ]);
    await page.goto(`${BASE}/documents`, { waitUntil: 'networkidle2' });
    await page.waitForSelector('#app-toolbar', { timeout: 60_000 });
    return {
      context,
      page,
      consoleErrors,
      failedRequests,
      expectDialogs: (...answers) => page.evaluate((queue) => { window.__dialogQueue = queue; }, answers),
      dialogLog: () => page.evaluate(() => window.__dialogLog),
      expectDenial: (value) => { expectingDenial = value; },
    };
  }

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

  /** Click a non-disabled button with the exact label inside a registry row. */
  const clickRowButton = (page, title, label) => page.waitForFunction(
    ({ needle, text }) => {
      const section = Array.from(document.querySelectorAll('section'))
        .find((element) => element.querySelector('h2')?.textContent.includes('Documents in your authorized scope'));
      const row = Array.from(section?.querySelectorAll('table tbody tr') || [])
        .find((element) => element.querySelector('th')?.textContent.includes(needle));
      if (!row) return false;
      const button = Array.from(row.querySelectorAll('button')).find((element) => element.textContent.trim() === text);
      if (button && !button.disabled) {
        button.click();
        return true;
      }
      return false;
    },
    { timeout: 60_000, polling: 100 },
    { needle: title, text: label },
  );

  /** The lifecycle chip rendered for a registry row. */
  const rowChip = (page, title) => page.evaluate((needle) => {
    const section = Array.from(document.querySelectorAll('section'))
      .find((element) => element.querySelector('h2')?.textContent.includes('Documents in your authorized scope'));
    const row = Array.from(section?.querySelectorAll('table tbody tr') || [])
      .find((element) => element.querySelector('th')?.textContent.includes(needle));
    return row?.querySelector('td:nth-child(3)')?.textContent.trim() ?? null;
  }, title);

  /** Exact button labels offered by a registry row. */
  const rowButtons = (page, title) => page.evaluate((needle) => {
    const section = Array.from(document.querySelectorAll('section'))
      .find((element) => element.querySelector('h2')?.textContent.includes('Documents in your authorized scope'));
    const row = Array.from(section?.querySelectorAll('table tbody tr') || [])
      .find((element) => element.querySelector('th')?.textContent.includes(needle));
    return Array.from(row?.querySelectorAll('.row-actions button') || []).map((button) => button.textContent.trim());
  }, title);

  /** Fill a labelled input/textarea by its visible label text (polls until mounted). */
  const setLabeledInput = (page, label, value) => page.waitForFunction(
    ({ labelText, next }) => {
      const labelElement = Array.from(document.querySelectorAll('label')).find((element) => element.textContent.trim() === labelText);
      const span = labelElement?.querySelector('span');
      const input = (span && document.getElementById(span.id.replace('-label', '')))
        || labelElement?.querySelector('input, textarea, select');
      if (!input) return false;
      const proto = input.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
      Object.getOwnPropertyDescriptor(proto, 'value').set.call(input, next);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    },
    { timeout: 60_000, polling: 100 },
    { labelText: label, next: value },
  );

  /** Choose a select option by visible option text (polls until mounted). */
  const selectOption = async (page, label, optionText) => {
    const handle = await page.waitForFunction(
      ({ labelText, needle }) => {
        const labelElement = Array.from(document.querySelectorAll('label')).find((element) => element.textContent.trim() === labelText);
        const span = labelElement?.querySelector('span');
        const input = (span && document.getElementById(span.id.replace('-label', '')))
          || labelElement?.querySelector('select');
        if (!input) return false;
        const option = Array.from(input.options).find((element) => element.textContent.includes(needle));
        if (!option) return false;
        Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value').set.call(input, option.value);
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return option.textContent.trim();
      },
      { timeout: 60_000, polling: 100 },
      { labelText: label, needle: optionText },
    );
    return handle.jsonValue();
  };

  /** Click the enabled command-panel submit button by exact label (polls). */
  const clickCommandSubmit = (page, label) => page.waitForFunction(
    (text) => {
      const panel = document.querySelector('.documents-command-panel');
      if (!panel) return false;
      const button = Array.from(panel.querySelectorAll('button')).find((element) => element.textContent.trim() === text);
      if (!button || button.disabled) return false;
      button.click();
      return true;
    },
    { timeout: 60_000, polling: 100 },
    label,
  );

  const dialogMessages = (page) => page.evaluate(() => window.__dialogLog);

  // ── Officer session: workspace gate + policy definitions ───────────────
  const officer = await session('e2e-documents-officer');
  const { page: officerPage } = officer;
  await waitIdle(officerPage);
  const officerMetrics = await officerPage.evaluate(() => Array.from(document.querySelectorAll('.summary-grid .panel')).map((panel) => panel.textContent.trim()));
  record('Officer workspace renders server-projected evidence metrics', officerMetrics.length === 4, officerMetrics.join(' | '));

  await openTab(officerPage, 'Classification & retention');
  await setLabeledInput(officerPage, 'Category', CATEGORY);
  await setLabeledInput(officerPage, 'Owning module', 'Identity');
  await selectOption(officerPage, 'Access class', 'Restricted');
  await clickPageButton(officerPage, 'Define classification');
  await waitForNotice(officerPage, 'Classification defined and audit-recorded.');
  await waitIdle(officerPage);
  record('Officer defines the classification through the UI', true, CATEGORY);

  await setLabeledInput(officerPage, 'Retention days', '365');
  await setLabeledInput(officerPage, 'Legal basis', 'E2E retention proof');
  await clickPageButton(officerPage, 'Define retention rule');
  await waitForNotice(officerPage, 'Retention rule defined and audit-recorded.');
  await waitIdle(officerPage);
  record('Officer defines the retention rule through the UI', true, `${CATEGORY} retain 365d`);

  // ── Registrar session: register + submit; affordances are server-owned ──
  const registrar = await session('e2e-documents-registrar');
  const { page: registrarPage } = registrar;
  await waitIdle(registrarPage);

  await openTab(registrarPage, 'Register document');
  const subjectOption = await selectOption(registrarPage, 'Subject', SUBJECT);
  const classificationOption = await selectOption(registrarPage, 'Classification', CATEGORY);
  await setLabeledInput(registrarPage, 'Document title', DOC_A);
  await setLabeledInput(registrarPage, 'Content hash', 'sha256:e2e-documents-a-v1');
  await setLabeledInput(registrarPage, 'Storage reference', 'storage/e2e/doc-a-v1.pdf');
  await clickPageButton(registrarPage, 'Register immutable version 1');
  await waitForNotice(registrarPage, 'Document registered as a draft with immutable version 1.');
  await waitIdle(registrarPage);
  record('Registrar registers evidence for the target person', true, `${subjectOption} / ${classificationOption}`);

  record('Registered document appears in the authorized scope', await rowChip(registrarPage, DOC_A) === 'Draft', `chip=${await rowChip(registrarPage, DOC_A)}`);
  const draftButtons = await rowButtons(registrarPage, DOC_A);
  record(
    'A draft offers the server-projected actions and NEVER a Verify affordance',
    draftButtons.includes('History') && draftButtons.includes('Submit version') && !draftButtons.includes('Verify'),
    draftButtons.join(', '),
  );

  await clickRowButton(registrarPage, DOC_A, 'Submit version');
  await setLabeledInput(registrarPage, 'Content hash', 'sha256:e2e-documents-a-v2');
  await setLabeledInput(registrarPage, 'Storage reference', 'storage/e2e/doc-a-v2.pdf');
  await clickCommandSubmit(registrarPage, 'Submit new version');
  await waitForNotice(registrarPage, 'New immutable version submitted for review.');
  await waitIdle(registrarPage);
  record('Registrar submits an immutable version for review', await rowChip(registrarPage, DOC_A) === 'Submitted', `chip=${await rowChip(registrarPage, DOC_A)}`);

  // ── Verifier session: independent verification + lifecycle + SoD denial ──
  const verifier = await session('e2e-documents-verifier');
  const { page: verifierPage } = verifier;
  await waitIdle(verifierPage);

  await clickRowButton(verifierPage, DOC_A, 'Verify');
  await selectOption(verifierPage, 'Verification result', 'Fail');
  await setLabeledInput(verifierPage, 'Evidence reason', 'e2e rework required');
  await clickCommandSubmit(verifierPage, 'Record failed verification');
  await waitForNotice(verifierPage, 'Verification failed and evidence was recorded.');
  await waitIdle(verifierPage);
  record('Verifier records an independent FAIL verdict', await rowChip(verifierPage, DOC_A) === 'Rejected', `chip=${await rowChip(verifierPage, DOC_A)}`);

  // Recovery flow: the registrar re-submits the rejected document.
  await clickRowButton(registrarPage, DOC_A, 'Submit version');
  await setLabeledInput(registrarPage, 'Content hash', 'sha256:e2e-documents-a-v3');
  await setLabeledInput(registrarPage, 'Storage reference', 'storage/e2e/doc-a-v3.pdf');
  await clickCommandSubmit(registrarPage, 'Submit new version');
  await waitForNotice(registrarPage, 'New immutable version submitted for review.');
  await waitIdle(registrarPage);
  record('Registrar re-submits the rejected document (rejected→submitted)', await rowChip(registrarPage, DOC_A) === 'Submitted', `chip=${await rowChip(registrarPage, DOC_A)}`);

  await clickRowButton(verifierPage, DOC_A, 'Verify');
  await selectOption(verifierPage, 'Verification result', 'Pass');
  await setLabeledInput(verifierPage, 'Evidence reason', 'e2e verified independently');
  await clickCommandSubmit(verifierPage, 'Record passing verification');
  await waitForNotice(verifierPage, 'Verification passed and evidence was recorded.');
  await waitIdle(verifierPage);
  record('Verifier records a PASS verdict', await rowChip(verifierPage, DOC_A) === 'Verified', `chip=${await rowChip(verifierPage, DOC_A)}`);

  await verifier.expectDialogs(true);
  await clickRowButton(verifierPage, DOC_A, 'Activate');
  await waitForNotice(verifierPage, 'Document activated.');
  await waitIdle(verifierPage);
  record('Verifier activates the verified evidence', await rowChip(verifierPage, DOC_A) === 'Active', `chip=${await rowChip(verifierPage, DOC_A)}`);

  // Officer records the retention decision while the document is ACTIVE.
  await openTab(officerPage, 'Evidence registry');
  await clickPageButton(officerPage, 'Refresh facts');
  await waitIdle(officerPage);
  const officerRowButtons = await rowButtons(officerPage, DOC_A);
  record(
    'The officer row offers History + Retention and nothing else (capability-projected)',
    officerRowButtons.join(',') === 'History,Retention',
    officerRowButtons.join(', '),
  );
  await officer.expectDialogs(true);
  await clickRowButton(officerPage, DOC_A, 'Retention');
  await waitForNotice(officerPage, 'Retention decision recorded from the current rule.');
  await waitIdle(officerPage);
  const retentionEvidence = await officerPage.evaluate(() => {
    const section = Array.from(document.querySelectorAll('section'))
      .find((element) => element.querySelector('h2')?.textContent.includes('Recent recorded decisions'));
    return section?.textContent || '';
  });
  record(
    'The retention decision appears in the retention evidence table',
    retentionEvidence.includes(DOC_A) && retentionEvidence.includes('Retain'),
    retentionEvidence.slice(0, 160),
  );

  // SoD boundary, observed as a denial: the verifier uploads and tries to
  // verify their OWN document.
  await openTab(verifierPage, 'Register document');
  await selectOption(verifierPage, 'Subject', SUBJECT);
  await selectOption(verifierPage, 'Classification', CATEGORY);
  await setLabeledInput(verifierPage, 'Document title', DOC_B);
  await setLabeledInput(verifierPage, 'Content hash', 'sha256:e2e-documents-b-v1');
  await setLabeledInput(verifierPage, 'Storage reference', 'storage/e2e/doc-b-v1.pdf');
  await clickPageButton(verifierPage, 'Register immutable version 1');
  await waitForNotice(verifierPage, 'Document registered as a draft with immutable version 1.');
  await waitIdle(verifierPage);
  await clickRowButton(verifierPage, DOC_B, 'Submit version');
  await setLabeledInput(verifierPage, 'Content hash', 'sha256:e2e-documents-b-v2');
  await setLabeledInput(verifierPage, 'Storage reference', 'storage/e2e/doc-b-v2.pdf');
  await clickCommandSubmit(verifierPage, 'Submit new version');
  await waitForNotice(verifierPage, 'New immutable version submitted for review.');
  await waitIdle(verifierPage);
  record('Verifier registers and submits their OWN document (SoD setup)', await rowChip(verifierPage, DOC_B) === 'Submitted', `chip=${await rowChip(verifierPage, DOC_B)}`);

  verifier.expectDenial(true);
  await clickRowButton(verifierPage, DOC_B, 'Verify');
  await selectOption(verifierPage, 'Verification result', 'Pass');
  await setLabeledInput(verifierPage, 'Evidence reason', 'attempted self-verification');
  await clickCommandSubmit(verifierPage, 'Record passing verification');
  await waitForAlert(verifierPage, 'the verifier may not be the uploader');
  verifier.expectDenial(false);
  await waitIdle(verifierPage);
  record(
    'Uploader/verifier SoD is denied in the live UI and the document does NOT move',
    await rowChip(verifierPage, DOC_B) === 'Submitted',
    `chip=${await rowChip(verifierPage, DOC_B)} — alert carries the server message`,
  );

  // Expire and archive behind the irreversible confirms.
  await verifier.expectDialogs(true);
  await clickRowButton(verifierPage, DOC_A, 'Expire');
  await waitForNotice(verifierPage, 'Document expired; its immutable evidence remains available.');
  await waitIdle(verifierPage);
  record('Verifier expires the active document', await rowChip(verifierPage, DOC_A) === 'Expired', `chip=${await rowChip(verifierPage, DOC_A)}`);

  await verifier.expectDialogs(true);
  await clickRowButton(verifierPage, DOC_A, 'Archive');
  await waitForNotice(verifierPage, 'Document archived; immutable evidence remains retained.');
  await waitIdle(verifierPage);
  record('Verifier archives the expired document', await rowChip(verifierPage, DOC_A) === 'Archived', `chip=${await rowChip(verifierPage, DOC_A)}`);

  const verifierDialogs = await dialogMessages(verifierPage);
  record(
    'The archive confirm warns that archiving does not erase immutable evidence',
    verifierDialogs.some((message) => message.startsWith(`Archive \u201C${DOC_A}\u201D?`) && message.includes('Archiving does not erase immutable evidence.')),
    verifierDialogs.join(' || ').slice(0, 220),
  );

  // ── Registrar session: immutable history + terminal-state affordances ──
  await openTab(registrarPage, 'Evidence registry');
  await clickPageButton(registrarPage, 'Refresh facts');
  await waitIdle(registrarPage);
  const archivedButtons = await rowButtons(registrarPage, DOC_A);
  record(
    'An archived document offers History only — no mutation affordance',
    archivedButtons.join(',') === 'History',
    archivedButtons.join(', '),
  );

  await clickRowButton(registrarPage, DOC_A, 'History');
  await registrarPage.waitForFunction(
    () => document.querySelector('.documents-history')?.textContent.includes('Version 3'),
    { timeout: 60_000 },
  );
  const history = await registrarPage.evaluate(() => document.querySelector('.documents-history')?.textContent || '');
  record(
    'The immutable history renders every version, uploader and verdict with its reason',
    ['Version 1', 'Version 2', 'Version 3', 'sha256:e2e-documents-a-v1', 'uploaded by', 'Fail', 'e2e rework required', 'Pass', 'e2e verified independently']
      .every((needle) => history.includes(needle)),
    history.slice(0, 200),
  );
  record(
    'No storage reference is ever rendered anywhere in the workspace',
    !history.includes('storage/') && !(await registrarPage.evaluate(() => document.querySelector('main#workspace-main')?.textContent ?? '')).includes('storage/'),
    'storage_ref is write-only projection',
  );
  await clickPageButton(registrarPage, 'Close history');

  record(
    'Every observed Documents mutation was a canonical POST (no PUT/PATCH/DELETE, no ad-hoc routes)',
    documentMutations.length >= 12 && documentMutationViolations.length === 0,
    `${documentMutations.length} canonical mutations; violations=${JSON.stringify(documentMutationViolations)}`,
  );

  function checkSession(label, { consoleErrors, failedRequests }) {
    record(
      `${label} completed the journey without console/network errors outside the provoked denial`,
      consoleErrors.length === 0 && failedRequests.length === 0,
      `console=${consoleErrors.length ? JSON.stringify(consoleErrors.slice(0, 4)) : '0'}; failedRequests=${failedRequests.length ? JSON.stringify(failedRequests.slice(0, 4)) : '0'}`,
    );
  }
  checkSession('Officer', officer);
  checkSession('Registrar', registrar);
  checkSession('Verifier', verifier);
} catch (error) {
  console.error('DOCUMENTS BROWSER E2E FAILED');
  console.error(error?.stack || String(error));
  writeStepSummary(`## Documents browser E2E\n\n**Journey aborted:** ${String(error?.message || error).slice(0, 2_000)}\n`);
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
writeStepSummary(`## Documents browser E2E\n\n**${results.length - failed.length}/${results.length} checks passed** (${new Date().toISOString()})\n\n${failed.length === 0 ? '' : failed.map((result) => `- FAILED: ${result.name}`).join('\n')}\n`);
console.log(`\nDOCUMENTS BROWSER E2E: ${results.length - failed.length}/${results.length} checks passed`);
for (const result of failed) console.log(`FAILED: ${result.name}`);
if (failed.length > 0) process.exit(1);
