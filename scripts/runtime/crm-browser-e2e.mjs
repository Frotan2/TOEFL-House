/**
 * CRM/Front Office browser E2E.
 *
 * Verifies the real React workspace against the canonical CRM API through
 * Chromium: authorized branch capture, visitor selection, stage transition,
 * immutable interaction append, follow-up scheduling and follow-up completion.
 *
 * Required environment:
 *   BASE_URL=http://127.0.0.1:8999
 *   E2E_USERNAME=<provisioned test account>
 *   E2E_PASSWORD=<provisioned test account password>
 *   CHROMIUM_PATH=/usr/bin/chromium
 */
import puppeteer from 'puppeteer-core';

const required = (name) => {
  const value = process.env[name]?.trim();
  if (!value) throw new Error(`Missing required environment variable: ${name}`);
  return value;
};

const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8999';
const USER = required('E2E_USERNAME');
const PASS = required('E2E_PASSWORD');
const EXECUTABLE = required('CHROMIUM_PATH');

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

const diagnosticLimit = 4_000;
const shorten = (value, limit = diagnosticLimit) => value.length <= limit ? value : `${value.slice(0, limit)}…`;

const consoleErrorDetail = async (message) => {
  const values = await Promise.all(message.args().slice(0, 4).map(async (argument) => {
    try {
      return await argument.evaluate((value) => {
        if (value instanceof Error) return `${value.name}: ${value.message}\n${value.stack ?? ''}`;
        if (value && typeof value === 'object') {
          return JSON.stringify(value, (_key, candidate) => candidate instanceof Error
            ? { name: candidate.name, message: candidate.message, stack: candidate.stack }
            : candidate);
        }
        return String(value);
      });
    } catch {
      return '<unavailable console argument>';
    }
  }));

  const location = message.location();
  const source = location.url ? ` @ ${location.url}:${location.lineNumber}:${location.columnNumber}` : '';
  return shorten(`${message.text()}${source}${values.length ? ` | ${values.map(String).join(' | ')}` : ''}`);
};

const emitFailureDiagnostics = async (page, error, consoleErrors, failedRequests) => {
  console.error('\nCRM BROWSER E2E FAILURE DIAGNOSTICS');
  console.error(error instanceof Error ? error.stack ?? `${error.name}: ${error.message}` : String(error));

  if (page) {
    try {
      const snapshot = await page.evaluate(() => {
        const root = document.getElementById('react-console');
        return {
          url: window.location.href,
          title: document.title,
          errorBoundaryVisible: document.querySelector('.error-boundary') !== null,
          root: root === null ? null : {
            view: root.getAttribute('data-view'),
            childElementCount: root.childElementCount,
            text: (root.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 4_000),
          },
          pageText: (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 4_000),
        };
      });
      console.error(`Page snapshot: ${JSON.stringify(snapshot)}`);
    } catch (snapshotError) {
      console.error(`Could not collect page snapshot: ${snapshotError instanceof Error ? snapshotError.message : String(snapshotError)}`);
    }
  }

  console.error(`Console errors (${consoleErrors.length}): ${consoleErrors.length ? consoleErrors.join(' | ') : 'none'}`);
  console.error(`Failed requests (${failedRequests.length}): ${failedRequests.length ? failedRequests.join(' | ') : 'none'}`);
};

const setReactControl = async (page, selector, value) => {
  const updated = await page.$eval(selector, (element, nextValue) => {
    const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(element), 'value')?.set;
    if (!setter) return false;
    setter.call(element, nextValue);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }, value);
  if (!updated) throw new Error(`Unable to set React control ${selector}`);
};

const setFormControl = async (page, submitText, selector, value) => {
  const updated = await page.evaluate(({ needle, controlSelector, nextValue }) => {
    const form = [...document.querySelectorAll('.crm-detail form')]
      .find((node) => (node.querySelector('button[type="submit"]')?.textContent || '').includes(needle));
    const element = form?.querySelector(controlSelector);
    if (!element) return false;
    const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(element), 'value')?.set;
    if (!setter) return false;
    setter.call(element, nextValue);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }, { needle: submitText, controlSelector: selector, nextValue: value });
  if (!updated) throw new Error(`Control ${selector} in form ${submitText} was not found`);
};

