/**
 * Real browser end-to-end verification.
 *
 * Drives a genuine Chromium instance against a running application: it signs
 * in through the real login form, loads each authenticated console, and
 * records actual network traffic and console output.
 *
 * jsdom, static bundle inspection and HTTP-only checks are explicitly NOT
 * accepted as browser evidence; this script exists so that claim can be made
 * from observation.
 *
 * Usage:
 *   BASE_URL=http://127.0.0.1:8000 \
 *   E2E_USERNAME=... E2E_PASSWORD=... \
 *   CHROMIUM_PATH=/tmp/chromium \
 *   node scripts/runtime/browser-e2e.mjs
 */
import puppeteer from 'puppeteer-core';

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const USER = process.env.E2E_USERNAME ?? 'runtime.owner';
const PASS = process.env.E2E_PASSWORD ?? 'Runtime-Pass-12345';
const EXECUTABLE = process.env.CHROMIUM_PATH ?? '/tmp/chromium';

/** Authenticated console routes and the heading each must render. */
const CONSOLES = [
  { path: '/workspace', expect: 'Workspace' },
  { path: '/students', expect: 'Students' },
  { path: '/academic', expect: 'Academic' },
  { path: '/teachers', expect: 'Teacher' },
  { path: '/crm', expect: 'CRM' },
  { path: '/management', expect: 'Management' },
  { path: '/reporting', expect: 'Reporting' },
  { path: '/finance', expect: 'Finance' },
  { path: '/hr', expect: 'HR' },
  { path: '/payroll', expect: 'Payroll' },
  { path: '/placement', expect: 'Placement' },
  { path: '/access', expect: 'Access' },
  { path: '/organization', expect: 'Organization' },
  { path: '/identity', expect: 'Identity' },
];

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--single-process'],
  headless: true,
});

try {
  const page = await browser.newPage();

  // Collect real browser-side signals for the whole session.
  const consoleErrors = [];
  const failedRequests = [];
  const apiCalls = [];

  page.on('console', (m) => {
    if (m.type() === 'error') consoleErrors.push(`${page.url()} :: ${m.text().slice(0, 160)}`);
  });
  page.on('pageerror', (e) => consoleErrors.push(`${page.url()} :: ${String(e.message).slice(0, 160)}`));
  page.on('requestfailed', (r) => failedRequests.push(`${r.url().slice(0, 120)} :: ${r.failure()?.errorText}`));
  page.on('response', (r) => {
    const u = r.url();
    if (u.includes('/api/v1/')) apiCalls.push({ url: u.replace(BASE, ''), status: r.status() });
    if (r.status() >= 400 && !u.includes('favicon')) {
      failedRequests.push(`${r.status()} ${u.replace(BASE, '').slice(0, 110)}`);
    }
  });

  // --- 1. Unauthenticated access is redirected to the login form ----------
  await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
  const redirected = page.url().includes('/login');
  record(
    'Unauthenticated console access redirects to login',
    redirected,
    `landed on ${page.url().replace(BASE, '')}`
  );

  // --- 2. Wrong credentials are rejected ----------------------------------
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', 'definitely-the-wrong-password');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const stillOnLogin = page.url().includes('/login');
  record(
    'Invalid credentials are rejected by the server',
    stillOnLogin,
    `still on ${page.url().replace(BASE, '')} after a wrong password`
  );

  // --- 3. Real sign-in ----------------------------------------------------
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const authenticated = !page.url().includes('/login');
  record(
    'Valid credentials establish an authenticated session',
    authenticated,
    `landed on ${page.url().replace(BASE, '')}`
  );

  if (!authenticated) {
    throw new Error('authentication failed; console verification cannot proceed');
  }

  // --- 4. Every console renders a live React tree -------------------------
  for (const { path, expect } of CONSOLES) {
    const before = consoleErrors.length;
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle2' });

    // The React root must actually paint, not just return 200 HTML.
    const mounted = await page.evaluate(() => {
      const roots = Array.from(document.querySelectorAll('[id$="-console"], #app, main'));
      return roots.some((r) => r.childElementCount > 0);
    });
    const text = (await page.evaluate(() => document.body.innerText || '')).replace(/\s+/g, ' ');
    const matched = text.toLowerCase().includes(expect.toLowerCase());
    const newErrors = consoleErrors.length - before;

    record(
      `Console ${path} renders in the browser`,
      mounted && matched && newErrors === 0,
      `mounted=${mounted} matched="${expect}"=${matched} newConsoleErrors=${newErrors} text="${text.slice(0, 60)}"`
    );
  }

  // --- 5. The frontend actually talked to the API -------------------------
  const okCalls = apiCalls.filter((c) => c.status < 400);
  const badCalls = apiCalls.filter((c) => c.status >= 400);
  record(
    'Frontend issues real API requests over the canonical transport',
    apiCalls.length > 0 && badCalls.length === 0,
    `${apiCalls.length} /api/v1 calls observed, ${okCalls.length} succeeded, ${badCalls.length} failed` +
      (badCalls.length ? ` -> ${badCalls.slice(0, 3).map((c) => `${c.status} ${c.url}`).join('; ')}` : '')
  );

  // --- 6. No browser console errors or failed requests --------------------
  record(
    'No uncaught console errors during the session',
    consoleErrors.length === 0,
    consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none'
  );
  record(
    'No failed network requests during the session',
    failedRequests.length === 0,
    failedRequests.length ? failedRequests.slice(0, 3).join(' | ') : 'none'
  );

  // --- 7. Sign-out returns to an unauthenticated state --------------------
  await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
  const logout = await page.$('form[action*="logout"] button, button[data-action="logout"]');
  if (logout) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
      logout.click(),
    ]);
    await page.goto(`${BASE}/workspace`, { waitUntil: 'networkidle2' });
    record('Sign-out ends the session', page.url().includes('/login'), `landed on ${page.url().replace(BASE, '')}`);
  } else {
    record('Sign-out control present', false, 'no logout control found on the workspace');
  }
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.pass).length;
console.log(`\nBROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
