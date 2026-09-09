/**
 * Shared frontend productization contract tests.
 *
 * These are intentionally source-level invariants: they protect the shared shell
 * from reintroducing accessibility defects that are otherwise easy to miss in a
 * route-by-route browser check.
 */
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ui = await readFile(resolve(repoRoot, 'resources/js/ui.tsx'), 'utf8');
const navigation = await readFile(resolve(repoRoot, 'resources/js/core/navigation.ts'), 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(ui.includes('querySelector(\'main\')'), 'Skip-link must resolve the actual page <main>, not a hard-coded page id.');
assert(ui.includes('aria-controls="primary-navigation"'), 'Mobile navigation trigger must identify the controlled navigation region.');
assert(ui.includes('aria-expanded={mobileOpen}'), 'Mobile navigation trigger must expose its open state.');
assert(ui.includes("event.key === 'Escape'"), 'Mobile navigation and command palette must expose keyboard dismissal.');
assert(ui.includes('returnFocusRef.current?.focus()'), 'Command palette must return focus to its invoking control.');
assert(!ui.includes('aria-hidden={!mobileOpen}'), 'Desktop sidebar must not be hidden from assistive technology when mobile navigation is closed.');

const keys = [...navigation.matchAll(/key: '([^']+)'/g)].map((match) => match[1]);
assert(keys.length === new Set(keys).size, 'Navigation keys must be unique to preserve a single active navigation authority.');

console.log('PASS  shared frontend accessibility/navigation contracts');
