import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const jsRoot = path.join(root, 'resources', 'js');
const viewsRoot = path.join(root, 'resources', 'views');

const domainComponents = {
  students: 'students.tsx', academic: 'academic.tsx', placement: 'placement.tsx',
  teachers: 'teacher.tsx', hr: 'hr.tsx', finance: 'finance.tsx', payroll: 'payroll.tsx',
  reporting: 'reporting.tsx', management: 'management.tsx', workspace: 'workspace.tsx',
};
const transportEntrypoints = [
  'app.tsx', 'finance.tsx', 'reporting.tsx', 'hr.tsx', 'payroll.tsx',
  'placement.tsx', 'identity.tsx', 'access.tsx', 'organization.tsx',
];

for (const [domain, filename] of Object.entries(domainComponents)) {
  const source = fs.readFileSync(path.join(jsRoot, filename), 'utf8');
  assert.match(source, /AppShell/, `${domain}: unified shell missing`);
  assert.doesNotMatch(source, /localStorage\.(?:getItem|setItem).*?(?:token|jwt|access_token)/is, `${domain}: browser token persistence is forbidden`);
  assert.doesNotMatch(source, /Authorization\s*[:=]\s*[`'"].*?(?:Bearer|JWT)/i, `${domain}: direct bearer transport is forbidden`);
}
for (const filename of transportEntrypoints) {
  assert.match(fs.readFileSync(path.join(jsRoot, filename), 'utf8'), /createApiClient/, `${filename}: canonical API bootstrap missing`);
}

const packageJson = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
assert.equal(packageJson.engines?.node, '>=22.0 <23.0', 'package: Node compatibility range drift');
assert.equal(packageJson.engines?.npm, '>=10.0 <11.0', 'package: npm compatibility range drift');

const app = fs.readFileSync(path.join(jsRoot, 'app.tsx'), 'utf8');
assert.match(app, /react-console/);
assert.match(app, /AppErrorBoundary/);
assert.match(app, /ReportingApp/);
assert.match(app, /function resolveContent/);
assert.match(app, /case 'reporting':/);
assert.match(app, /switch \(view as ConsoleView \| null\)/);

const navigation = fs.readFileSync(path.join(jsRoot, 'core', 'navigation.ts'), 'utf8');
assert.match(navigation, /export const navigation:/);
assert.match(navigation, /navigationGroups/);
for (const route of ['/workspace', '/crm?view=front-office', '/students', '/academic', '/placement', '/teachers', '/hr', '/crm', '/finance', '/payroll', '/library', '/communication', '/workspace?view=reporting', '/documents', '/organization', '/identity', '/access', '/privacy', '/audit', '/management']) {
  assert.match(navigation, new RegExp(`href: '${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'`), `navigation: ${route} missing`);
}
assert.doesNotMatch(navigation, /href: '\/reporting'/, 'navigation: legacy reporting route must not be primary UI');

const api = fs.readFileSync(path.join(jsRoot, 'core', 'api.ts'), 'utf8');
for (const pattern of [/credentials: 'same-origin'/, /X-CSRF-TOKEN/, /Idempotency-Key/, /correlation_id/, /DEFAULT_TIMEOUT_MS\s*=\s*30_000/, /AbortController/, /network_error/]) assert.match(api, pattern);
const boundary = fs.readFileSync(path.join(jsRoot, 'core', 'error-boundary.tsx'), 'utf8');
assert.match(boundary, /componentDidCatch/);
assert.match(boundary, /window\.location\.reload/);

const ui = fs.readFileSync(path.join(jsRoot, 'ui.tsx'), 'utf8');
for (const pattern of [/navigationGroups/, /metaKey\s*\|\|\s*event\.ctrlKey/, /ArrowDown/, /ArrowUp/, /Enter/, /Escape/, /aria-selected/]) assert.match(ui, pattern);

const browserE2e = fs.readFileSync(path.join(root, 'scripts', 'runtime', 'browser-e2e.mjs'), 'utf8');
assert.match(browserE2e, /required\('E2E_USERNAME'\)/);
assert.match(browserE2e, /required\('E2E_PASSWORD'\)/);
assert.match(browserE2e, /required\('CHROMIUM_PATH'\)/);
assert.doesNotMatch(browserE2e, /Runtime-Pass-12345|runtime\.owner|definitely-the-wrong-password/i);

const environment = fs.readFileSync(path.join(root, 'scripts', 'runtime', 'verify-environment.mjs'), 'utf8');
for (const range of ["php: { range: '>=8.2 <8.5'", "composer: { range: '>=2.5 <3'", "node: { range: '>=22.0 <23.0'", "npm: { range: '>=10.0 <11.0'", "postgres: { range: '>=18.0 <19.0'"]) assert.match(environment, new RegExp(range.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));

const workflow = fs.readFileSync(path.join(root, '.github', 'workflows', 'verification.yml'), 'utf8');
for (const pattern of [
  /permissions:\s*\n\s*contents: read/, /concurrency:\s*\n\s*group:/, /cancel-in-progress: true/,
  /PHP_VERSION: '8\.4\.25'/, /COMPOSER_VERSION: '2\.10\.3'/, /NODE_VERSION: '22\.22\.3'/,
  /NPM_VERSION: '10\.9\.8'/, /POSTGRES_VERSION: '18\.4'/, /npm ci --engine-strict/,
  /::add-mask::\$E2E_PASSWORD/, /image: postgres:18\.4/,
]) assert.match(workflow, pattern);

const hr = fs.readFileSync(path.join(jsRoot, 'hr.tsx'), 'utf8');
assert.match(hr, /function confirmAction/);
assert.match(hr, /action === 'terminate'/);
const payroll = fs.readFileSync(path.join(jsRoot, 'payroll.tsx'), 'utf8');
assert.match(payroll, /function confirmAction/);

const workspaceBlade = fs.readFileSync(path.join(viewsRoot, 'workspace.blade.php'), 'utf8');
for (const contract of [
  "@vite('resources/js/app.tsx')",
  'toefl-house-ultimate.css',
  'toefl-house-route-state.css',
  'toefl-house-placement.css',
  'toefl-house-operations.css',
  '<base href="{{ url(\'/\') }}/">',
]) assert.ok(workspaceBlade.includes(contract), `workspace Blade contract missing: ${contract}`);
assert.doesNotMatch(workspaceBlade, /reporting-console|resources\/js\/reporting\.tsx/, 'workspace Blade must have one React mount authority');
const legacyLayout = fs.readFileSync(path.join(viewsRoot, 'layouts', 'app.blade.php'), 'utf8');
for (const contract of [
  'toefl-house-ultimate.css',
  'toefl-house-operations.css',
  'toefl-house-legacy-operations.css',
  '<base href="{{ url(\'/\') }}/">',
]) assert.ok(legacyLayout.includes(contract), `legacy layout contract missing: ${contract}`);
for (const folder of ['library', 'communication', 'documents', 'audit', 'privacy']) assert.ok(fs.existsSync(path.join(viewsRoot, folder, 'index.blade.php')), `${folder}: legacy boundary missing`);

console.log('PASS  frontend domain, transport, shell, CI and legacy-boundary contracts');
