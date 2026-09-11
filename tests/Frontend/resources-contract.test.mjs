import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const literalPattern = (value) => new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

test('Library & Resources is mounted in the unified React console', () => {
  const app = read('resources/js/app.tsx');
  const library = read('resources/js/library.tsx');
  assert.match(app, /import \{ LibraryApp \} from ['"]\.\/library['"]/);
  assert.match(app, /case ['"]library['"]:/);
  assert.match(library, /AppShell current="library"/);
  // LibraryApp is imported by the canonical app entrypoint. It must stay a
  // pure feature component: a second root on #react-console races the shared
  // root and can leave unrelated routes blank after React reconciliation.
  assert.doesNotMatch(library, /from ['"]react-dom\/client['"]/);
  assert.doesNotMatch(library, /\bcreateRoot\s*\(/);
  assert.doesNotMatch(library, /document\.getElementById\(['"]react-console['"]\)/);
  assert.equal((app.match(/\bcreateRoot\s*\(/g) ?? []).length, 1, 'the canonical app entrypoint owns exactly one shared-console root');
  assert.match(library, /\/resources\/workspace/);
});

test('Library API routes expose the full resource lifecycle through v1', () => {
  const routes = read('routes/resources-api.php');
  for (const token of [
    "Route::get('/workspace'",
    "Route::post('/books'",
    "Route::post('/books/{copyId}/issue'",
    "Route::post('/issuances/{issuanceId}/return'",
    "Route::post('/issuances/{issuanceId}/loss'",
    "Route::post('/assets'",
    "Route::post('/assets/{assetId}/custody'",
    "Route::post('/assets/{assetId}/disposal'",
    "Route::post('/disposals/{requestId}/approve'",
    "Route::post('/disposals/{requestId}/execute'",
    "Route::post('/work-orders'",
    "Route::post('/work-orders/{orderId}/approve'",
    "Route::post('/work-orders/{orderId}/start'",
    "Route::post('/work-orders/{orderId}/complete'",
    "Route::post('/work-orders/{orderId}/cancel'",
  ]) assert.match(routes, literalPattern(token));
});

test('Library API is mounted under the canonical authenticated v1 route stack', () => {
  const bootstrap = read('bootstrap/app.php');
  const controller = read('app/Http/Controllers/Api/ResourcesApiController.php');
  assert.match(bootstrap, /routes\/resources-api\.php/);
  assert.match(controller, /final class ResourcesApiController/);
  assert.match(controller, /authorizedBranches\('resources\.books'\)/);
  assert.match(controller, /authorizedBranches\('resources\.asset'\)/);
  assert.match(controller, /authorizedBranches\('facilities\.work'\)/);
  assert.match(controller, /idempotencyKey\(/);
});
