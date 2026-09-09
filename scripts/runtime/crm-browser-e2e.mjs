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

const setReactControl = async (page, selector, value) => {
  await page.$eval(selector, (element, nextValue) => {
    const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(element), 'value')?.set;
    setter?.call(element, nextValue);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
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

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  headless: true,
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  page.setDefaultNavigationTimeout(30_000);
  page.setDefaultTimeout(15_000);

  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => consoleErrors.push(error.message));
  page.on('requestfailed', (request) => failedRequests.push(`${request.url()} :: ${request.failure()?.errorText || 'failed'}`));

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

  const branchState = await page.$eval('.crm-capture select', (select) => ({
    value: select.value,
    options: [...select.options].map((option) => option.value),
  }));
  record('CRM capture exposes an explicit branch contract', branchState.options.length >= 2 && branchState.options.some(Boolean), JSON.stringify(branchState));

  const captureInputs = await page.$$('.crm-capture input');
  if (captureInputs.length < 3) throw new Error('CRM capture form no longer exposes name/phone/email controls');
  await captureInputs[0].type(`Browser CRM ${Date.now()}`);
  await captureInputs[2].type(`crm-browser-${Date.now()}@example.test`);

  const captureSelects = await page.$$('.crm-capture select');
  if (captureSelects.length < 3) throw new Error('CRM capture form no longer exposes branch selector');
  const branchValue = branchState.options.find(Boolean);
  await captureSelects[2].select(branchValue);
  await page.click('.crm-capture button[type="submit"]');
  await waitForNotice(page, 'Visitor captured in the CRM source of truth.');
  record('Visitor capture succeeds through the canonical API', true, 'capture acknowledgement received');

  const visitorName = await page.$eval('.visitor-list .visitor-row strong', (node) => node.textContent.trim());
  await clickButtonByText(page, visitorName);
  await page.waitForFunction((expected) => document.querySelector('#detail-title')?.textContent?.trim() === expected, {}, visitorName);
  record('Captured visitor can be reopened from the authorized directory', true, visitorName);

  const stageOptions = await page.$$eval('.stage-actions select option', (options) => options.map((option) => option.value));
  if (stageOptions.length > 1) {
    await page.select('.stage-actions select', stageOptions[1]);
    await waitForNotice(page, 'Visitor moved to');
    record('Visitor stage transition round-trips through the API', true, `${stageOptions[0]} -> ${stageOptions[1]}`);
  } else {
    record('Visitor stage transition round-trips through the API', false, 'no authorized next transition rendered');
  }

  await setReactControl(page, '.crm-detail form textarea', 'Browser E2E interaction evidence');
  await clickButtonByText(page, 'Append interaction');
  await waitForNotice(page, 'Interaction appended to the immutable timeline.');
  record('Interaction is appended to the CRM timeline', true, 'immutable interaction acknowledgement received');

  const followupFormState = await page.$$eval('.crm-detail form', (forms) => forms.map((form) => ({
    text: (form.textContent || '').replace(/\s+/g, ' ').trim(),
    inputs: [...form.querySelectorAll('input')].map((input) => ({ type: input.type, value: input.value })),
  })));
  const followupIndex = followupFormState.findIndex((form) => form.text.includes('Schedule follow-up'));
  if (followupIndex < 0) throw new Error('CRM follow-up form not found');

  const forms = await page.$$('.crm-detail form');
  const followupForm = forms[followupIndex];
  const followupInputs = await followupForm.$$('input');
  const dateValue = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const iso = new Date(dateValue.getTime() - dateValue.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
  const scheduledInput = followupInputs.find(async () => false);
  void scheduledInput;
  for (const input of followupInputs) {
    const type = await input.evaluate((node) => node.type);
    if (type === 'datetime-local') {
      await input.click();
      await input.evaluate((node, nextValue) => {
        const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(node), 'value')?.set;
        setter?.call(node, nextValue);
        node.dispatchEvent(new Event('input', { bubbles: true }));
        node.dispatchEvent(new Event('change', { bubbles: true }));
      }, iso);
    }
  }
  const followupTextInputs = followupInputs.filter(async (input) => (await input.evaluate((node) => node.type)) === 'text');
  void followupTextInputs;
  await setReactControl(page, '.crm-detail form:nth-of-type(4) input[type="text"]', `Browser E2E follow-up ${Date.now()}`);
  await clickButtonByText(page, 'Schedule follow-up');
  await waitForNotice(page, 'Follow-up scheduled in the CRM source of truth.');
  record('Follow-up is scheduled through the canonical CRM path', true, 'follow-up acknowledgement received');

  await clickButtonByText(page, 'Complete');
  await waitForNotice(page, 'Follow-up completed.');
  record('Follow-up terminal transition is exposed end-to-end', true, 'completion acknowledgement received');

  record('CRM browser flow has no uncaught console errors', consoleErrors.length === 0, consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none');
  record('CRM browser flow has no failed network requests', failedRequests.length === 0, failedRequests.length ? failedRequests.slice(0, 3).join(' | ') : 'none');
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nCRM BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
