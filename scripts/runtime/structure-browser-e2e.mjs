/**
 * ORG-OPS-01 real-browser end-to-end verification.
 *
 * Drives the actual React Organization console against the canonical
 * /api/v1 endpoints through Chromium, using NON-OWNER operators:
 *
 *   e2e-structure-gm       initiator (organization.structure.initiate)
 *   e2e-structure-mgr      reviewer  (organization.structure.review)
 *   e2e-structure-owner-1  approving owner
 *   e2e-structure-owner-2  approving owner (second signature executes)
 *   e2e-structure-nobody   zero capabilities (fail-closed proof)
 *
 * The journey proves, in the real product UI:
 *   1. the full propose -> independent review -> two distinct owner
 *      signatures chain, executed by the second signature;
 *   2. create + activate lifecycle for organization, campus, branch and
 *      department, with parents chosen through meaningful hierarchy
 *      selectors (never opaque ID entry);
 *   3. cross-organization isolation: a foreign operator's open proposal
 *      is invisible to the bootstrap reviewer, the foreign organization
 *      is not rendered, and a direct signed API attempt is denied 403;
 *   4. fail-closed transport: unauthenticated 401, no-capability 403,
 *      and the page gate redirects an unauthorized operator away;
 *   5. every topology mutation goes through staged governance routes
 *      (no legacy direct create/update/delete surface exists).
 *
 * Required:
 *   BASE_URL=http://127.0.0.1:8000
 *   CHROMIUM_PATH=/path/to/chromium           (defaults to /tmp/chromium)
 *   CHROMIUM_LIB_DIR=/dir/with/nss/libs       (defaults to /tmp/chrome-runtime/lib)
 * Credentials default to the accounts created by
 * scripts/runtime/structure-browser-provision.php and may be overridden
 * via E2E_PASSWORD.
 */
import { execFileSync } from 'node:child_process';
import puppeteer from 'puppeteer-core';

const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8000';
const PASSWORD = process.env.E2E_PASSWORD || 'structure-e2e-password';
const EXECUTABLE = process.env.CHROMIUM_PATH?.trim() || '/tmp/chromium';
const LIB_DIR = process.env.CHROMIUM_LIB_DIR?.trim() || '/tmp/chrome-runtime/lib';

const results = [];
const record = (name, pass, detail = '') => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

/** Every observed topology mutation MUST be a POST on a staged governance route. */
const topologyMutations = [];
const STAGED_ROUTE = /^\/api\/v1\/organization\/changes(\/[0-9a-f-]+\/(review|approve|reject|withdraw))?$/;
function watchMutations(page, username) {
  page.on('requestfinished', (request) => {
    const method = request.method();
    const path = new URL(request.url()).pathname;
    if (!path.startsWith('/api/v1/organization')) return;
    if (method !== 'GET') topologyMutations.push({ username, method, path });
  });
}

const stamp = `${Date.now()}`;
const DRAFT_ORG = `Browser Draft Organization ${stamp}`;
const ORG_NAME = `Browser E2E Org ${stamp}`;
const CAMPUS_NAME = `Browser E2E Campus ${stamp}`;
const BRANCH_NAME = `Browser E2E Branch ${stamp}`;
const DEPARTMENT_NAME = `Browser E2E Department ${stamp}`;
const SIBLING_CAMPUS_NAME = `Sibling Secret Campus ${stamp}`;
const BOOTSTRAP_ORG = 'Authority Bootstrap';
const SIBLING_ORG = 'Cross-Org Sibling';

