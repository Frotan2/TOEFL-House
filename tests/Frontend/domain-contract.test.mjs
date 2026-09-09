import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const jsRoot = path.join(root, 'resources', 'js');
const viewsRoot = path.join(root, 'resources', 'views');

const domainEntrypoints = {
  students: 'students.tsx',
  academic: 'academic.tsx',
  placement: 'placement.tsx',
  teachers: 'teacher.tsx',
  hr: 'hr.tsx',
  finance: 'finance.tsx',
  payroll: 'payroll.tsx',
  reporting: 'reporting.tsx',
  management: 'management.tsx',
  workspace: 'workspace.tsx',
};

for (const [domain, filename] of Object.entries(domainEntrypoints)) {
  const file = path.join(jsRoot, filename);
  assert.ok(fs.existsSync(file), `${domain}: missing canonical entrypoint ${filename}`);
  const source = fs.readFileSync(file, 'utf8');
  assert.match(source, /createApiClient/, `${domain}: must use the canonical API client`);
  assert.match(source, /AppShell/, `${domain}: must use the unified shell`);
  assert.doesNotMatch(source, /localStorage\.(?:getItem|setItem).*?(?:token|jwt|access_token)/is, `${domain}: browser token persistence is forbidden`);
  assert.doesNotMatch(source, /Authorization\s*[:=]\s*[`'"].*?(?:Bearer|JWT)/i, `${domain}: direct bearer-token transport is forbidden`);
}

const packageJson = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
assert.equal(packageJson.engines?.node, '22.22.3', 'package: Node engine must match the verified runtime lock');
assert.equal(packageJson.engines?.npm, '10.9.8', 'package: npm engine must match the verified runtime lock');

const app = fs.readFileSync(path.join(jsRoot, 'app.tsx'), 'utf8');
assert.match(app, /react-console/, 'app.tsx: canonical React console mount must remain present');
assert.match(app, /AppErrorBoundary/, 'app.tsx: root render must be protected by the application error boundary');
assert.match(app, /function resolveContent/, 'app.tsx: view resolution must be centralized');
assert.match(app, /switch \(view as ConsoleView \| null\)/, 'app.tsx: view resolution must use an explicit typed switch');
assert.doesNotMatch(app, /\?\s*<AcademicSetupApp.*?:\s*studentJourneyRequested/s, 'app.tsx: deeply nested ternary routing must not return');

const navigation = fs.readFileSync(path.join(jsRoot, 'core', 'navigation.ts'), 'utf8');
assert.match(navigation, /export const navigation:/, 'navigation contract: shared registry missing');
assert.match(navigation, /navigationGroups/, 'navigation contract: shared group registry missing');
for (const route of ['/workspace', '/crm?view=front-office', '/students', '/academic', '/placement', '/teachers', '/hr', '/crm', '/finance', '/payroll', '/library', '/communication', '/reporting', '/documents', '/organization', '/identity', '/access', '/privacy', '/audit', '/management']) {
  assert.match(navigation, new RegExp(`href: '${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'`), `navigation contract: ${route} is missing from the shared registry`);
}

const boundary = fs.readFileSync(path.join(jsRoot, 'core', 'error-boundary.tsx'), 'utf8');
assert.match(boundary, /componentDidCatch/, 'error boundary: production error capture missing');
assert.match(boundary, /console\.error/, 'error boundary: diagnostic logging missing');
assert.match(boundary, /window\.location\.reload/, 'error boundary: full reload recovery missing');
assert.match(boundary, /Reference:/, 'error boundary: support reference must be visible');

const api = fs.readFileSync(path.join(jsRoot, 'core', 'api.ts'), 'utf8');
assert.match(api, /credentials: 'same-origin'/, 'API client: session credentials must remain same-origin');
assert.match(api, /X-CSRF-TOKEN/, 'API client: CSRF boundary missing');
assert.match(api, /Idempotency-Key/, 'API client: mutation idempotency boundary missing');
assert.match(api, /correlation_id/, 'API client: server correlation diagnostics missing');
assert.match(api, /DEFAULT_TIMEOUT_MS\s*=\s*30_000/, 'API client: bounded request timeout missing');
assert.match(api, /request_timeout/, 'API client: timeout must surface a typed retryable error');
assert.match(api, /AbortController/, 'API client: requests must be abortable by timeout');

const designSystem = fs.readFileSync(path.join(jsRoot, 'design-system.tsx'), 'utf8');
assert.match(designSystem, /role="tablist"/, 'design system: tabs must expose tablist semantics');
assert.match(designSystem, /aria-controls/, 'design system: tabs must reference their tabpanels');
assert.match(designSystem, /tabIndex=\{selected \? 0 : -1\}/, 'design system: tabs must use roving tabindex');
assert.match(designSystem, /event\.key === 'Home'/, 'design system: Home tab navigation missing');
assert.match(designSystem, /event\.key === 'End'/, 'design system: End tab navigation missing');

const ui = fs.readFileSync(path.join(jsRoot, 'ui.tsx'), 'utf8');
assert.match(ui, /metaKey\s*\|\|\s*event\.ctrlKey/, 'shell: Ctrl/⌘+K command palette shortcut missing');
assert.match(ui, /event\.key === 'ArrowDown'/, 'shell: ArrowDown traversal missing');
assert.match(ui, /event\.key === 'ArrowUp'/, 'shell: ArrowUp traversal missing');
assert.match(ui, /event\.key === 'Enter'/, 'shell: Enter activation missing');
assert.match(ui, /event\.key === 'Escape'/, 'shell: Escape dismissal missing');
assert.match(ui, /aria-selected/, 'shell: active command palette option must be exposed semantically');

const navigationPaths = [
  '/workspace', '/crm?view=front-office', '/students', '/academic', '/placement', '/teachers', '/hr', '/crm',
  '/finance', '/payroll', '/library', '/communication', '/reporting', '/documents', '/organization', '/identity',
  '/access', '/privacy', '/audit', '/management',
];
for (const route of navigationPaths) assert.match(ui, new RegExp(`href=[\"']${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}[\"']`), `shell: navigation route ${route} is missing`);

const browserE2e = fs.readFileSync(path.join(root, 'scripts', 'runtime', 'browser-e2e.mjs'), 'utf8');
assert.match(browserE2e, /required\('E2E_USERNAME'\)/, 'browser E2E: username must come from runtime configuration');
assert.match(browserE2e, /required\('E2E_PASSWORD'\)/, 'browser E2E: password must come from runtime configuration');
assert.match(browserE2e, /required\('CHROMIUM_PATH'\)/, 'browser E2E: browser binary must be explicit');
assert.doesNotMatch(browserE2e, /Runtime-Pass-12345|runtime\.owner/, 'browser E2E: embedded test credentials are forbidden');
assert.doesNotMatch(browserE2e, /wrong-password|definitely-the-wrong-password/i, 'browser E2E: must not consume login throttling with a deliberate invalid login');

const environment = fs.readFileSync(path.join(root, 'scripts', 'runtime', 'verify-environment.mjs'), 'utf8');
assert.match(environment, /php: \{ exact: '8\.4\.14'/, 'environment: PHP exact lock missing');
assert.match(environment, /composer: \{ exact: '2\.9\.2'/, 'environment: Composer exact lock missing');
assert.match(environment, /node: \{ exact: '22\.22\.3'/, 'environment: Node exact lock missing');
assert.match(environment, /npm: \{ exact: '10\.9\.8'/, 'environment: npm exact lock missing');
assert.match(environment, /postgres: \{ exact: '18\.4'/, 'environment: PostgreSQL exact lock missing');
assert.match(environment, /function exact\(/, 'environment: exact version comparator missing');

const workflow = fs.readFileSync(path.join(root, '.github', 'workflows', 'verification.yml'), 'utf8');
assert.match(workflow, /permissions:\s*\n\s*contents: read/, 'CI: workflow must use least-privilege contents permission');
assert.match(workflow, /concurrency:\s*\n\s*group:/, 'CI: duplicate verification runs must be cancelable');
assert.match(workflow, /cancel-in-progress: true/, 'CI: superseded verification must be canceled');
assert.match(workflow, /PHP_VERSION: '8\.4\.14'/, 'CI: PHP must match the verified runtime lock');
assert.match(workflow, /COMPOSER_VERSION: '2\.9\.2'/, 'CI: Composer must match the verified runtime lock');
assert.match(workflow, /NODE_VERSION: '22\.22\.3'/, 'CI: Node must match the verified runtime lock');
assert.match(workflow, /NPM_VERSION: '10\.9\.8'/, 'CI: npm must match the verified runtime lock');
assert.match(workflow, /POSTGRES_VERSION: '18\.4'/, 'CI: PostgreSQL must match the verified runtime lock');
assert.match(workflow, /tools: composer:\$\{\{ env\.COMPOSER_VERSION \}\}/, 'CI: Composer must be explicitly pinned in PHP jobs');
assert.match(workflow, /npm ci --engine-strict/, 'CI: npm must enforce package engine constraints');
assert.match(workflow, /timeout-minutes:/, 'CI: jobs must have bounded execution time');
assert.match(workflow, /E2E_USERNAME=ci\.e2e\.owner/, 'CI: isolated bootstrap username must be passed to browser E2E');
assert.match(workflow, /E2E_PASSWORD=\$E2E_PASSWORD/, 'CI: isolated bootstrap password must be passed to browser E2E');
assert.match(workflow, /image: postgres:18\.4/, 'CI: database version must be pinned');

const retrySweep = fs.readFileSync(path.join(root, 'app', 'Modules', 'Integrations', 'Jobs', 'IntegrationRetrySweepJob.php'), 'utf8');
assert.match(retrySweep, /DEFAULT_BATCH = 100/, 'integrations: retry sweep default batch missing');
assert.match(retrySweep, /MAX_BATCH = 500/, 'integrations: retry sweep safety ceiling missing');
assert.match(retrySweep, /->limit\(\$batch\)/, 'integrations: retry sweep must be bounded per invocation');
assert.match(retrySweep, /integrations\.retry_operator_required/, 'integrations: retry sweep must require explicit operator identity');

const hr = fs.readFileSync(path.join(jsRoot, 'hr.tsx'), 'utf8');
assert.match(hr, /function confirmAction/, 'HR: lifecycle confirmation helper missing');
assert.match(hr, /action === 'terminate'/, 'HR: termination must be classified as irreversible');
assert.match(hr, /humanize\(action\).*?confirmAction/s, 'HR: employment lifecycle transition must confirm before command');
assert.match(hr, /event\.key === 'ArrowRight' \|\| event\.key === 'ArrowDown'/, 'HR: tab keyboard traversal missing');
assert.match(hr, /event\.key === 'ArrowLeft' \|\| event\.key === 'ArrowUp'/, 'HR: reverse tab keyboard traversal missing');
assert.match(hr, /aria-controls/, 'HR: tabs must reference their tabpanels');

const payroll = fs.readFileSync(path.join(jsRoot, 'payroll.tsx'), 'utf8');
assert.match(payroll, /function confirmAction/, 'Payroll: decision confirmation helper missing');
assert.match(payroll, /Close Payroll period .*?confirmAction/s, 'Payroll: closing a period must confirm');
assert.match(payroll, /Approve Payroll calculation .*?confirmAction/s, 'Payroll: approving a calculation must confirm');
assert.match(payroll, /event\.key === 'ArrowRight' \|\| event\.key === 'ArrowDown'/, 'Payroll: tab keyboard traversal missing');
assert.match(payroll, /event\.key === 'ArrowLeft' \|\| event\.key === 'ArrowUp'/, 'Payroll: reverse tab keyboard traversal missing');
assert.match(payroll, /payroll-panel-calculations/, 'Payroll: calculations tabpanel must have a distinct id');

const blade = fs.readFileSync(path.join(viewsRoot, 'workspace.blade.php'), 'utf8');
assert.match(blade, /@vite\('resources\/js\/app\.tsx'\)/, 'workspace blade: canonical app entrypoint missing');
assert.match(blade, /toefl-house-ultimate\.css/, 'workspace blade: global visual contract missing');
assert.match(blade, /toefl-house-route-state\.css/, 'workspace blade: route-aware navigation contract missing');
assert.match(blade, /toefl-house-placement\.css/, 'workspace blade: Placement visual contract missing');
assert.match(blade, /toefl-house-operations\.css/, 'workspace blade: operational visual contract missing');

const legacyLayout = fs.readFileSync(path.join(viewsRoot, 'layouts', 'app.blade.php'), 'utf8');
assert.match(legacyLayout, /toefl-house-ultimate\.css/, 'legacy layout: unified visual contract missing');
assert.match(legacyLayout, /toefl-house-operations\.css/, 'legacy layout: operational visual contract missing');
assert.match(legacyLayout, /toefl-house-legacy-operations\.css/, 'legacy layout: legacy operations contract missing');

const specialistBlades = {
  placement: ['placement', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-placement\\.css']],
  finance: ['finance', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  hr: ['hr', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  payroll: ['payroll', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  reporting: ['reporting', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  organization: ['organization', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  identity: ['identity', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
  access: ['access', 'index.blade.php', ['toefl-house-ultimate\\.css', 'toefl-house-route-state\\.css', 'toefl-house-operations\\.css']],
};
for (const [domain, [folder, filename, cssContracts]] of Object.entries(specialistBlades)) {
  const file = path.join(viewsRoot, folder, filename);
  assert.ok(fs.existsSync(file), `${domain}: specialist Blade mount is missing`);
  const source = fs.readFileSync(file, 'utf8');
  for (const contract of cssContracts) assert.match(source, new RegExp(contract), `${domain}: ${contract} is not loaded`);
}

const legacyViews = ['library', 'communication', 'documents', 'audit', 'privacy'];
for (const folder of legacyViews) {
  const file = path.join(viewsRoot, folder, 'index.blade.php');
  assert.ok(fs.existsSync(file), `${folder}: legacy domain Blade mount is missing`);
}

const placementBlade = fs.readFileSync(path.join(viewsRoot, 'placement', 'index.blade.php'), 'utf8');
assert.match(placementBlade, /id="placement-console"/, 'placement: console mount missing');
assert.match(placementBlade, /resources\/js\/placement\.tsx/, 'placement: specialist entrypoint missing');

const routes = fs.readFileSync(path.join(root, 'routes', 'web.php'), 'utf8');
for (const pathFragment of ['/placement', '/hr', '/library', '/finance', '/communication', '/payroll', '/reporting', '/documents', '/access', '/privacy', '/audit', '/print']) {
  assert.ok(routes.includes(`prefix('${pathFragment.slice(1)}')`), `web routes: ${pathFragment} prefix is missing`);
}
assert.match(routes, /Route::view\('\/workspace', 'workspace'\)->name\('workspace'\)/, 'web routes: canonical workspace route missing');
assert.match(routes, /Route::get\('\/', fn \(\) => redirect\(\)->route\('workspace'\)\)->name\('home'\)/, 'web routes: home must redirect to canonical workspace');

const lifecycle = fs.readFileSync(
  path.join(root, 'app', 'Modules', 'Academic', 'Placement', 'Domain', 'PlacementProfileLifecycle.php'),
  'utf8',
);
for (const state of ['STATE_DRAFT', 'STATE_SCORED', 'STATE_RECOMMENDED', 'STATE_REVIEWED', 'STATE_APPROVED', 'STATE_RELEASED']) {
  assert.match(lifecycle, new RegExp(state), `placement lifecycle: ${state} is no longer declared`);
}
assert.match(lifecycle, /allowsTransition/, 'placement lifecycle: transition authority missing');
assert.match(lifecycle, /requireTransition/, 'placement lifecycle: server-side transition guard missing');

console.log('Frontend/domain contract tests: PASS');
