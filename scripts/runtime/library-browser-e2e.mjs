/**
 * Library & Resources browser E2E.
 *
 * Verifies the real React workspace against the canonical Resources API
 * through Chromium, with three isolated operator sessions:
 *
 *   e2e-library-librarian   registers a book copy, issues it to a borrower,
 *                           records the return, registers an asset, assigns
 *                           and releases custody, raises a staged disposal
 *                           request, provokes (and observes) the capability
 *                           denial on self-approval, WITHDRAWS the request
 *                           as its requester, re-requests, raises a work
 *                           order, provokes the approval denial, then — after
 *                           the two approval sessions signed — executes the
 *                           disposal and completes the work order with
 *                           evidence.
 *   e2e-library-approver-1  first disposal signature (the request MUST stay
 *                           requested and withdrawable) and the independent
 *                           work-order approval.
 *   e2e-library-approver-2  second disposal signature (the request becomes
 *                           approved and executable by its requester).
 *
 * Required environment:
 *   BASE_URL=http://127.0.0.1:8999
 *   CHROMIUM_PATH=/usr/bin/chromium
 *   DB_DATABASE=<disposable migrated database>   (provisioning target)
 * Optional:
 *   LIBRARY_E2E_PASSWORD  (default: the provisioning-convention password)
 *   PHP_BINARY            (default: php on PATH)
 *
 * No credentials are embedded beyond the disposable-database provisioning
 * convention already used by the structure journey.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8999';
const PASSWORD = process.env.LIBRARY_E2E_PASSWORD || 'employee-password-1';
const EXECUTABLE = process.env.CHROMIUM_PATH?.trim() || '/usr/bin/chromium';
const LIB_DIR = process.env.CHROMIUM_LIB_DIR?.trim();
const phpBin = process.env.PHP_BINARY?.trim() || 'php';

const results = [];
const record = (name, pass, detail = '') => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

/**
 * CI log downloads may be unavailable to an operator, so every failure also
 * lands in the workflow step summary. The workflow additionally commits a
 * mirror file from /tmp to the branch, because step summaries are not
 * exposed through the public REST API either.
 */
const EVIDENCE_FILE = '/tmp/library-browser-e2e-summary.md';
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

/** Every observed Resources mutation MUST be a POST on a canonical route. */
const LIBRARY_MUTATION = /^\/api\/v1\/resources\/(books(\/[0-9a-f-]+\/issue)?|issuances\/[0-9a-f-]+\/(return|loss)|assets(\/[0-9a-f-]+\/(custody(\/release)?|disposal))?|disposals\/[0-9a-f-]+\/(approve|withdraw|execute)|work-orders(\/[0-9a-f-]+\/(approve|start|complete|cancel))?)$/;
const resourceMutations = [];

// Provision (idempotently) the journey actors through the same canonical
// access model the feature suite uses.
let provisionOutput;
try {
  provisionOutput = execFileSync(phpBin, ['scripts/runtime/library-browser-provision.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE || 'toefl_house_dev' },
    encoding: 'utf8',
  });
} catch (error) {
  console.error('LIBRARY BROWSER E2E PROVISIONING FAILED');
  console.error(`status=${error.status ?? 'n/a'} stdout=${(error.stdout ?? '').slice(-2_000)}`);
  console.error(`stderr=${(error.stderr ?? '').slice(-2_000)}`);
  writeStepSummary(`## Library browser E2E\n\n**Provisioning failed** (exit ${error.status ?? 'n/a'})\n\n\`\`\`\n${(error.stderr ?? error.stdout ?? String(error)).slice(-3_000)}\n\`\`\`\n`);
  process.exit(1);
}
console.log(provisionOutput.split('\n').filter((line) => line.includes('library browser E2E')).join('\n'));

const stamp = `${Date.now()}`;
const BOOK_CODE = `E2E-BOOK-${stamp}`;
const BOOK_TITLE = `Browser E2E volume ${stamp}`;
const ASSET_CODE = `E2E-ASSET-${stamp}`;
const ASSET_NAME = `Browser E2E projector ${stamp}`;
const FACILITY = `Browser E2E HVAC ${stamp}`;

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  ...(LIB_DIR ? { env: { ...process.env, LD_LIBRARY_PATH: `${LIB_DIR}:${process.env.LD_LIBRARY_PATH ?? ''}` } } : {}),
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  headless: true,
  timeout: 90_000,
});

