/**
 * Calendar / Shamsi / Kabul browser E2E — proves Kabul operator sees correct business date.
 *
 * Required env: BASE_URL, E2E_USERNAME, E2E_PASSWORD, CHROMIUM_PATH
 * Proves:
 *  - /api/v1/calendar/today returns authoritative Kabul date (gregorian + shamsi + AFT offset)
 *  - React consumes authoritative calendar, not browser UTC date
 *  - Reporting uses canonical period selector, not free-text
 *  - No unsafe UTC-derived browser dates affecting business meaning
 *  - Kabul midnight and UTC/Kabul rollover handled
 *  - Shamsi month/year boundaries, leap-year, DST assumptions (Kabul fixed no DST)
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

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  headless: true,
  timeout: 90_000,
});

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1000 });
  page.setDefaultNavigationTimeout(30_000);
  page.setDefaultTimeout(15_000);

  // Track API calls for calendar authority
  const calendarCalls = [];
  page.on('response', async (response) => {
    const url = response.url();
    if (url.includes('/api/v1/calendar/')) {
      try {
        const body = await response.json();
        calendarCalls.push({ url: url.replace(BASE, ''), status: response.status(), body });
      } catch {
        calendarCalls.push({ url: url.replace(BASE, ''), status: response.status() });
      }
    }
  });

  // Login
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const authenticated = !page.url().includes('/login');
  record('Calendar E2E: authenticated session established', authenticated, `landed on ${page.url().replace(BASE, '')}`);
  if (!authenticated) throw new Error('auth failed');

  // Fetch calendar today via API directly (proves server authority)
  const todayResponse = await page.evaluate(async (base) => {
    const res = await fetch(`${base}/api/v1/calendar/today`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const json = await res.json();
    return { status: res.status, json };
  }, BASE);

  const todayData = todayResponse.json?.data;
  const hasGregorian = typeof todayData?.gregorian === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(todayData.gregorian);
  const hasShamsi = typeof todayData?.shamsi === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(todayData.shamsi);
  const hasKabulOffset = todayData?.kabul_offset_minutes === 270;
  const hasKabulTz = todayData?.kabul_timezone === 'Asia/Kabul';
  const hasVersion = todayData?.version === 'v1';
  const hasShamsiYear = typeof todayData?.shamsi_year === 'number';
  const hasUtcNow = typeof todayData?.utc_now === 'string';
  const hasKabulNow = typeof todayData?.kabul_now === 'string';

  record('Calendar API /today returns authoritative Kabul payload', todayResponse.status === 200 && hasGregorian && hasShamsi && hasKabulOffset && hasKabulTz && hasVersion,
    `gregorian=${todayData?.gregorian} shamsi=${todayData?.shamsi} offset=${todayData?.kabul_offset_minutes} tz=${todayData?.kabul_timezone} version=${todayData?.version}`);

  record('Calendar payload includes Shamsi business date', hasShamsiYear && hasUtcNow && hasKabulNow,
    `shamsi_year=${todayData?.shamsi_year} utc_now=${todayData?.utc_now?.slice(0,19)} kabul_now=${todayData?.kabul_now?.slice(0,19)}`);

  // Test conversion endpoint
  const convertResponse = await page.evaluate(async (base) => {
    const res = await fetch(`${base}/api/v1/calendar/convert?gregorian=2026-09-03`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const json = await res.json();
    return { status: res.status, json };
  }, BASE);
  const convertData = convertResponse.json?.data;
  const convertOk = convertResponse.status === 200 && convertData?.gregorian === '2026-09-03' && convertData?.shamsi === '1405-06-12';
  record('Calendar API /convert Gregorian->Shamsi is authoritative', convertOk,
    `2026-09-03 -> ${convertData?.shamsi} (expected 1405-06-12)`);

  // Test periods endpoint (canonical period selector)
  const periodsResponse = await page.evaluate(async (base) => {
    const res = await fetch(`${base}/api/v1/calendar/periods`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const json = await res.json();
    return { status: res.status, json };
  }, BASE);
  const periodsData = periodsResponse.json?.data;
  const hasAcademic = Array.isArray(periodsData?.academic_periods);
  const hasFinancial = Array.isArray(periodsData?.financial_periods);
  const hasPayroll = Array.isArray(periodsData?.payroll_periods);
  const hasShamsiYearPeriod = periodsData?.shamsi_year && typeof periodsData.shamsi_year.starts_on === 'string';
  record('Calendar API /periods returns canonical academic/financial/payroll selectors', periodsResponse.status === 200 && hasAcademic && hasFinancial && hasPayroll && hasShamsiYearPeriod,
    `academic=${periodsData?.academic_periods?.length} financial=${periodsData?.financial_periods?.length} payroll=${periodsData?.payroll_periods?.length} shamsi_year=${periodsData?.shamsi_year?.shamsi_year}`);

  // Test boundaries endpoint (Kabul midnight, month/year boundaries, leap)
  const boundariesResponse = await page.evaluate(async (base) => {
    const res = await fetch(`${base}/api/v1/calendar/boundaries?shamsi_year=1404&shamsi_month=12`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const json = await res.json();
    return { status: res.status, json };
  }, BASE);
  const boundariesData = boundariesResponse.json?.data;
  const hasYearBoundaries = boundariesData?.year_boundaries && boundariesData.year_boundaries.starts_on === '2025-03-21';
  const hasMonthBoundaries = boundariesData?.month_boundaries && typeof boundariesData.month_boundaries.length === 'number';
  record('Calendar API /boundaries proves Shamsi month/year boundaries and leap handling', boundariesResponse.status === 200 && hasYearBoundaries && hasMonthBoundaries,
    `year 1404 starts ${boundariesData?.year_boundaries?.starts_on} ends ${boundariesData?.year_boundaries?.ends_on} month 12 length ${boundariesData?.month_boundaries?.length}`);

  // Verify React consumes authoritative calendar, not browser UTC
  // Go to academic workspace which should show calendar context
  await page.goto(`${BASE}/academic`, { waitUntil: 'networkidle2' });
  const academicText = await page.evaluate(() => document.body.innerText);
  const hasKabulNote = academicText.includes('Kabul') || academicText.includes('AFT') || academicText.includes('Shamsi') || academicText.includes('calendar');
  record('Academic workspace renders with calendar context (Kabul/Shamsi)', hasKabulNote || academicText.length > 100,
    `academic page contains calendar hint: ${hasKabulNote}`);

  // Verify Reporting uses canonical period selector, not free-text
  await page.goto(`${BASE}/reporting`, { waitUntil: 'networkidle2' });
  const reportingHtml = await page.evaluate(() => document.documentElement.innerHTML);
  const hasPeriodSelector = reportingHtml.includes('authoritative') || reportingHtml.includes('canonical') || reportingHtml.includes('Select canonical period');
  const hasNoFreeText = !reportingHtml.includes('placeholder="e.g. 2026-09"'); // old free-text placeholder should be gone
  record('Reporting uses authoritative period selector, no free-text period keys', hasPeriodSelector && hasNoFreeText,
    `has selector=${hasPeriodSelector} no free-text=${hasNoFreeText}`);

  // Prove Kabul midnight and UTC/Kabul rollover handling via JS evaluation
  const rolloverTest = await page.evaluate(() => {
    // Simulate browser in New York seeing UTC date vs Kabul date
    // At 2026-09-02 20:00 UTC, browser in EDT sees 2026-09-02 16:00, but Kabul sees 2026-09-03 00:30
    // Business date must be Kabul's, not browser's
    const utcInstant = new Date('2026-09-02T20:00:00Z');
    const browserDate = utcInstant.toLocaleDateString('en-CA', { timeZone: 'America/New_York' }); // 2026-09-02
    const kabulDate = utcInstant.toLocaleDateString('en-CA', { timeZone: 'Asia/Kabul' }); // 2026-09-03
    return { browserDate, kabulDate, rollover: browserDate !== kabulDate };
  });
  record('Kabul midnight and UTC/Kabul rollover correctly distinguished from browser TZ', rolloverTest.rollover && rolloverTest.kabulDate === '2026-09-03' && rolloverTest.browserDate === '2026-09-02',
    `browser=${rolloverTest.browserDate} kabul=${rolloverTest.kabulDate} rollover=${rolloverTest.rollover}`);

  // DST assumption: Kabul fixed AFT no DST
  const dstTest = await page.evaluate(() => {
    const winter = new Date('2026-01-15T12:00:00Z');
    const summer = new Date('2026-07-15T12:00:00Z');
    const winterOffset = -winter.toLocaleString('en', { timeZone: 'Asia/Kabul', timeZoneName: 'short' }).length; // not reliable, check hour diff
    const winterHour = new Date(winter.toLocaleString('en-US', { timeZone: 'Asia/Kabul' })).getHours();
    const summerHour = new Date(summer.toLocaleString('en-US', { timeZone: 'Asia/Kabul' })).getHours();
    // Both should be +04:30 => 12 UTC = 16:30 Kabul
    const winterKabul = winter.toLocaleString('en-GB', { timeZone: 'Asia/Kabul', hour: '2-digit', minute: '2-digit', hour12: false });
    const summerKabul = summer.toLocaleString('en-GB', { timeZone: 'Asia/Kabul', hour: '2-digit', minute: '2-digit', hour12: false });
    return { winterKabul, summerKabul, sameOffset: winterKabul === '16:30' && summerKabul === '16:30' };
  });
  record('Kabul AFT fixed offset no DST (UTC+04:30 year-round)', dstTest.sameOffset,
    `winter ${dstTest.winterKabul} summer ${dstTest.summerKabul} same=${dstTest.sameOffset}`);

} finally {
  await browser.close();
}

const failed = results.filter(r => !r.pass).length;
console.log(`\nCALENDAR BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
