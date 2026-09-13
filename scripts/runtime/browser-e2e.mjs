/**
 * Real browser end-to-end verification.
 *
 * Required environment:
 *   BASE_URL=http://127.0.0.1:8000
 *   E2E_USERNAME=<provisioned test account>
 *   E2E_PASSWORD=<provisioned test account password>
 *   CHROMIUM_PATH=/path/to/chromium
 *
 * No credentials are embedded in source. This suite intentionally avoids a
 * wrong-password attempt because login throttling is part of the security
 * contract and a browser smoke test must not consume the valid-login budget.
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

const CONSOLES = [
  ['/workspace', 'Workspace'],
  ['/library', 'Library'],
  ['/documents', 'Documents'],
  ['/students', 'Students'],
  ['/academic', 'Academic'],
  ['/teachers', 'Teacher'],
  ['/crm', 'CRM'],
  ['/management', 'Management'],
  ['/reporting', 'Reporting'],
  ['/finance', 'Finance'],
  ['/hr', 'HR'],
  ['/payroll', 'Payroll'],
  ['/placement', 'Placement'],
  ['/access', 'Access'],
  ['/organization', 'Organization'],
  ['/identity', 'Identity'],
  ['/privacy', 'Privacy'],
  ['/governance/audit', 'Audit'],
];

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

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

// Ubuntu's chromium package is a Snap wrapper. A runner can finish package
// installation while its first confined browser start is still warming, so retain
// a bounded launch allowance instead of treating that runner-only startup lag as
// a product failure (Puppeteer's implicit default is only 30 seconds).
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
  // The employee UI consumes the one composed workspace contract. Its work
  // and notification projections are intentionally not redundant direct API reads.
  const workspaceProjectionResponses = [];

  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(`${page.url()} :: ${message.text().slice(0, 200)}`);
  });
  page.on('pageerror', (error) => consoleErrors.push(`${page.url()} :: ${String(error.message).slice(0, 200)}`));
  page.on('requestfailed', (request) => failedRequests.push(`${request.url().slice(0, 160)} :: ${request.failure()?.errorText ?? 'request failed'}`));
  page.on('response', (response) => {
    const url = response.url();
    if (url.includes('/api/v1/')) {
      apiCalls.push({ url: url.replace(BASE, ''), status: response.status() });
      if (new URL(url).pathname === '/api/v1/workspace' && response.request().method() === 'GET') {
        workspaceProjectionResponses.push(response.json()
          .then((responseBody) => ({
            hasWork: Array.isArray(responseBody?.data?.work?.items) && Number.isInteger(responseBody?.data?.work?.count),
            hasNotifications: Array.isArray(responseBody?.data?.notifications?.items)
              && Number.isInteger(responseBody?.data?.notifications?.unread_count)
              && typeof responseBody?.data?.notifications?.status === 'string',
          }))
          .catch(() => ({ hasWork: false, hasNotifications: false })));
      }
    }
    if (response.status() >= 400 && !url.includes('favicon')) failedRequests.push(`${response.status()} ${url.replace(BASE, '').slice(0, 140)}`);
  });

  await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
  record('Unauthenticated workspace redirects to login', page.url().includes('/login'), `landed on ${page.url().replace(BASE, '')}`);

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const authenticated = !page.url().includes('/login');
  record('Valid credentials establish an authenticated session', authenticated, `landed on ${page.url().replace(BASE, '')}`);
  if (!authenticated) throw new Error('browser E2E authentication failed; verify the provisioned test account and environment variables');

  for (const [path, expected] of CONSOLES) {
    const beforeErrors = consoleErrors.length;
    const beforeFailures = failedRequests.length;
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle2' });
    const mounted = await page.evaluate(() => Array.from(document.querySelectorAll('[id$="-console"], #react-console, #app, main')).some((node) => node.childElementCount > 0));
    const text = (await page.evaluate(() => document.body.innerText || '')).replace(/\s+/g, ' ');
    const matched = text.toLowerCase().includes(expected.toLowerCase());
    record(`Console ${path} renders without browser errors`, mounted && matched && consoleErrors.length === beforeErrors && failedRequests.length === beforeFailures,
      `mounted=${mounted} matched=${matched} newConsoleErrors=${consoleErrors.length - beforeErrors} newFailedRequests=${failedRequests.length - beforeFailures}`);
  }

  const workspaceProjections = await Promise.all(workspaceProjectionResponses);
  const hasNotificationsProjection = workspaceProjections.some(({ hasNotifications }) => hasNotifications);
  const hasWorkProjection = workspaceProjections.some(({ hasWork }) => hasWork);
  record('Workspace receives canonical communication and work projections', hasNotificationsProjection && hasWorkProjection,
    `workspaceResponses=${workspaceProjections.length} notifications=${hasNotificationsProjection} workItems=${hasWorkProjection}`);

  const badCalls = apiCalls.filter((call) => call.status >= 400);
  record('Frontend uses canonical API successfully', apiCalls.length > 0 && badCalls.length === 0,
    `${apiCalls.length} API calls observed; ${badCalls.length} failed`);
  record('No uncaught browser console errors', consoleErrors.length === 0, consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none');
  record('No failed network requests', failedRequests.length === 0, failedRequests.length ? failedRequests.slice(0, 3).join(' | ') : 'none');

  await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
  const logout = await page.$('form[action*="logout"] button, button[data-action="logout"]');
  if (!logout) {
    record('Sign-out control is present', false, 'no logout control found');
  } else {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
      logout.click(),
    ]);
    await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
    record('Sign-out ends the authenticated session', page.url().includes('/login'), `landed on ${page.url().replace(BASE, '')}`);
  }
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nBROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
