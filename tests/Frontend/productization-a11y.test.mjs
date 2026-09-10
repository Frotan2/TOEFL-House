/**
 * Frontend productization contracts.
 *
 * These source-level invariants protect the shared UX boundary against regressions
 * that route-only browser checks can miss: duplicated transport, hidden desktop
 * navigation, stale reporting mounts, weak responsive CSS, and token persistence.
 */
import { readFile, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const jsRoot = resolve(repoRoot, 'resources/js');
const ui = await readFile(resolve(jsRoot, 'ui.tsx'), 'utf8');
const navigation = await readFile(resolve(jsRoot, 'core/navigation.ts'), 'utf8');
const api = await readFile(resolve(jsRoot, 'core/api.ts'), 'utf8');
const app = await readFile(resolve(jsRoot, 'app.tsx'), 'utf8');
const workspaceBlade = await readFile(resolve(repoRoot, 'resources/views/workspace.blade.php'), 'utf8');
const appCss = await readFile(resolve(jsRoot, 'app.css'), 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function tsxFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) files.push(...await tsxFiles(path));
    else if (entry.isFile() && entry.name.endsWith('.tsx')) files.push(path);
  }
  return files;
}

assert(ui.includes("querySelector('main')"), 'Skip-link must resolve the actual page <main>, not a hard-coded page id.');
assert(ui.includes('aria-controls="primary-navigation"'), 'Mobile navigation trigger must identify the controlled navigation region.');
assert(ui.includes('aria-expanded={mobileOpen}'), 'Mobile navigation trigger must expose its open state.');
assert(ui.includes("event.key === 'Escape'"), 'Mobile navigation and command palette must expose keyboard dismissal.');
assert(ui.includes('returnFocusRef.current?.focus()'), 'Command palette must return focus to its invoking control.');
assert(ui.includes('aria-haspopup="dialog"'), 'Command palette trigger must expose its dialog relationship.');
assert(!ui.includes('aria-hidden={!mobileOpen}'), 'Desktop sidebar must not be hidden from assistive technology when mobile navigation is closed.');

const keys = [...navigation.matchAll(/key: '([^']+)'/g)].map((match) => match[1]);
assert(keys.length === new Set(keys).size, 'Navigation keys must be unique.');
assert(navigation.includes("href: '/workspace?view=reporting'"), 'Reporting must be reachable through the canonical workspace boundary.');
assert(!navigation.includes("href: '/reporting'"), 'Primary navigation must not point at the legacy reporting entrypoint.');

assert(app.includes("import { ReportingApp } from './reporting';"), 'Canonical app must own Reporting composition.');
assert(app.includes("case 'reporting':"), 'Canonical app must resolve the Reporting workspace.');
assert(workspaceBlade.includes("@vite('resources/js/app.tsx')"), 'Workspace Blade must load only the canonical React entrypoint.');
assert(!workspaceBlade.includes('reporting-console'), 'Workspace Blade must not expose a secondary Reporting mount.');
assert(!workspaceBlade.includes("@vite('resources/js/reporting.tsx')"), 'Workspace Blade must not mount Reporting through a second entrypoint.');

assert(api.includes("error: 'network_error'"), 'API client must normalize network failures.');
assert(api.includes('retryable: true'), 'Normalized transport failures must be retryable.');
assert(api.includes("new URL(response.url).pathname === '/login'"), 'Authentication redirects must be detected by URL pathname.');

assert(appCss.includes('@media'), 'Shared frontend CSS must contain responsive breakpoints.');
assert(appCss.includes('prefers-reduced-motion'), 'Shared frontend CSS must honor reduced-motion preferences.');
assert(appCss.includes('.table-wrap'), 'Data tables must have a responsive overflow container.');

for (const file of await tsxFiles(jsRoot)) {
  const source = await readFile(file, 'utf8');
  assert(!/localStorage\.(?:getItem|setItem)\s*\(\s*['"][^'"]*(?:token|jwt|access_token)/is.test(source), `${file}: browser token persistence is forbidden.`);
  assert(!/\bfetch\s*\(/.test(source.replace(/\/\/.*$/gm, '')), `${file}: direct fetch transport is forbidden; use core/api.ts.`);
}

console.log('PASS  frontend productization architecture, accessibility, transport and responsive contracts');
