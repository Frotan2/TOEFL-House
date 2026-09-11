import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const literalPattern = (value) => new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

test('Documents & Evidence is mounted by the canonical React console', () => {
  const app = read('resources/js/app.tsx');
  const documents = read('resources/js/documents.tsx');

  assert.match(app, /import \{ DocumentsApp \} from ['"]\.\/documents['"]/);
  assert.match(app, /case ['"]documents['"]:/);
  assert.match(documents, /AppShell current="documents"/);
  assert.match(documents, /\/documents/);
  assert.match(documents, /available_actions/);
  assert.match(documents, /\/history/);
  assert.doesNotMatch(documents, /from ['"]react-dom\/client['"]/);
  assert.doesNotMatch(documents, /\bcreateRoot\s*\(/);
  assert.doesNotMatch(documents, /\bfetch\s*\(/);
});

test('Documents API routes expose the canonical lifecycle and history contract', () => {
  const routes = read('routes/documents-api.php');

  for (const token of [
    "Route::prefix('documents')->name('api.documents.')",
    "Route::get('/', [DocumentsApiController::class, 'workspace'])",
    "Route::post('/classifications', [DocumentsApiController::class, 'defineClassification'])",
    "Route::post('/retention-rules', [DocumentsApiController::class, 'defineRetentionRule'])",
    "Route::post('/', [DocumentsApiController::class, 'register'])",
    "Route::get('/{documentId}/history', [DocumentsApiController::class, 'history'])",
    "Route::post('/{documentId}/submit', [DocumentsApiController::class, 'submit'])",
    "Route::post('/{documentId}/verify', [DocumentsApiController::class, 'verify'])",
    "Route::post('/{documentId}/activate', [DocumentsApiController::class, 'activate'])",
    "Route::post('/{documentId}/expire', [DocumentsApiController::class, 'expire'])",
    "Route::post('/{documentId}/archive', [DocumentsApiController::class, 'archive'])",
    "Route::post('/{documentId}/retention', [DocumentsApiController::class, 'decideRetention'])",
  ]) assert.match(routes, literalPattern(token));
});

test('Documents read projection is server-scoped, minimal, and command-owned', () => {
  const bootstrap = read('bootstrap/app.php');
  const controller = read('app/Http/Controllers/Api/DocumentsApiController.php');
  const webRoutes = read('routes/web.php');

  assert.match(bootstrap, /routes\/documents-api\.php/);
  for (const capability of ['RegisterDocument::CAPABILITY', 'TransitionDocument::CAPABILITY', 'DecideRetention::CAPABILITY']) {
    assert.match(controller, literalPattern(capability));
  }
  assert.match(controller, /defineClassification.*null/s);
  assert.match(controller, /DocumentHistoryQuery::class/);
  assert.match(controller, /Per-record affordances prevent a capability in branch A/);
  assert.match(controller, /Storage references, content fingerprints, and verification/);
  assert.doesNotMatch(controller, /use App\\Modules\\Documents\\Models\\DocumentVersion/);
  assert.match(webRoutes, /Route::view\('\/', 'workspace', \['view' => 'documents'\]\)->name\('index'\)/);
  assert.ok(!fs.existsSync(path.join(root, 'resources/views/documents/index.blade.php')), 'Documents must not retain a second Blade read model.');
});
