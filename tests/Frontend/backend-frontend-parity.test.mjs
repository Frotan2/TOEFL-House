import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(path, 'utf8');

const [resourceRoutes, library, placementRoutes, placement, navigation] = await Promise.all([
  read('routes/resources-api.php'),
  read('resources/js/library.tsx'),
  read('routes/placement-api.php'),
  read('resources/js/placement.tsx'),
  read('resources/js/core/navigation.ts'),
]);

// Concrete parity contract: every user-facing Resources lifecycle action exposed by
// the canonical API must remain reachable from the modern Library workspace.
for (const endpoint of [
  '/resources/books',
  '/resources/issuances/',
  '/resources/assets',
  '/resources/assets/',
  '/resources/disposals/',
  '/resources/work-orders',
]) {
  assert.match(resourceRoutes, new RegExp(endpoint.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')));
}
for (const endpoint of [
  '/resources/disposals/${request.id}/approve',
  '/resources/disposals/${request.id}/execute',
]) {
  assert.ok(library.includes(endpoint), `Library workspace is missing Resources capability: ${endpoint}`);
}

// The placement workspace must preserve the server-owned decision lifecycle.
for (const endpoint of [
  '/placement/attempts',
  '/placement/section-results/',
  '/placement/profiles/',
]) assert.match(placementRoutes, new RegExp(endpoint.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')));
for (const marker of [
  '/placement/attempts/${encodeURIComponent(attempt.id)}/submit',
  '/placement/attempts/${encodeURIComponent(attempt.id)}/cancel',
  '/placement/section-results/${encodeURIComponent(result.id)}/moderate',
  '/placement/section-results/${encodeURIComponent(result.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/recommend',
  '/placement/profiles/${encodeURIComponent(profile.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/release',
]) assert.ok(placement.includes(marker), `Placement workspace lost lifecycle marker: ${marker}`);

// Canonical navigation must retain the Resource workspace once it becomes converged.
assert.match(navigation, /['\"]\/library['\"]/);

console.log('Backend↔frontend parity sentinels passed.');