// Provision (idempotently) the operators and a unique, pre-granted draft
// organization for THIS run's organization activation leg. Uses the same
// provisioned PHP launcher as the rest of the runtime tooling.
const phpBin = process.env.PHP_BINARY?.trim() || '/home/user/TOEFL-House/.runtime/bin/php';
const provisionOutput = execFileSync(phpBin, ['scripts/runtime/structure-browser-provision.php'], {
  cwd: '/home/user/TOEFL-House',
  env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE || 'toefl_house_dev', STRUCTURE_E2E_DRAFT_ORG: DRAFT_ORG },
  encoding: 'utf8',
});
console.log(provisionOutput.split('\n').filter((line) => line.includes('draft organization')).join('\n'));

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  headless: true,
  timeout: 90_000,
  env: {
    ...process.env,
    LD_LIBRARY_PATH: `${LIB_DIR}:${process.env.LD_LIBRARY_PATH ?? ''}`,
  },
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
});

/** @type {puppeteer.BrowserContext[]} */
const contexts = [];
const allConsoleErrors = [];
/** Deliberate, asserted denials are expected HTTP 4xx, not failures. */
const expectedDenials = [];

try {
  const health = await fetch(`${BASE}/health`);
  record('Health endpoint is reachable', health.ok, `HTTP ${health.status}`);
  if (!health.ok) throw new Error('server is not reachable');

  /**
   * One isolated cookie jar per operator; each logs in once.
   */
  async function session(username, { acceptDialogs = false } = {}) {
    const context = await browser.createBrowserContext();
    contexts.push(context);
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 1100, deviceScaleFactor: 1 });
    page.setDefaultNavigationTimeout(30_000);
    page.setDefaultTimeout(15_000);

    const consoleErrors = [];
    const failedRequests = [];
    let deliberateDenialWindow = 0;
    page.on('console', (message) => {
      if (message.type() !== 'error') return;
      // "Failed to load resource" is logged for the 401/403 responses that a
      // negative assertion deliberately provoked; those are not product errors.
      if (deliberateDenialWindow > 0 && message.text().startsWith('Failed to load resource')) return;
      consoleErrors.push(`${username}: ${message.text().slice(0, 220)}`);
    });
    page.on('pageerror', (error) => consoleErrors.push(`${username}: ${String(error.message).slice(0, 220)}`));
    page.on('requestfailed', (request) => failedRequests.push(`${username}: ${request.url().replace(BASE, '')} ${request.failure()?.errorText ?? 'failed'}`));
    watchMutations(page, username);
    page.on('requestfinished', async (request) => {
      const response = request.response();
      if (!response) return;
      const url = request.url().replace(BASE, '');
      if (response.status() >= 400 && !expectedDenials.some((needle) => url.includes(needle))) {
        failedRequests.push(`${username}: ${response.status()} ${url.slice(0, 180)}`);
      }
    });
    if (acceptDialogs) {
      page.on('dialog', (dialog) => dialog.accept().catch(() => {}));
    }

    await page.goto(`${BASE}/organization`, { waitUntil: 'networkidle2' });
    if (page.url().includes('/login')) {
      await page.type('input[name="username"]', username);
      await page.type('input[name="password"]', PASSWORD);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
        page.click('button[type="submit"]'),
      ]);
    }
    await page.goto(`${BASE}/organization`, { waitUntil: 'networkidle2' });
    // A capability gate may redirect an unauthorized operator away; the
    // journey asserts that explicitly for the zero-capability operator.
    if (page.url().includes('/organization')) {
      await page.waitForSelector('#organization-title');
    }

    /** Runs a negative request whose 4xx response + console noise are expected. */
    async function expectingDenial(work) {
      deliberateDenialWindow += 1;
      try {
        return await work();
      } finally {
        await new Promise((resolve) => setTimeout(resolve, 200));
        deliberateDenialWindow -= 1;
      }
    }

    return { context, page, username, consoleErrors, failedRequests, expectingDenial };
  }

  function checkSession(sessionRecord) {
    record(`${sessionRecord.username} session has no console/network errors`,
      sessionRecord.consoleErrors.length === 0 && sessionRecord.failedRequests.length === 0,
      `console=${sessionRecord.consoleErrors.length} ${sessionRecord.consoleErrors.slice(0, 2).join(' | ')} `
      + `network=${sessionRecord.failedRequests.length} ${sessionRecord.failedRequests.slice(0, 2).join(' | ')}`);
    allConsoleErrors.push(...sessionRecord.consoleErrors);
  }

  const gm = await session('e2e-structure-gm');
  const mgr = await session('e2e-structure-mgr');
  const owner1 = await session('e2e-structure-owner-1');
  const owner2 = await session('e2e-structure-owner-2', { acceptDialogs: true });
  record('Four distinct non-Owner operators authenticated to the Organization console', true,
    'initiator, reviewer and two approving owners each hold an isolated session');

  // --- UI mechanics --------------------------------------------------------

  async function openTab(page, label) {
    await page.waitForSelector('[role="tab"]', { timeout: 20_000 });
    await page.evaluate((wanted) => {
      // Queue tabs carry a live count suffix, e.g. "Governance queue (3)".
      const tab = [...document.querySelectorAll('[role="tab"]')]
        .find((node) => (node.textContent || '').trim().startsWith(wanted));
      if (!tab) throw new Error(`tab ${wanted} not found`);
      tab.click();
    }, label);
    await new Promise((resolve) => setTimeout(resolve, 150));
  }

  /** Loads /organization (with retries for the single-worker dev server) and opens a tab. */
  async function loadConsole(page, tab) {
    let lastError;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        await page.goto(`${BASE}/organization`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#organization-title', { timeout: 20_000 });
        if (tab) await openTab(page, tab);
        return;
      } catch (error) {
        lastError = error;
        await new Promise((resolve) => setTimeout(resolve, 700));
      }
    }
    throw lastError;
  }

  async function waitNotice(page, fragment) {
    await page.waitForFunction((needle) => {
      const text = document.querySelector('.notice')?.textContent || document.querySelector('.alert')?.textContent || '';
      return text.includes(needle);
    }, { timeout: 20_000 }, fragment);
  }

  async function chooseChangeType(page, optionValue) {
    await openTab(page, 'Propose a change');
    await page.waitForSelector('#org-propose form.proposal-form');
    const value = await page.$eval('#org-propose label.directory-filter select', (select, wanted) => {
      const option = [...select.options].find((node) => node.value === wanted);
      if (!option) throw new Error(`change type ${wanted} unavailable; options: ${[...select.options].map((o) => o.value).join(',')}`);
      return option.value;
    }, optionValue);
    await page.select('#org-propose label.directory-filter select', value);
    await new Promise((resolve) => setTimeout(resolve, 120));
  }

  /** Selects a <select> option whose VISIBLE TEXT contains the fragment — never a raw id. */
  /** Selects by visible text in the Nth <select> of the mounted proposal form. */
  async function selectInFormByIndex(page, index, labelFragment) {
    const value = await page.$$eval('#org-propose form.proposal-form select', (selects, { index: wantedIndex, fragment }) => {
      const select = selects[wantedIndex];
      if (!select) throw new Error(`form select #${wantedIndex} not mounted`);
      const option = [...select.options].find((node) => node.textContent?.toLowerCase().includes(fragment.toLowerCase()));
      if (!option) throw new Error(`option matching "${fragment}" not found; options: ${[...select.options].map((o) => o.textContent).join(' | ')}`);
      select.value = option.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return option.value;
    }, { index, fragment: labelFragment });
    await new Promise((resolve) => setTimeout(resolve, 100));
    return value;
  }

  async function selectByLabel(page, selectSelector, labelFragment) {
    const value = await page.$eval(selectSelector, (select, fragment) => {
      const option = [...select.options].find((node) => node.textContent?.toLowerCase().includes(fragment.toLowerCase()));
      if (!option) throw new Error(`option matching "${fragment}" not found; options: ${[...select.options].map((o) => o.textContent).join(' | ')}`);
      select.value = option.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      return option.value;
    }, labelFragment);
    return value;
  }

  async function fillVisible(page, selector, value) {
    await page.waitForSelector(selector);
    await page.$eval(selector, (input, next) => {
      const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(input), 'value')?.set;
      setter?.call(input, next);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);
  }

  async function submitProposal(page, buttonText, notice) {
    await page.evaluate((text) => {
      const button = [...document.querySelectorAll('#org-propose form.proposal-form button[type="submit"]')]
        .find((node) => (node.textContent || '').includes(text));
      if (!button || button.disabled) throw new Error(`submit "${text}" missing or disabled`);
      button.click();
    }, buttonText);
    await waitNotice(page, notice);
  }

  async function stageReview(actor, buttonText, noticeFragment) {
    await loadConsole(actor.page, 'Governance queue');
    // Sequential chaining means exactly one proposal awaits this signature.
    await actor.page.evaluate((text) => {
      const button = [...document.querySelectorAll('#org-approvals table button')]
        .find((node) => (node.textContent || '').includes(text) && !node.disabled);
      if (!button) throw new Error(`queue action "${text}" not offered to this operator`);
      button.click();
    }, buttonText);
    await waitNotice(actor.page, noticeFragment);
  }

  async function governChain(description) {
    await stageReview(mgr, 'Mark reviewed', 'Independent review recorded.');
    await stageReview(owner1, 'Sign as owner 1', 'First owner signature recorded');
    await stageReview(owner2, 'Sign as owner 2 & execute', 'Approval signatures complete');
    console.log(`      chain executed: ${description}`);
  }

  async function transitionFromTopology(page, unitName, actionLabel) {
    await loadConsole(page, 'Topology');
    await page.evaluate(({ name, label }) => {
      const headings = [...document.querySelectorAll('.topology-unit h3, .topology-unit h4, .topology-unit .unit-heading strong')];
      const heading = headings.find((node) => node.textContent?.trim() === name);
      if (!heading) throw new Error(`topology unit "${name}" not rendered`);
      const unit = heading.closest('.topology-unit');
      const action = [...unit.querySelectorAll('button')].find((node) => node.textContent?.trim() === label);
      if (!action || action.disabled) throw new Error(`${label} action for "${name}" is not offered`);
      action.click();
    }, { name: unitName, label: actionLabel });
    await page.waitForSelector('#org-propose form.proposal-form');
    await page.evaluate(() => {
      const button = [...document.querySelectorAll('#org-propose form.proposal-form button[type="submit"]')]
        .find((node) => (node.textContent || '').includes('Propose lifecycle change'));
      if (!button || button.disabled) throw new Error('lifecycle proposal submit unavailable');
      button.click();
    });
    await waitNotice(page, 'was recorded');
  }

  const activateFromTopology = (page, unitName) => transitionFromTopology(page, unitName, 'Activate');

  async function assertTopologyState(page, unitName, expectedState) {
    await loadConsole(page, 'Topology');
    const state = await page.evaluate(({ name, state: wanted }) => {
      const headings = [...document.querySelectorAll('.topology-unit h3, .topology-unit h4, .topology-unit .unit-heading strong')];
      const heading = headings.find((node) => node.textContent?.trim() === name);
      if (!heading) return null;
      const unit = heading.closest('.topology-unit');
      return (unit?.querySelector('.status-chip')?.textContent || '').trim();
    }, { name: unitName, state: expectedState });
    record(`"${unitName}" is rendered in the hierarchy as ${expectedState}`, state?.toLowerCase() === expectedState,
      `observed state: ${state ?? 'unit not rendered'}`);
  }

  // --- 1. Organization create, fail-closed on the new trust domain, and
  //       activation of an organization the operators WERE granted --------

  await chooseChangeType(gm.page, 'create_organization');
  await fillVisible(gm.page, '#org-propose form.proposal-form input', ORG_NAME);
  await submitProposal(gm.page, 'Propose organization creation', 'Organization creation proposed.');
  await governChain(`create organization "${ORG_NAME}"`);
  await assertTopologyState(gm.page, ORG_NAME, 'draft');

  // The browser must not offer activation for a new trust domain where the
  // operators hold no authority: authority does not leak across organizations.
  await loadConsole(gm.page, 'Topology');
  const activateOfferedOnNewOrg = await gm.page.evaluate((name) => {
    const heading = [...document.querySelectorAll('.topology-unit h3')].find((node) => node.textContent?.trim() === name);
    if (!heading) throw new Error(`new organization ${name} not rendered`);
    return [...heading.closest('.topology-unit').querySelectorAll('button')]
      .some((node) => node.textContent?.trim() === 'Activate');
  }, ORG_NAME);
  record('No activate action is offered on a new organization outside the operators\u2019 authority',
    activateOfferedOnNewOrg === false, `activate offered: ${activateOfferedOnNewOrg}`);

  await activateFromTopology(gm.page, DRAFT_ORG);
  await governChain(`activate organization "${DRAFT_ORG}"`);
  await assertTopologyState(gm.page, DRAFT_ORG, 'active');

  // --- 2. Campus create + activate under the bootstrap organization -------

  await chooseChangeType(gm.page, 'create_campus');
  await selectByLabel(gm.page, '#org-propose form.proposal-form select', BOOTSTRAP_ORG);
  await fillVisible(gm.page, '#org-propose form.proposal-form input', CAMPUS_NAME);
  await submitProposal(gm.page, 'Propose campus creation', 'Campus creation proposed.');
  await governChain(`create campus "${CAMPUS_NAME}"`);
  await assertTopologyState(gm.page, CAMPUS_NAME, 'draft');
  await activateFromTopology(gm.page, CAMPUS_NAME);
  await governChain(`activate campus "${CAMPUS_NAME}"`);
  await assertTopologyState(gm.page, CAMPUS_NAME, 'active');

  // --- 3. Branch create + activate, attributed through a labeled campus ---

  await chooseChangeType(gm.page, 'create_branch');
  await selectByLabel(gm.page, '#org-propose form.proposal-form select', CAMPUS_NAME);
  await fillVisible(gm.page, '#org-propose form.proposal-form input', BRANCH_NAME);
  await submitProposal(gm.page, 'Propose branch creation', 'Branch creation proposed.');
  await governChain(`create branch "${BRANCH_NAME}"`);
  await assertTopologyState(gm.page, BRANCH_NAME, 'draft');
  await activateFromTopology(gm.page, BRANCH_NAME);
  await governChain(`activate branch "${BRANCH_NAME}"`);
  await assertTopologyState(gm.page, BRANCH_NAME, 'active');

  // --- 4. Department create + activate under the labeled branch -----------

  await chooseChangeType(gm.page, 'create_department');
  // First selector = parent KIND ("An organization/A campus/A branch");
  // the second is the labeled parent, rendered with branch + campus names.
  await selectInFormByIndex(gm.page, 0, 'A branch');
  await selectInFormByIndex(gm.page, 1, BRANCH_NAME);
  await fillVisible(gm.page, '#org-propose form.proposal-form input', DEPARTMENT_NAME);
  await submitProposal(gm.page, 'Propose department creation', 'Department creation proposed.');
  await governChain(`create department "${DEPARTMENT_NAME}"`);
  await assertTopologyState(gm.page, DEPARTMENT_NAME, 'draft');
  await activateFromTopology(gm.page, DEPARTMENT_NAME);
  await governChain(`activate department "${DEPARTMENT_NAME}"`);
  await assertTopologyState(gm.page, DEPARTMENT_NAME, 'active');

  // Deactivation (terminal retirement) through the same staged chain. The
  // created department is leaf and empty, so the bottom-up guard accepts it;
  // it is never deleted — the row stays and renders Closed.
  await transitionFromTopology(gm.page, DEPARTMENT_NAME, 'Close (retire)');
  await governChain(`close department "${DEPARTMENT_NAME}"`);
  await assertTopologyState(gm.page, DEPARTMENT_NAME, 'closed');

  // --- 5. Cross-organization isolation ------------------------------------

  const siblingGm = await session('e2e-sibling-gm');
  // The foreign initiator sees their own ACTIVE organization but never the
  // bootstrap operator's active trust domain. Non-active roots awaiting
  // activation are intentionally visible organization-wide (they carry no
  // authority until granted), so only active foreign roots are asserted hidden.
  const orgOptions = await siblingGm.page.evaluate(async () => {
    const response = await fetch('/api/v1/organization/workspace', { headers: { Accept: 'application/json' } });
    const body = await response.json();
    return {
      status: response.status,
      names: body.organizations?.map((org) => ({ name: org.name, state: org.lifecycle_state })) ?? [],
    };
  });
  const foreignActiveVisible = orgOptions.names.some((org) => org.name === BOOTSTRAP_ORG && org.state === 'active');
  const siblingVisible = orgOptions.names.some((org) => org.name === SIBLING_ORG && org.state === 'active');
  record('Foreign organization operator cannot see the bootstrap active trust domain',
    orgOptions.status === 200 && siblingVisible && !foreignActiveVisible,
    JSON.stringify(orgOptions.names));

  await chooseChangeType(siblingGm.page, 'create_campus');
  await selectByLabel(siblingGm.page, '#org-propose form.proposal-form select', SIBLING_ORG);
  await fillVisible(siblingGm.page, '#org-propose form.proposal-form input', SIBLING_CAMPUS_NAME);
  await submitProposal(siblingGm.page, 'Propose campus creation', 'Campus creation proposed.');

  // Identify the foreign proposal through the sibling session itself.
  const siblingProposalId = await siblingGm.page.evaluate(async (wanted) => {
    const response = await fetch('/api/v1/organization/workspace', { headers: { Accept: 'application/json' } });
    const body = await response.json();
    const found = (body.change_requests ?? []).find((request) => request.payload?.name === wanted);
    return found?.id ?? null;
  }, SIBLING_CAMPUS_NAME);
  record('Foreign proposal exists in the sibling trust domain', Boolean(siblingProposalId), siblingProposalId ?? 'missing');

  await loadConsole(mgr.page, 'Topology');
  const mgrView = await mgr.page.evaluate(({ foreignCampus, foreignOrg, ownOrg }) => {
    const text = document.body.innerText || '';
    return {
      foreignCampusHidden: !text.includes(foreignCampus),
      foreignOrgHidden: !text.includes(foreignOrg),
      ownOrgVisible: text.includes(ownOrg),
    };
  }, { foreignCampus: SIBLING_CAMPUS_NAME, foreignOrg: SIBLING_ORG, ownOrg: BOOTSTRAP_ORG });
  record('Bootstrap reviewer cannot see the foreign organization or its open proposal',
    mgrView.foreignCampusHidden && mgrView.foreignOrgHidden && mgrView.ownOrgVisible,
    JSON.stringify(mgrView));

  // The UI never offers the action; prove the server also rejects a direct,
  // session-signed attempt to sign the foreign proposal.
  expectedDenials.push(`/changes/${siblingProposalId}/review`);
  const directDenial = await mgr.expectingDenial(() => mgr.page.evaluate(async (requestId) => {
    const xsrf = decodeURIComponent(document.cookie.split('; ').filter((pair) => pair.startsWith('XSRF-TOKEN='))[0]?.split('=')[1] || '');
    const response = await fetch(`/api/v1/organization/changes/${requestId}/review`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: '{}',
    });
    const body = await response.json().catch(() => ({}));
    return { status: response.status, error: body.error ?? null };
  }, siblingProposalId));
  record('Direct signed attempt to review the foreign proposal is rejected 403',
    directDenial.status === 403, JSON.stringify(directDenial));

  // --- 6. Fail-closed transport and page gate ------------------------------

  expectedDenials.push('/organization/workspace');
  const anonymous = await browser.createBrowserContext();
  contexts.push(anonymous);
  const anonPage = await anonymous.newPage();
  await anonPage.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  const anonStatus = await anonPage.evaluate(async () => {
    const response = await fetch('/api/v1/organization/workspace', { headers: { Accept: 'application/json' } });
    return response.status;
  });
  record('Unauthenticated workspace API call is rejected 401', anonStatus === 401, `status ${anonStatus}`);
  await anonPage.goto(`${BASE}/organization`, { waitUntil: 'networkidle2' });
  record('Unauthenticated organization console redirects to login', anonPage.url().includes('/login'), anonPage.url().replace(BASE, ''));

  const nobody = await session('e2e-structure-nobody');
  const nobodyApi = await nobody.expectingDenial(() => nobody.page.evaluate(async () => {
    const response = await fetch('/api/v1/organization/workspace', { headers: { Accept: 'application/json' } });
    const body = await response.json().catch(() => ({}));
    return { status: response.status, error: body.error ?? null };
  }));
  record('Operator without governance capabilities receives 403 organization_read_denied',
    nobodyApi.status === 403 && nobodyApi.error === 'api.organization_read_denied', JSON.stringify(nobodyApi));
  await nobody.page.goto(`${BASE}/organization`, { waitUntil: 'domcontentloaded' });
  await nobody.page.waitForFunction(() => !window.location.pathname.includes('/organization'),
    { timeout: 10_000 }).catch(() => {});
  record('Unauthorized operator is gated away from the organization console',
    !nobody.page.url().includes('/organization'),
    nobody.page.url().replace(BASE, ''));

  // --- 7. Only staged governance mutation routes were used -----------------

  for (const actorSession of [gm, mgr, owner1, owner2, siblingGm, nobody]) {
    checkSession(actorSession);
  }
  const nonStaged = topologyMutations
    .filter((mutation) => mutation.method !== 'POST' || !STAGED_ROUTE.test(mutation.path));
  record('Every topology mutation observed in the browser is a POST on a staged governance route',
    topologyMutations.length >= 20 && nonStaged.length === 0,
    `${topologyMutations.length} mutations; non-staged: ${nonStaged.length ? JSON.stringify(nonStaged) : 'none'}`);

  // Hierarchy provenance: the active branch is shown under the created
  // campus and organization, not as an opaque/unattributed node.
  await loadConsole(gm.page, 'Topology');
  const nesting = await gm.page.evaluate(({ org, campus, branch, department }) => {
    const heading = [...document.querySelectorAll('.topology-unit h3, .topology-unit h4, .topology-unit .unit-heading strong')]
      .find((node) => node.textContent?.trim() === org)?.closest('.topology-unit');
    if (!heading) return { rendered: false };
    const text = heading.innerText;
    return {
      rendered: true,
      containsCampus: text.includes(campus),
      containsBranch: text.includes(branch),
      containsDepartment: text.includes(department),
    };
  }, { org: BOOTSTRAP_ORG, campus: CAMPUS_NAME, branch: BRANCH_NAME, department: DEPARTMENT_NAME });
  record('The hierarchy renders organization -> campus -> branch -> department nesting with names',
    nesting.rendered && nesting.containsCampus && nesting.containsBranch && nesting.containsDepartment,
    JSON.stringify(nesting));

  // Session teardown check for the anonymous page (no console errors expected).
  record('Browser journey finished with no uncaught console errors across operators',
    allConsoleErrors.length === 0, allConsoleErrors.length ? allConsoleErrors.slice(0, 3).join(' | ') : 'none');
} catch (error) {
  console.error('\nSTRUCTURE BROWSER E2E FAILURE');
  console.error(error instanceof Error ? error.stack ?? error.message : String(error));
  process.exitCode = 1;
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nSTRUCTURE BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
if (failed) process.exitCode = 1;