const setFormControlByLabel = async (page, submitText, labelText, value) => {
  const updated = await page.evaluate(({ needle, wantedLabel, nextValue }) => {
    const form = [...document.querySelectorAll('.crm-detail form')]
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
  if (!updated) throw new Error(`Label ${labelText} in form ${submitText} was not found`);
};

const submitFormByButton = async (page, text) => {
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

const clickButtonByText = async (page, text) => {
  const clicked = await page.evaluate((needle) => {
    const button = [...document.querySelectorAll('button')]
      .find((node) => (node.textContent || '').trim().includes(needle));
    if (!button) return false;
    button.click();
    return true;
  }, text);
  if (!clicked) throw new Error(`Button containing "${text}" was not found`);
};

const waitForNotice = async (page, expected) => {
  await page.waitForFunction((needle) => {
    const text = document.querySelector('.notice')?.textContent || '';
    return text.includes(needle);
  }, { timeout: 15_000 }, expected);
};

const waitForVisitor = async (page, expected) => {
  await page.waitForFunction((name) => [...document.querySelectorAll('.visitor-list .visitor-row strong')]
    .some((node) => node.textContent?.trim() === name), { timeout: 15_000 }, expected);
};

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

let page;
const consoleErrors = [];
const failedRequests = [];

try {
  page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  page.setDefaultNavigationTimeout(30_000);
  page.setDefaultTimeout(15_000);

  page.on('console', (message) => {
    if (message.type() === 'error') {
      void consoleErrorDetail(message).then((detail) => consoleErrors.push(detail));
    }
  });
  page.on('pageerror', (error) => consoleErrors.push(shorten(`${error.message}${error.stack ? `\n${error.stack}` : ''}`)));
  page.on('requestfailed', (request) => failedRequests.push(`${request.url()} :: ${request.failure()?.errorText || 'failed'}`));
  page.on('response', (response) => {
    const url = response.url();
    if (response.status() >= 400 && !url.includes('favicon')) failedRequests.push(`${response.status()} ${url.replace(BASE, '')}`);
  });

  await page.goto(`${BASE}/crm`, { waitUntil: 'networkidle2' });
  if (page.url().includes('/login')) {
    await page.type('input[name="username"]', USER);
    await page.type('input[name="password"]', PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
      page.click('button[type="submit"]'),
    ]);
  }
  record('CRM browser session is authenticated', !page.url().includes('/login'), page.url().replace(BASE, '') || '/');
  if (page.url().includes('/login')) throw new Error('CRM E2E login failed');

  await page.goto(`${BASE}/crm`, { waitUntil: 'networkidle2' });
  await page.waitForSelector('#crm-title');
  await page.waitForSelector('form.crm-capture');

  const branchState = await page.$eval('#crm-origin-branch', (select) => ({
    value: select.value,
    options: [...select.options].map((option) => option.value),
  }));
  record('CRM capture exposes an explicit branch contract', branchState.options.length >= 2 && branchState.options.some(Boolean), JSON.stringify(branchState));

  const captureInputs = await page.$$('.crm-capture input');
  if (captureInputs.length < 3) throw new Error('CRM capture form no longer exposes name/phone/email controls');
  const uniqueSuffix = `${Date.now()}`;
  const visitorName = `Browser CRM ${uniqueSuffix}`;
  await captureInputs[0].type(visitorName);
  await captureInputs[2].type(`crm-browser-${uniqueSuffix}@example.test`);
  const branchValue = branchState.options.find(Boolean);
  if (!branchValue) throw new Error('CRM capture has no selectable authorized origin branch');
  await page.select('#crm-origin-branch', branchValue);
  await page.click('.crm-capture button[type="submit"]');
  await waitForNotice(page, 'Visitor captured in the CRM source of truth.');
  record('Visitor capture succeeds through the canonical API', true, 'capture acknowledgement received');

  await waitForVisitor(page, visitorName);
  await clickButtonByText(page, visitorName);
  await page.waitForFunction((expected) => document.querySelector('#detail-title')?.textContent?.trim() === expected, {}, visitorName);
  record('Captured visitor can be reopened from the authorized directory', true, visitorName);

  const stageOptions = await page.$$eval('.stage-actions select option', (options) => options.map((option) => option.value));
  if (stageOptions.length > 1) {
    await page.select('.stage-actions select', stageOptions[1]);
    await waitForNotice(page, 'Visitor moved to');
    record('Visitor stage transition round-trips through the API', true, `${stageOptions[0]} -> ${stageOptions[1]}`);
  } else {
    throw new Error('no authorized next transition rendered');
  }

  await setFormControl(page, 'Append interaction', 'textarea', 'Browser E2E interaction evidence');
  await submitFormByButton(page, 'Append interaction');
  await waitForNotice(page, 'Interaction appended to the immutable timeline.');
  record('Interaction is appended to the CRM timeline', true, 'immutable interaction acknowledgement received');

  const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const isoLocal = new Date(tomorrow.getTime() - tomorrow.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
  await setFormControl(page, 'Schedule follow-up', 'input[type="datetime-local"]', isoLocal);
  await setFormControlByLabel(page, 'Schedule follow-up', 'Title', `Browser E2E follow-up ${uniqueSuffix}`);
  await submitFormByButton(page, 'Schedule follow-up');
  await waitForNotice(page, 'Follow-up scheduled in the CRM source of truth.');
  record('Follow-up is scheduled through the canonical CRM path', true, 'follow-up acknowledgement received');

  await clickButtonByText(page, 'Complete');
  await waitForNotice(page, 'Follow-up completed.');
  record('Follow-up terminal transition is exposed end-to-end', true, 'completion acknowledgement received');

  record('CRM browser flow has no uncaught console errors', consoleErrors.length === 0, consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none');
  record('CRM browser flow has no failed network requests', failedRequests.length === 0, failedRequests.length ? failedRequests.slice(0, 3).join(' | ') : 'none');
} catch (error) {
  // Browser jobs are the only available Chromium evidence in this checkout.
  // Preserve enough state in CI logs to distinguish a route, bundle, API, or
  // React-render failure without printing form values or session credentials.
  await new Promise((resolve) => setTimeout(resolve, 0));
  await emitFailureDiagnostics(page, error, consoleErrors, failedRequests);
  throw error;
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nCRM BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
