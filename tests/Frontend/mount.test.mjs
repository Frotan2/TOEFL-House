/**
 * Frontend mount regression test.
 *
 * Guards the defect class where a React console bundle builds and typechecks
 * successfully but throws at runtime on load, leaving the mount element blank.
 *
 * Each legacy console and the canonical app entrypoint are bundled and
 * evaluated in a real DOM. The selected console must mount without throwing.
 *
 * Run: npm run test:frontend
 */
import { JSDOM } from 'jsdom';
import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

const CONSOLES = [
  { name: 'finance', entry: 'resources/js/finance.tsx', mountId: 'finance-console' },
  { name: 'reporting', entry: 'resources/js/reporting.tsx', mountId: 'reporting-console' },
  { name: 'access', entry: 'resources/js/access.tsx', mountId: 'access-console' },
  { name: 'identity', entry: 'resources/js/identity.tsx', mountId: 'identity-console' },
  { name: 'organization', entry: 'resources/js/organization.tsx', mountId: 'organization-console' },
  { name: 'placement', entry: 'resources/js/placement.tsx', mountId: 'placement-console' },
  { name: 'payroll', entry: 'resources/js/payroll.tsx', mountId: 'payroll-console' },
  { name: 'hr', entry: 'resources/js/hr.tsx', mountId: 'hr-console' },
  {
    name: 'app-library',
    entry: 'resources/js/app.tsx',
    mountId: 'react-console',
    url: 'https://app.test/library',
    markup: '<div id="react-console" data-view="library" data-csrf-token="test-token" data-api-base="/api/v1"></div>',
  },
  {
    name: 'app-front-office',
    entry: 'resources/js/app.tsx',
    mountId: 'react-console',
    url: 'https://app.test/crm?view=front-office',
    markup: '<div id="react-console" data-view="crm" data-students-view="directory" data-student-id="" data-csrf-token="test-token" data-api-base="/api/v1"></div>',
  },
  {
    name: 'app-reporting',
    entry: 'resources/js/app.tsx',
    mountId: 'react-console',
    url: 'https://app.test/workspace?view=reporting',
    markup: '<div id="react-console" data-view="reporting" data-csrf-token="test-token" data-api-base="/api/v1"></div>',
  },
];

async function bundle(entry) {
  const result = await build({
    entryPoints: [resolve(repoRoot, entry)],
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

async function mountConsole(consoleDefinition, code) {
  const mountId = consoleDefinition.mountId;
  const markup = consoleDefinition.markup ?? `<div id="${mountId}" data-csrf-token="test-token" data-api-base="/api/v1"></div>`;
  const dom = new JSDOM(
    `<!doctype html><html><body>${markup}</body></html>`,
    { runScripts: 'outside-only', url: consoleDefinition.url ?? 'https://app.test/', pretendToBeVisual: true },
  );

  const { window } = dom;
  // Never-resolving fetch: this asserts first paint, not loaded data.
  window.fetch = () => new Promise(() => {});

  const errors = [];
  window.addEventListener('error', (event) => errors.push(String(event.message)));

  try {
    window.eval(code);
  } catch (error) {
    errors.push(error instanceof Error ? error.message : String(error));
  }

  await new Promise((resolveTick) => setTimeout(resolveTick, 300));

  const element = window.document.getElementById(mountId);
  const mounted = element !== null && element.childNodes.length > 0;
  window.close();

  return { mounted, errors };
}

let failures = 0;

for (const consoleDefinition of CONSOLES) {
  const { name } = consoleDefinition;
  let outcome;
  try {
    outcome = await mountConsole(consoleDefinition, await bundle(consoleDefinition.entry));
  } catch (error) {
    outcome = { mounted: false, errors: [`bundle failed: ${error instanceof Error ? error.message : String(error)}`] };
  }

  const ok = outcome.mounted && outcome.errors.length === 0;
  if (!ok) failures += 1;

  const detail = outcome.errors.length > 0 ? outcome.errors[0] : 'rendered';
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(18)} ${detail}`);
}

if (failures > 0) {
  console.error(`\n${failures} console(s) failed to mount.`);
  process.exit(1);
}

console.log(`\nAll ${CONSOLES.length} console entrypoints mounted.`);
