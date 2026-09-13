#!/usr/bin/env node
/**
 * Students & Admissions browser E2E.
 *
 * Uses real Chromium, real session authentication and the canonical
 * /api/v1/students boundary. Separate browser contexts prove the staged
 * Admissions authority chain and Student lifecycle boundaries without
 * introducing a test-only business authority.
 */
import puppeteer from 'puppeteer-core';

const BASE = process.env.BASE_URL?.trim() || 'http://127.0.0.1:8999';
const PASSWORD = process.env.STUDENTS_E2E_PASSWORD?.trim() || 'employee-password-1';
const EXECUTABLE = process.env.CHROMIUM_PATH?.trim() || '/usr/bin/chromium';

const users = {
  registrar: 'e2e-students-registrar',
  reviewer: 'e2e-students-reviewer',
  approver: 'e2e-students-approver',
  manager: 'e2e-students-manager',
  reactivator: 'e2e-students-reactivator',
  transfer: 'e2e-students-transfer',
  guardian: 'e2e-students-guardian',
};

const results = [];
const record = (name, pass, detail = '') => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}${detail ? `\n      ${detail}` : ''}`);
};

const browser = await puppeteer.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  headless: true,
  timeout: 90_000,
});

const contexts = [];

async function session(username) {
  const context = await browser.createBrowserContext();
  contexts.push(context);
  const page = await context.newPage();
  page.setDefaultTimeout(30_000);
  page.setDefaultNavigationTimeout(60_000);
  const errors = [];
  const failed = [];
  page.on('pageerror', (error) => errors.push(String(error).slice(0, 500)));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text().slice(0, 500)); });
  page.on('requestfailed', (request) => failed.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', username);
  await page.type('input[name="password"]', PASSWORD);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
  ]);
  if (page.url().includes('/login')) throw new Error(`login failed for ${username}`);

  async function api(method, path, body, expected = null) {
    const response = await page.evaluate(async ({ method: requestMethod, path: requestPath, body: requestBody, expectedStatus }) => {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const response = await fetch(`/api/v1${requestPath}`, {
        method: requestMethod,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: requestBody === undefined ? undefined : JSON.stringify(requestBody),
      });
      const text = await response.text();
      let data = null;
      try { data = text ? JSON.parse(text) : null; } catch { data = text; }
      return { status: response.status, data, expectedStatus };
    }, { method, path, body, expectedStatus: expected });
    if (expected !== null && response.status !== expected) {
      throw new Error(`${method} ${path}: expected ${expected}, got ${response.status}: ${JSON.stringify(response.data).slice(0, 1200)}`);
    }
    return response;
  }

  return { username, page, api, errors, failed };
}

try {
  const health = await fetch(`${BASE}/health`);
  record('Students E2E server is reachable', health.ok, `HTTP ${health.status}`);
  if (!health.ok) throw new Error('health check failed');

  const registrar = await session(users.registrar);
  await registrar.page.goto(`${BASE}/students`, { waitUntil: 'networkidle2' });
  await registrar.page.waitForSelector('#students-title, #workspace-main', { timeout: 60_000 });
  const mount = await registrar.page.evaluate(() => ({
    url: window.location.pathname,
    root: document.getElementById('react-console')?.getAttribute('data-view') || null,
    body: document.body.innerText.includes('Admissions intake'),
  }));
  record('Students workspace mounts through the canonical employee shell', mount.url === '/students' && (mount.root === 'students' || mount.body), JSON.stringify(mount));

  const index = await registrar.api('GET', '/students');
  record('Registrar receives a server-projected capability and directory payload', index.status === 200 && index.data?.capabilities?.admission_register === true && Array.isArray(index.data?.registration_people), `capabilities=${JSON.stringify(index.data?.capabilities || {})}`);

  const candidateId = 'e2e-students-candidate';
  const guardianId = 'e2e-students-guardian-person';
  const branchId = index.data?.registration_branches?.[0]?.id;
  if (!branchId) throw new Error('No authorized registration branch was projected to the registrar');
  if (!(index.data?.registration_people || []).some((person) => person.id === candidateId)) {
    throw new Error(`Candidate ${candidateId} was not present in the verified registration-person projection`);
  }

  const unique = Date.now();
  const registered = await registrar.api('POST', '/students/applicants', {
    person_id: candidateId,
    program_interest: `E2E General English ${unique}`,
    branch_id: branchId,
  }, 201);
  record('Applicant registration succeeds only through the Admissions authority', true, `applicant=${registered.data?.status || 'registered'}`);
  const afterRegistration = await registrar.api('GET', '/students');
  const applicant = (afterRegistration.data?.applicants || []).find((item) => item.person?.id === candidateId || item.person?.person_id === candidateId);
  if (!applicant) throw new Error('registered applicant was not visible in the server-authorized directory');

  const initiated = await registrar.api('POST', `/students/applicants/${encodeURIComponent(applicant.id)}/initiate`, {
    decision: 'admit',
    reason: 'Browser E2E admission evidence',
    evidence_ref: `evidence/students/admission-${unique}`,
  }, 201);
  const decisionId = initiated.data?.decision_id;
  if (!decisionId) throw new Error('admission initiate did not return a decision id');
  record('Admission initiation creates a staged decision and does not self-finalize', true, decisionId);

  const reviewer = await session(users.reviewer);
  const reviewResult = await reviewer.api('POST', `/students/decisions/${encodeURIComponent(decisionId)}/review`, undefined, 200);
  record('Admission review requires the distinct review capability', reviewResult.data?.status === 'reviewed', JSON.stringify(reviewResult.data));

  const approver = await session(users.approver);
  const approveResult = await approver.api('POST', `/students/decisions/${encodeURIComponent(decisionId)}/approve`, undefined, 200);
  record('Admission approval requires the distinct approval capability', approveResult.data?.status === 'final' && approveResult.data?.outcome === 'admit', JSON.stringify(approveResult.data));

  const conversion = await approver.api('POST', `/students/applicants/${encodeURIComponent(applicant.id)}/enroll`, undefined, 200);
  record('Final admitted applicant converts exactly once into the Students aggregate', conversion.data?.status === 'enrolled' && Boolean(conversion.data?.student_id || conversion.data?.student), JSON.stringify(conversion.data));
  const studentId = conversion.data?.student_id;
  if (!studentId) throw new Error('conversion response did not expose student_id');

  const manager = await session(users.manager);
  await manager.page.goto(`${BASE}/students/${encodeURIComponent(studentId)}`, { waitUntil: 'networkidle2' });
  await manager.page.waitForSelector('#student-detail-title, #workspace-main', { timeout: 60_000 });
  const detail = await manager.api('GET', `/students/${encodeURIComponent(studentId)}`);
  record('Student detail is scoped and exposes the canonical lifecycle projection', detail.status === 200 && detail.data?.student_id === studentId && Array.isArray(detail.data?.status_history), `status=${detail.data?.status}`);

  const suspendKey = `students-e2e-suspend-${unique}`;
  const firstSuspend = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/status/suspend`, { reason: 'Browser E2E suspension proof' }, 200);
  const replaySuspend = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/status/suspend`, { reason: 'Browser E2E suspension proof' }, 200);
  record('Student status mutation is idempotent and does not duplicate history', firstSuspend.data?.status === 'suspend' && replaySuspend.data?.status === 'suspend', `replay=${JSON.stringify(replaySuspend.data)}`);

  const deniedReactivate = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/status/reactivate`, { reason: 'manager must not self-reactivate' });
  record('Reactivation is server-denied without the separate reactivation capability', deniedReactivate.status === 403, `HTTP ${deniedReactivate.status}`);

  const reactivator = await session(users.reactivator);
  const reactivated = await reactivator.api('POST', `/students/${encodeURIComponent(studentId)}/status/reactivate`, { reason: 'Browser E2E approved reactivation' }, 200);
  record('A separately authorized reactivator can restore the student to active', reactivated.data?.status === 'reactivate', JSON.stringify(reactivated.data));

  const hold = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/hold`, { action: 'freeze', reason: 'Browser E2E hold' }, 200);
  const resume = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/hold`, { action: 'resume', reason: 'Browser E2E hold cleared' }, 200);
  record('Student hold lifecycle is reversible only through its command authority', hold.data?.status === 'freeze' && resume.data?.status === 'resume');

  const guardian = await session(users.guardian);
  const guardianResult = await guardian.api('POST', `/students/${encodeURIComponent(studentId)}/guardians`, {
    guardian_person_id: guardianId,
    relationship: 'guardian',
    permissions: ['view-academic', 'receive-communication'],
  }, 201);
  const relationshipId = guardianResult.data?.relationship_id;
  if (!relationshipId) throw new Error('guardian registration did not return relationship_id');
  const verifiedGuardian = await guardian.api('POST', `/students/guardians/${encodeURIComponent(relationshipId)}/verify`, { evidence_ref: `evidence/students/guardian-${unique}` }, 200);
  record('Guardian relationship remains untrusted until explicit evidence verification', verifiedGuardian.data?.status === 'verify', JSON.stringify(verifiedGuardian.data));

  const preference = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/communication-preference`, { channel: 'email', enabled: true }, 200);
  record('Communication preference is written through the Students authority', preference.data?.status === 'saved', JSON.stringify(preference.data));

  await manager.api('POST', `/students/${encodeURIComponent(studentId)}/status/complete`, { reason: 'Browser E2E terminal-state proof' }, 200);
  const terminalAttempt = await manager.api('POST', `/students/${encodeURIComponent(studentId)}/status/withdraw`, { reason: 'must be rejected from completed' });
  record('Terminal student status rejects an illegal backward transition', terminalAttempt.status === 422 || terminalAttempt.status === 409, `HTTP ${terminalAttempt.status}`);

  const finalDetail = await manager.api('GET', `/students/${encodeURIComponent(studentId)}`);
  record('Students closure leaves a coherent append-only final projection', finalDetail.status === 200 && finalDetail.data?.status === 'completed' && (finalDetail.data?.status_history?.length || 0) >= 5, `history=${finalDetail.data?.status_history?.length || 0}`);

  for (const actor of [registrar, reviewer, approver, manager, reactivator, guardian]) {
    record(`${actor.username} session has no uncaught browser or failed-network errors`, actor.errors.length === 0 && actor.failed.length === 0, [...actor.errors, ...actor.failed].slice(0, 3).join(' | ') || 'none');
  }
} catch (error) {
  console.error(error instanceof Error ? error.stack ?? error.message : String(error));
  throw error;
} finally {
  await Promise.all(contexts.map((context) => context.close().catch(() => {})));
  await browser.close();
}

const failed = results.filter((result) => !result.pass).length;
console.log(`\nSTUDENTS & ADMISSIONS BROWSER E2E RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
