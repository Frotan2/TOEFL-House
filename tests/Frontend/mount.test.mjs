/**
 * Frontend mount regression test.
 *
 * Guards the defect class where a React console bundle builds and typechecks
 * successfully but throws at runtime on load (for example a missing
 * `createRoot` import), leaving the mount element empty and the workspace
 * silently blank for the user.
 *
 * Each console entrypoint is bundled and evaluated in a real DOM. The console
 * must mount without throwing.
 *
 * Run: npm run test:frontend
 */
import { JSDOM } from 'jsdom';
import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

/** Console entrypoints that mount themselves into a Blade-provided element. */
const CONSOLES = [
  'finance',
  'reporting',
  'access',
  'identity',
  'organization',
  'placement',
  'payroll',
  'hr',
];

async function bundle(name) {
  const result = await build({
    entryPoints: [resolve(repoRoot, `resources/js/${name}.tsx`)],
    bundle: true,
    write: false,
    format: 'iife',
    jsx: 'automatic',
    loader: { '.css': 'empty' },
    define: { 'process.env.NODE_ENV': '"production"' },
    logLevel: 'silent',
  });
  return result.outputFiles[0].text;
}

async function mountConsole(name, code) {
  const mountId = `${name}-console`;
  const dom = new JSDOM(
    `<!doctype html><html><body><div id="${mountId}" data-csrf-token="test-token" data-api-base="/api/v1"></div></body></html>`,
    { runScripts: 'outside-only', url: 'https://app.test/', pretendToBeVisual: true },
  );

  const { window } = dom;
  // Never-resolving fetch: this asserts first paint, not loaded data.
  window.fetch = () => new Promise(() => {});

  const errors = [];
  window.addEventListener('error', (event) => errors.push(String(event.message)));

  try {
    window.eval(code);
  } catch (error) {
    errors.push(error.message);
  }

  await new Promise((resolveTick) => setTimeout(resolveTick, 300));

  const element = window.document.getElementById(mountId);
  const mounted = element !== null && element.childNodes.length > 0;
  window.close();

  return { mounted, errors };
}

let failures = 0;

for (const name of CONSOLES) {
  let outcome;
  try {
    outcome = await mountConsole(name, await bundle(name));
  } catch (error) {
    outcome = { mounted: false, errors: [`bundle failed: ${error.message}`] };
  }

  const ok = outcome.mounted && outcome.errors.length === 0;
  if (!ok) {
    failures += 1;
  }

  const detail = outcome.errors.length > 0 ? outcome.errors[0] : 'rendered';
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(14)} ${detail}`);
}

if (failures > 0) {
  console.error(`\n${failures} console(s) failed to mount.`);
  process.exit(1);
}

console.log(`\nAll ${CONSOLES.length} consoles mounted.`);