/** @type {puppeteer.BrowserContext[]} */
const contexts = [];
const allConsoleErrors = [];

try {
  const health = await fetch(`${BASE}/health`);
  record('Health endpoint is reachable', health.ok, `HTTP ${health.status}`);
  if (!health.ok) throw new Error('server is not reachable');

  /**
   * One isolated cookie jar per operator; each logs in once. Dialogs are
   * answered from an explicit queue so every prompt/confirm the React layer
   * raises is deliberately scripted instead of blanket-accepted.
   */
  async function session(username) {
    const context = await browser.createBrowserContext();
    contexts.push(context);
    const page = await context.newPage();
    /**
     * Deterministic dialogs: window.prompt/window.confirm are scripted
     * in-page from an explicit queue and logged with their exact messages
     * and defaults. CDP dialog plumbing in headless Chromium is not
     * load-bearing for this journey (and a silently dismissed prompt would
     * make the React handler return without any observable trace), while
     * the log doubles as evidence that defaults come from the server
     * calendar and confirms name the right record.
     */
    await page.evaluateOnNewDocument(() => {
      window.__dialogQueue = [];
      window.__dialogLog = [];
      window.prompt = (message, defaultValue) => {
        const response = window.__dialogQueue.shift();
        const value = response === undefined ? (defaultValue ?? '') : String(response);
        window.__dialogLog.push({ kind: 'prompt', message: String(message).slice(0, 120), default: defaultValue === undefined ? null : String(defaultValue), returned: value });
        return value;
      };
      window.confirm = (message) => {
        const response = window.__dialogQueue.shift();
        const ok = response === undefined ? true : Boolean(response);
        window.__dialogLog.push({ kind: 'confirm', message: String(message).slice(0, 160), returned: ok });
        return ok;
      };
    });
    await page.setViewport({ width: 1440, height: 1100, deviceScaleFactor: 1 });
    page.setDefaultNavigationTimeout(30_000);
    page.setDefaultTimeout(15_000);

    const consoleErrors = [];
    const failedRequests = [];
    const observedDenials = [];
    let deliberateDenialWindow = 0;

    page.on('console', (message) => {
      if (message.type() !== 'error') return;
      // "Failed to load resource" is logged for the 403 responses that a
      // negative assertion deliberately provoked; those are not product errors.
      if (deliberateDenialWindow > 0 && message.text().startsWith('Failed to load resource')) return;
      consoleErrors.push(`${username}: ${message.text().slice(0, 220)}`);
    });
    page.on('pageerror', (error) => consoleErrors.push(`${username}: ${String(error.message).slice(0, 220)}`));
    page.on('requestfailed', (request) => failedRequests.push(`${username}: ${request.url().replace(BASE, '')} ${request.failure()?.errorText ?? 'failed'}`));
    page.on('requestfinished', (request) => {
      const response = request.response();
      if (!response) return;
      const url = new URL(request.url());
      const path = url.pathname;
      if (path.startsWith('/api/v1/resources') && request.method() !== 'GET') {
        resourceMutations.push({ username, method: request.method(), path });
      }
      if (response.status() >= 400 && !url.pathname.includes('favicon')) {
        if (deliberateDenialWindow > 0) {
          observedDenials.push({ status: response.status(), path });
          return;
        }
        failedRequests.push(`${username}: ${response.status()} ${path.slice(0, 180)}`);
      }
    });
    const dialogLeaks = [];
    page.on('dialog', async (dialog) => {
      // With the in-page stubs active a real dialog is itself a finding.
      dialogLeaks.push(`${dialog.type()}: ${dialog.message().slice(0, 120)}`);
      await dialog.accept().catch(() => {});
    });

    await page.goto(`${BASE}/library`, { waitUntil: 'networkidle2' });
    if (page.url().includes('/login')) {
      await page.type('input[name="username"]', username);
      await page.type('input[name="password"]', PASSWORD);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
        page.click('button[type="submit"]'),
      ]);
    }
    await page.goto(`${BASE}/library`, { waitUntil: 'networkidle2' });
    if (page.url().includes('/login')) throw new Error(`${username}: library E2E login failed`);
    await page.waitForSelector('#library-title', { timeout: 20_000 });
    // The toolbar renders only after the workspace contract resolved.
    await page.waitForFunction(() => document.querySelector('#library-main .toolbar') !== null
      && !(document.body.innerText || '').includes('Loading authorized'), { timeout: 20_000 });

    return {
      context,
      page,
      username,
      consoleErrors,
      failedRequests,
      observedDenials,
      /** Script the upcoming window.prompt/window.confirm answers in order. */
      expectDialogs(...responses) {
        return page.evaluate((items) => window.__dialogQueue.push(...items), responses);
      },
      dialogLeaks,
      /** Runs a negative request whose 403 response + console noise are expected. */
      async expectingDenial(work) {
        deliberateDenialWindow += 1;
        try {
          return await work();
        } finally {
          await new Promise((resolve) => setTimeout(resolve, 250));
          deliberateDenialWindow -= 1;
        }
      },
    };
  }

  function checkSession(sessionRecord) {
    record(`${sessionRecord.username} session has no console/network errors`,
      sessionRecord.consoleErrors.length === 0 && sessionRecord.failedRequests.length === 0 && sessionRecord.dialogLeaks.length === 0,
      `console=${sessionRecord.consoleErrors.length} ${sessionRecord.consoleErrors.slice(0, 2).join(' | ')}`
      + ` network=${sessionRecord.failedRequests.length} ${sessionRecord.failedRequests.slice(0, 2).join(' | ')}`
      + ` dialogLeaks=${sessionRecord.dialogLeaks.length} ${sessionRecord.dialogLeaks.slice(0, 2).join(' | ')}`);
    allConsoleErrors.push(...sessionRecord.consoleErrors);
  }

  /**
   * After every successful command the workspace re-fetches its facts, and
   * library.tsx unmounts the whole console while loading. Interacting during
   * that window finds nothing, so every notice is followed by an idle wait:
   * the toolbar exists again and no loading banner is on screen.
   */
  const waitIdle = async (page) => {
    await page.waitForFunction(() => document.querySelector('#library-main .toolbar') !== null
      && !(document.body.innerText || '').includes('Loading authorized'), { timeout: 20_000 });
  };
  /** Everything needed to explain a timed-out expectation without CI logs. */
  const pageState = async (page) => {
    try {
      return await page.evaluate(() => ({
        url: window.location.pathname,
        notice: document.querySelector('.notice')?.textContent || null,
        alert: document.querySelector('.alert')?.textContent || null,
        dialogs: (window.__dialogLog || []).slice(-6),
        busy: (document.body.innerText || '').includes('Loading authorized'),
        text: (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 500),
      }));
    } catch {
      return 'page state unavailable';
    }
  };
  const waitForNotice = async (page, expected) => {
    try {
      await page.waitForFunction((needle) => (document.querySelector('.notice')?.textContent || '').includes(needle),
        { timeout: 20_000 }, expected);
    } catch {
      const state = await pageState(page);
      throw new Error(`notice "${expected}" never appeared; mutations=${JSON.stringify(resourceMutations.slice(-3))} state=${JSON.stringify(state)}`);
    }
    await waitIdle(page);
  };
  const waitForAlert = async (page, expected) => {
    try {
      await page.waitForFunction((needle) => (document.querySelector('.alert')?.textContent || '').includes(needle),
        { timeout: 20_000 }, expected);
    } catch {
      const state = await pageState(page);
      throw new Error(`alert "${expected}" never appeared; state=${JSON.stringify(state)}`);
    }
  };
  /** Sets a React-controlled input inside the form whose submit button matches. */
  const setFormInput = async (page, submitText, labelText, value) => {
    const updated = await page.evaluate(({ needle, wantedLabel, nextValue }) => {
      const form = [...document.querySelectorAll('form')]
        .find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(needle));
      const label = [...(form?.querySelectorAll('label') || [])]
        .find((node) => (node.firstChild?.textContent || '').trim() === wantedLabel);
      const element = label?.querySelector('input, textarea, select');
      if (!element) return false;
      const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(element), 'value')?.set;
      if (!setter) return false;
      setter.call(element, nextValue);
      element.dispatchEvent(new Event('input', { bubbles: true }));
      element.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    }, { needle: submitText, wantedLabel: labelText, nextValue: value });
    if (!updated) throw new Error(`Control "${labelText}" in form "${submitText}" was not found`);
  };
  /** Selects the first non-empty option of a labelled select (form-scoped or global). */
  const selectFirstOption = async (page, labelText, submitText = null) => {
    const value = await page.evaluate(({ wantedLabel, needle }) => {
      const scope = needle
        ? [...document.querySelectorAll('form')].find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(needle))
        : document;
      const label = [...(scope?.querySelectorAll('label') || [])]
        .find((node) => (node.firstChild?.textContent || '').trim() === wantedLabel);
      const select = label?.querySelector('select');
      if (!select) return null;
      const option = [...select.options].find((candidate) => candidate.value !== '');
      if (!option) return null;
      const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(select), 'value')?.set;
      setter.call(select, option.value);
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return option.value;
    }, { wantedLabel: labelText, needle: submitText });
    if (value === null) throw new Error(`Select "${labelText}" has no authorized option`);
    return value;
  };
  const submitForm = async (page, text) => {
    const submitted = await page.evaluate((needle) => {
      const form = [...document.querySelectorAll('form')]
        .find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(needle));
      const button = form?.querySelector('button[type="submit"]');
      if (!button) return false;
      button.click();
      return true;
    }, text);
    if (!submitted) throw new Error(`Form with submit button containing "${text}" was not found`);
  };
  /**
   * Clicks a button inside the table row containing rowText, optionally
   * section-scoped by its heading. The click happens INSIDE the polled
   * predicate so a workspace re-render between polls cannot make the
   * interaction miss: the first poll that sees an enabled button clicks it.
   */
  const clickRowButton = async (page, rowText, buttonText, sectionHeading = null) => {
    try {
      await page.waitForFunction(({ rowNeedle, buttonNeedle, heading }) => {
        const sections = heading
          ? [...document.querySelectorAll('section')].filter((node) => (node.querySelector('h2')?.textContent || '').includes(heading))
          : [document];
        for (const scope of sections) {
          const row = [...scope.querySelectorAll('tbody tr')].find((node) => (node.innerText || '').includes(rowNeedle));
          if (!row) continue;
          const button = [...row.querySelectorAll('button')].find((node) => (node.textContent || '').trim().includes(buttonNeedle));
          if (button && !button.disabled) {
            button.click();
            return true;
          }
        }
        return false;
      }, { timeout: 20_000 }, { rowNeedle: rowText, buttonNeedle: buttonText, heading: sectionHeading });
    } catch {
      const state = await pageState(page);
      throw new Error(`Enabled button "${buttonText}" in row "${rowText}"${sectionHeading ? ` of "${sectionHeading}"` : ''} never appeared; state=${JSON.stringify(state)}`);
    }
  };
  /** Polls a row-shape detail out of a section-scoped table row. */
  const rowDetail = async (page, rowText, sectionHeading) => {
    await page.waitForFunction(({ rowNeedle, heading }) => {
      const section = [...document.querySelectorAll('section')].find((node) => (node.querySelector('h2')?.textContent || '').includes(heading));
      return [...(section?.querySelectorAll('tbody tr') ?? [])].some((node) => (node.innerText || '').includes(rowNeedle));
    }, { timeout: 20_000 }, { rowNeedle: rowText, heading: sectionHeading });

    return page.evaluate(({ rowNeedle, heading }) => {
      const section = [...document.querySelectorAll('section')].find((node) => (node.querySelector('h2')?.textContent || '').includes(heading));
      const row = [...section.querySelectorAll('tbody tr')].find((node) => (node.innerText || '').includes(rowNeedle));
      return {
        text: (row.innerText || '').replace(/\s+/g, ' '),
        buttons: [...row.querySelectorAll('button')].map((button) => (button.textContent || '').trim()),
      };
    }, { rowNeedle: rowText, heading: sectionHeading });
  };
  const waitForRow = async (page, rowText, sectionHeading = null) => {
    await page.waitForFunction(({ rowNeedle, heading }) => {
      const sections = heading
        ? [...document.querySelectorAll('section')].filter((node) => (node.querySelector('h2')?.textContent || '').includes(heading))
        : [document];
      return sections.some((scope) => [...scope.querySelectorAll('tbody tr')].some((node) => (node.innerText || '').includes(rowNeedle)));
    }, { timeout: 20_000 }, { rowNeedle: rowText, heading: sectionHeading });
  };
  const kabulToday = async (page) => {
    const calendar = await page.evaluate(() => fetch('/api/v1/calendar/today', { headers: { Accept: 'application/json' } })
      .then((response) => response.json()));
    const today = calendar?.data?.gregorian;
    if (!today) throw new Error('calendar contract did not provide the Kabul date');
    return today;
  };

  // ------------------------------------------------------------------
  // Librarian session: books, asset, custody, staged disposal request,
  // provoked denials, requester withdrawal, corrected re-request, work order.
  // ------------------------------------------------------------------
  const librarian = await session('e2e-library-librarian');
  const { page } = librarian;
  record('Library browser session is authenticated', !page.url().includes('/login'), page.url().replace(BASE, ''));
  const today = await kabulToday(page);

  const metrics = await page.$$eval('.summary-grid .metric', (nodes) => nodes.map((node) => node.textContent));
  record('Library workspace renders authorized facts', metrics.length === 6, `metrics=${JSON.stringify(metrics)}`);

  // --- books: register -> issue -> return ------------------------------
  await setFormInput(page, 'Register copy', 'Copy code', BOOK_CODE);
  await setFormInput(page, 'Register copy', 'Title', BOOK_TITLE);
  await setFormInput(page, 'Register copy', 'Acquired on', today);
  await selectFirstOption(page, 'Owning branch', 'Register copy');
  await submitForm(page, 'Register copy');
  await waitForNotice(page, 'Book copy registered.');
  await waitForRow(page, BOOK_CODE);
  record('Book copy is registered through the canonical API', true, BOOK_CODE);

  await selectFirstOption(page, 'Borrower');
  await librarian.expectDialogs(undefined, undefined); // due-date prompt (server default) + confirm
  await clickRowButton(page, BOOK_CODE, 'Issue');
  await waitForNotice(page, 'Book issued.');
  await waitForRow(page, 'Issued');
  record('Book issuance round-trips through the circulation ledger', true, 'chip=Issued');
  const issueDialogs = await page.evaluate(() => (window.__dialogLog || []).slice(-2));
  record('Issue dialogs default from the server calendar and name the record',
    issueDialogs[0]?.kind === 'prompt' && issueDialogs[0]?.default === today
      && issueDialogs[1]?.kind === 'confirm' && (issueDialogs[1]?.message || '').includes('Issue'),
    JSON.stringify(issueDialogs));

  await librarian.expectDialogs(undefined, undefined); // return-date prompt + confirm
  await clickRowButton(page, 'Issued', 'Return');
  await waitForNotice(page, 'Book returned.');
  await waitForRow(page, 'Returned');
  record('Book return closes the issuance in the ledger', true, 'chip=Returned');

  // --- asset: register -> custody assign/release ------------------------
  await setFormInput(page, 'Register asset', 'Code', ASSET_CODE);
  await setFormInput(page, 'Register asset', 'Name', ASSET_NAME);
  await setFormInput(page, 'Register asset', 'Category', 'equipment');
  await setFormInput(page, 'Register asset', 'Location', 'Room 7');
  await setFormInput(page, 'Register asset', 'Acquired on', today);
  await selectFirstOption(page, 'Owning branch', 'Register asset');
  await submitForm(page, 'Register asset');
  await waitForNotice(page, 'Asset registered.');
  await waitForRow(page, ASSET_CODE);
  record('Asset is registered with immutable provenance', true, ASSET_CODE);

  await selectFirstOption(page, 'Custodian');
  await librarian.expectDialogs(undefined, undefined); // assignment-date prompt + confirm
  await clickRowButton(page, ASSET_CODE, 'Assign custody');
  await waitForNotice(page, 'Custody assigned.');
  await librarian.expectDialogs(undefined, undefined); // release-date prompt + confirm
  await clickRowButton(page, ASSET_CODE, 'Release');
  await waitForNotice(page, 'Custody released.');
  record('Custody assignment and release round-trip', true, 'assigned then released');

  // --- staged disposal: request, provoked denial, withdrawal, re-request
  await librarian.expectDialogs(undefined, 'Browser E2E decommission', undefined); // method prompt (default scrap), reason prompt, confirm
  await clickRowButton(page, ASSET_CODE, 'Request disposal');
  await waitForNotice(page, 'Disposal request created.');
  await waitForRow(page, 'Requested', 'Approval and execution queue');
  record('Staged disposal request is created from the asset row', true, 'chip=Requested');

  const denialsBeforeSelfApproval = librarian.observedDenials.length;
  await librarian.expectingDenial(async () => {
    await librarian.expectDialogs(undefined); // confirm
    await clickRowButton(page, 'Requested', 'Approve', 'Approval and execution queue');
    await waitForAlert(page, 'no active authority grants resources.dispose_approve');
  });
  const selfApprovalDenial = librarian.observedDenials[denialsBeforeSelfApproval];
  record('The requesting session cannot approve its own disposal',
    selfApprovalDenial?.status === 403 && selfApprovalDenial.path.endsWith('/approve'),
    `status=${selfApprovalDenial?.status} path=${selfApprovalDenial?.path}`);
  await waitForRow(page, 'Requested', 'Approval and execution queue');

  await librarian.expectDialogs(undefined); // irreversible confirm
  await clickRowButton(page, 'Requested', 'Withdraw', 'Approval and execution queue');
  await waitForNotice(page, 'Disposal request withdrawn.');
  const withdrawnRow = await rowDetail(page, 'Withdrawn', 'Approval and execution queue');
  record('Requester withdrawal is terminal and frees the asset',
    withdrawnRow.buttons.length === 0,
    `withdrawn row buttons=${JSON.stringify(withdrawnRow.buttons)}`);

  await librarian.expectDialogs(undefined, 'Browser E2E corrected method', undefined);
  await clickRowButton(page, ASSET_CODE, 'Request disposal');
  await waitForNotice(page, 'Disposal request created.');
  await waitForRow(page, 'Requested', 'Approval and execution queue');
  record('A corrected disposal request can be raised after withdrawal', true, 'chip=Requested');

  // --- work order: request + provoked denial ----------------------------
  await selectFirstOption(page, 'Branch', 'Request work');
  await setFormInput(page, 'Request work', 'Facility', FACILITY);
  await setFormInput(page, 'Request work', 'Description', 'Replace the filter cartridge');
  await submitForm(page, 'Request work');
  await waitForNotice(page, 'Work order requested.');
  await waitForRow(page, FACILITY, 'Work orders with evidence');
  record('Work order request is created through the canonical API', true, FACILITY);

  const denialsBeforeWorkApproval = librarian.observedDenials.length;
  await librarian.expectingDenial(async () => {
    await librarian.expectDialogs(undefined);
    await clickRowButton(page, FACILITY, 'Approve', 'Work orders with evidence');
    await waitForAlert(page, 'no active authority grants facilities.work_approve');
  });
  const workDenial = librarian.observedDenials[denialsBeforeWorkApproval];
  record('The requesting session cannot approve its own work order',
    workDenial?.status === 403 && workDenial.path.endsWith('/approve'),
    `status=${workDenial?.status} path=${workDenial?.path}`);

  // ------------------------------------------------------------------
  // Approver one: first disposal signature + independent work approval.
  // ------------------------------------------------------------------
  const approverOne = await session('e2e-library-approver-1');
  await approverOne.expectDialogs(undefined);
  await clickRowButton(approverOne.page, 'Requested', 'Approve', 'Approval and execution queue');
  await waitForNotice(approverOne.page, 'Disposal approval recorded.');
  const afterFirstSignature = await rowDetail(approverOne.page, 'Requested', 'Approval and execution queue');
  record('The first signature keeps the request unapproved and withdrawable',
    afterFirstSignature.buttons.includes('Withdraw') && !afterFirstSignature.buttons.includes('Execute'),
    `row="${afterFirstSignature.text.slice(0, 120)}" buttons=${JSON.stringify(afterFirstSignature.buttons)}`);

  await approverOne.expectDialogs(undefined);
  await clickRowButton(approverOne.page, FACILITY, 'Approve', 'Work orders with evidence');
  await waitForNotice(approverOne.page, 'Work order approved.');
  await waitForRow(approverOne.page, 'Approved', 'Work orders with evidence');
  record('Independent work-order approval transitions the order', true, 'chip=Approved');

  // ------------------------------------------------------------------
  // Approver two: second disposal signature.
  // ------------------------------------------------------------------
  const approverTwo = await session('e2e-library-approver-2');
  await approverTwo.expectDialogs(undefined);
  await clickRowButton(approverTwo.page, 'Requested', 'Approve', 'Approval and execution queue');
  await waitForNotice(approverTwo.page, 'Disposal approval recorded.');
  const afterSecondSignature = await rowDetail(approverTwo.page, 'Approved', 'Approval and execution queue');
  record('The second distinct signature approves the request for execution',
    afterSecondSignature.buttons.includes('Execute') && !afterSecondSignature.buttons.includes('Withdraw'),
    `buttons=${JSON.stringify(afterSecondSignature.buttons)}`);

  // ------------------------------------------------------------------
  // Librarian returns: execution belongs to the requesting session; the
  // approved work order is started and completed with evidence.
  // ------------------------------------------------------------------
  await page.goto(`${BASE}/library`, { waitUntil: 'networkidle2' });
  await waitIdle(page);

  await librarian.expectDialogs(undefined, undefined); // disposal-date prompt + irreversible confirm
  await clickRowButton(page, 'Approved', 'Execute', 'Approval and execution queue');
  await waitForNotice(page, 'Disposal executed and recorded.');
  await waitForRow(page, 'Completed', 'Approval and execution queue');
  await waitForRow(page, 'Disposed');
  record('The requesting session executes the fully approved disposal', true, 'request=Completed asset=Disposed');

  await librarian.expectDialogs(undefined); // start confirm
  await clickRowButton(page, FACILITY, 'Start', 'Work orders with evidence');
  await waitForNotice(page, 'Work order started.');
  await waitForRow(page, 'In Progress', 'Work orders with evidence');
  await librarian.expectDialogs('evidence/browser/hvac-1', undefined); // evidence prompt + confirm
  await clickRowButton(page, FACILITY, 'Complete', 'Work orders with evidence');
  await waitForNotice(page, 'Work order completed with evidence.');
  await waitForRow(page, 'Completed', 'Work orders with evidence');
  record('Work order completes with evidence through the UI', true, 'chip=Completed');

  // ------------------------------------------------------------------
  // Cross-session invariants.
  // ------------------------------------------------------------------
  const unexpectedMutations = resourceMutations.filter((mutation) => !LIBRARY_MUTATION.test(mutation.path));
  record('Every observed Resources mutation is a canonical POST route',
    resourceMutations.length > 0 && unexpectedMutations.length === 0,
    `${resourceMutations.length} mutations observed; unexpected=${JSON.stringify(unexpectedMutations.slice(0, 3))}`);

  for (const sessionRecord of [librarian, approverOne, approverTwo]) {
    checkSession(sessionRecord);
  }
} catch (error) {
  console.error('\nLIBRARY BROWSER E2E FAILURE DIAGNOSTICS');
  console.error(error instanceof Error ? error.stack ?? `${error.name}: ${error.message}` : String(error));
  for (const context of contexts) {
    for (const page of await context.pages()) {
      try {
        const snapshot = await page.evaluate(() => ({
          url: window.location.href,
          notice: document.querySelector('.notice')?.textContent || null,
          alert: document.querySelector('.alert')?.textContent || null,
          pageText: (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 3_000),
        }));
        console.error(`Page snapshot: ${JSON.stringify(snapshot)}`);
      } catch {
        // A crashed page yields no snapshot; the error above is the evidence.
      }
    }
  }
  console.error(`Console errors (${allConsoleErrors.length}): ${allConsoleErrors.slice(0, 5).join(' | ') || 'none'}`);

  const summary = [
    '## Library browser E2E failure',
    '',
    '```',
    String(error instanceof Error ? `${error.name}: ${error.message}\n${error.stack ?? ''}` : error).slice(0, 2_500),
    '```',
    '',
    `Records completed: ${results.length}`,
    '',
    '| Record | Result | Detail |',
    '|---|---|---|',
    ...results.map((entry) => `| ${entry.name} | ${entry.pass ? 'PASS' : 'FAIL'} | ${String(entry.detail ?? '').slice(0, 160).replace(/\|/g, '/')} |`),
    '',
    `Console errors: ${allConsoleErrors.slice(0, 5).join(' | ') || 'none'}`,
  ];
  writeStepSummary(summary.join('\n'));
  throw error;
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nLIBRARY BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);

// Always publish the record table to the workflow step summary so the run
// page shows the journey's outcome even where log downloads are restricted.
writeStepSummary([
  `### Library browser E2E: ${results.length - failed}/${results.length} passed`,
  '',
  '| Record | Result | Detail |',
  '|---|---|---|',
  ...results.map((entry) => `| ${entry.name} | ${entry.pass ? 'PASS' : 'FAIL'} | ${String(entry.detail ?? '').slice(0, 160).replace(/\|/g, '/')} |`),
].join('\n'));

process.exit(failed === 0 ? 0 : 1);
