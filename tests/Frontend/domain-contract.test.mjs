import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const viewsRoot = path.join(root, 'resources', 'views');
const workspaceBlade = fs.readFileSync(path.join(viewsRoot, 'workspace.blade.php'), 'utf8');
for (const contract of [
  "@vite('resources/js/app.tsx')",
  'toefl-house-ultimate.css',
  'toefl-house-route-state.css',
  'toefl-house-placement.css',
  'toefl-house-operations.css',
  '<base href="{{ url(\'/\') }}/">',
]) assert.ok(workspaceBlade.includes(contract), `workspace Blade contract missing: ${contract}`);
const legacyLayout = fs.readFileSync(path.join(viewsRoot, 'layouts', 'app.blade.php'), 'utf8');
for (const contract of [
  'toefl-house-ultimate.css',
  'toefl-house-operations.css',
  'toefl-house-legacy-operations.css',
]) assert.ok(legacyLayout.includes(contract), `legacy layout contract missing: ${contract}`);
assert.match(legacyLayout, /<base href="\/">/, 'legacy layout must use a root-relative document base');
for (const folder of ['library', 'communication', 'documents', 'audit', 'privacy']) assert.ok(fs.existsSync(path.join(viewsRoot, folder, 'index.blade.php')), `${folder}: legacy boundary missing`);

console.log('PASS  frontend domain, transport, shell, CI and legacy-boundary contracts');
