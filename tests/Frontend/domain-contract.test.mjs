import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const jsRoot = path.join(root, 'resources', 'js');

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

const app = fs.readFileSync(path.join(jsRoot, 'app.tsx'), 'utf8');
assert.match(app, /react-console/, 'app.tsx: canonical React console mount must remain present');

const ui = fs.readFileSync(path.join(jsRoot, 'ui.tsx'), 'utf8');
assert.match(ui, /metaKey\s*\|\|\s*event\.ctrlKey/, 'shell: Ctrl/⌘+K command palette shortcut missing');
assert.match(ui, /event\.key === 'ArrowDown'/, 'shell: ArrowDown traversal missing');
assert.match(ui, /event\.key === 'ArrowUp'/, 'shell: ArrowUp traversal missing');
assert.match(ui, /event\.key === 'Enter'/, 'shell: Enter activation missing');
assert.match(ui, /event\.key === 'Escape'/, 'shell: Escape dismissal missing');
assert.match(ui, /aria-selected/, 'shell: active command palette option must be exposed semantically');

const blade = fs.readFileSync(path.join(root, 'resources', 'views', 'workspace.blade.php'), 'utf8');
assert.match(blade, /@vite\('resources\/js\/app\.tsx'\)/, 'workspace blade: canonical app entrypoint missing');
assert.match(blade, /toefl-house-ultimate\.css/, 'workspace blade: global visual contract missing');
assert.match(blade, /toefl-house-route-state\.css/, 'workspace blade: route-aware navigation contract missing');
assert.match(blade, /toefl-house-placement\.css/, 'workspace blade: Placement visual contract missing');
assert.match(blade, /toefl-house-operations\.css/, 'workspace blade: operational visual contract missing');

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
