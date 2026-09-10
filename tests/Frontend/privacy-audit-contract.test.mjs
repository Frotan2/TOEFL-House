import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (path) => fs.readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('privacy and audit React surfaces consume server projections and expose no client authority model', () => {
  const privacy = read('resources/js/privacy.tsx');
  const audit = read('resources/js/audit.tsx');
  assert.match(privacy, /\/privacy\/workspace/);
  assert.match(privacy, /server-authorized/);
  assert.match(audit, /\/audit\/workspace/);
  assert.match(audit, /immutable audit evidence/);
  assert.doesNotMatch(privacy, /role\s*===|roles\.includes|capabilit(?:y|ies)\s*===/i);
  assert.doesNotMatch(audit, /role\s*===|roles\.includes|capabilit(?:y|ies)\s*===/i);
});

test('canonical governance routes mount the React views', () => {
  const app = read('resources/js/app.tsx');
  const bootstrap = read('bootstrap/app.php');
  assert.match(app, /case 'privacy':/);
  assert.match(app, /case 'audit':/);
  assert.match(bootstrap, /governance\/privacy/);
  assert.match(bootstrap, /governance\/audit/);
});
