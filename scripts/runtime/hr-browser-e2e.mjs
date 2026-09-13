/**
 * HR console browser E2E verification.
 *
 * Verifies that the People & HR console mounts, renders its summary
 * metrics, loads the workspace data from the canonical API, and
 * presents the expected lifecycle management areas without browser
 * errors or failed API calls.
 *
 * Required environment:
 *   BASE_URL=http://127.0.0.1:8000
 *   E2E_USERNAME=<provisioned test account with hr.employ capability>
 *   E2E_PASSWORD=<provisioned test account password>
 *   CHROMIUM_PATH=/path/to/chromium
 *
 * No credentials are embedded in source.
 */
import puppeteer from 'puppeteer-core';

const required = (name) => {
  const value = process.env[name]?.trim();
  if (!value) {
    console.error(`Missing required environment variable: ${name}`);
    process.exit(1);
  }
  return value;
};

const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8000';
const USER = required('E2E_USERNAME');
const PASS = required('E2E_PASSWORD');
const EXECUTABLE = required('CHROMIUM_PATH');

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

// Health check
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 10_000);
let health;
try {
  health = await fetch(`${BASE}/health`, { signal: controller.signal });
} catch (error) {
  console.error(`Cannot reach ${BASE}/health: ${error instanceof Error ? error.message : String(error)}`);
  process.exit(1);
} finally {
  clearTimeout(timeout);
}

record('Health endpoint is reachable', health.ok, `HTTP ${health.status}`);
if (!health.ok) process.exit(1);

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  headless: true,
  timeout: 90_000,
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  page.setDefaultNavigationTimeout(30_000);
  page.setDefaultTimeout(10_000);

  const consoleErrors = [];
  const failedRequests = [];
  const apiCalls = [];

  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(`${page.url()} :: ${message.text().slice(0, 200)}`);
  });
  page.on('pageerror', (error) => consoleErrors.push(`${page.url()} :: ${String(error.message).slice(0, 200)}`));
  page.on('requestfailed', (request) => failedRequests.push(`${request.url().slice(0, 160)} :: ${request.failure()?.errorText ?? 'request failed'}`));
  page.on('response', (response) => {
    const url = response.url();
    if (url.includes('/api/v1/')) {
      apiCalls.push({ url: url.replace(BASE, ''), status: response.status() });
    }
    if (response.status() >= 400 && !url.includes('favicon')) {
      failedRequests.push(`${response.status()} ${url.replace(BASE, '').slice(0, 140)}`);
    }
  });

  // 1. Login
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const authenticated = !page.url().includes('/login');
  record('Authentication succeeds', authenticated, `landed on ${page.url().replace(BASE, '')}`);
  if (!authenticated) throw new Error('HR browser E2E authentication failed');

  // 2. Navigate to HR console
  await page.goto(`${BASE}/hr`, { waitUntil: 'networkidle2' });

  // Wait for the React app to mount
  const settled = await page.waitForFunction(
    () => {
      const main = document.querySelector('main[id="workspace-main"]');
      return main && main.childElementCount > 0;
    },
    { timeout: 15_000, polling: 150 },
  ).then(() => true).catch(() => false);

  record('HR console mounts', settled, `React app rendered: ${settled}`);

  // 3. Verify page content includes expected HR areas
  const bodyText = await page.evaluate(() => document.body.innerText || '');
  const hasPeopleSection = bodyText.toLowerCase().includes('people');
  const hasEmploymentSection = bodyText.toLowerCase().includes('employment');
  const hasHRTitle = bodyText.toLowerCase().includes('hr') || bodyText.toLowerCase().includes('people');
  record('HR console shows People & HR heading', hasHRTitle && hasPeopleSection,
    `found People: ${hasPeopleSection}, Employment: ${hasEmploymentSection}`);

  // 4. Verify summary metrics are rendered
  const metricCount = await page.evaluate(() => {
    const metrics = document.querySelectorAll('.metric');
    return metrics.length;
  });
  record('HR summary metrics are rendered', metricCount >= 3,
    `found ${metricCount} metric panels`);

  // 5. Verify tab navigation exists
  const tabCount = await page.evaluate(() => {
    const tabs = document.querySelectorAll('[role="tab"]');
    return tabs.length;
  });
  record('HR tab navigation is present', tabCount >= 4,
    `found ${tabCount} tabs`);

  // 6. Navigate through tabs
  const tabs = ['employment', 'contracts', 'leave', 'scales'];
  for (const tabName of tabs) {
    const tabButton = await page.$(`button[id="hr-tab-${tabName}"]`);
    if (tabButton) {
      await tabButton.click();
      // Brief wait for content to render
      await new Promise((resolve) => setTimeout(resolve, 300));
      const panelVisible = await page.evaluate((name) => {
        const panel = document.querySelector(`[id="hr-panel-${name}"]`);
        return panel !== null || document.querySelector('section.panel') !== null;
      }, tabName);
      record(`HR tab "${tabName}" is navigable`, panelVisible, `panel visible: ${panelVisible}`);
    } else {
      record(`HR tab "${tabName}" button exists`, false, `button#hr-tab-${tabName} not found`);
    }
  }

  // 7. Verify the workspace API was called
  const hrApiCalls = apiCalls.filter((call) => call.url.includes('/hr/'));
  const hrWorkspaceCall = hrApiCalls.find((call) => call.url.includes('/hr/workspace'));
  record('HR workspace API was called', hrWorkspaceCall !== undefined,
    `API calls: ${hrApiCalls.length}, workspace: ${hrWorkspaceCall?.status ?? 'not called'}`);

  // 8. Check for console errors and failed requests
  const badCalls = apiCalls.filter((call) => call.status >= 400);
  record('No uncaught browser errors', consoleErrors.length === 0,
    consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none');
  record('No failed network requests', failedRequests.length === 0,
    failedRequests.length ? failedRequests.slice(0, 3).join(' | ') : 'none');
  record('API calls succeed', badCalls.length === 0,
    `${apiCalls.length} calls, ${badCalls.length} failed${badCalls.length ? ': ' + badCalls.map((c) => `${c.status} ${c.url}`).join(', ').slice(0, 200) : ''}`);

} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nHR BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
