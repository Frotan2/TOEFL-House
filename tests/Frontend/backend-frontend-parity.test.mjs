import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(path, 'utf8');
const containsAll = (source, markers, label) => {
  for (const marker of markers) assert.ok(source.includes(marker), `${label} is missing: ${marker}`);
};

const [resourceRoutes, library, placementRoutes, placement, navigation] = await Promise.all([
  read('routes/resources-api.php'),
  read('resources/js/library.tsx'),
  read('routes/placement-api.php'),
  read('resources/js/placement.tsx'),
  read('resources/js/core/navigation.ts'),
]);

containsAll(resourceRoutes, [
  "'/books'", "'/books/{copyId}/issue'", "'/issuances/{issuanceId}/return'", "'/issuances/{issuanceId}/loss'",
  "'/assets'", "'/assets/{assetId}/custody'", "'/assets/{assetId}/custody/release'", "'/assets/{assetId}/disposal'",
  "'/disposals/{requestId}/approve'", "'/disposals/{requestId}/execute'", "'/work-orders'", "'/work-orders/{orderId}/approve'",
  "'/work-orders/{orderId}/start'", "'/work-orders/{orderId}/complete'", "'/work-orders/{orderId}/cancel'",
], 'Resources API route contract');

containsAll(library, [
  '/resources/books/${copy.id}/issue',
  '/resources/issuances/${loan.id}/return',
  '/resources/issuances/${loan.id}/loss',
  '/resources/assets/${asset.id}/custody',
  '/resources/assets/${asset.id}/custody/release',
  '/resources/assets/${asset.id}/disposal',
  '/resources/disposals/${request.id}/approve',
  '/resources/disposals/${request.id}/execute',
  '/resources/work-orders/${work.id}/approve',
  '/resources/work-orders/${work.id}/start',
  '/resources/work-orders/${work.id}/complete',
  '/resources/work-orders/${work.id}/cancel',
], 'Library workspace capability contract');

containsAll(placementRoutes, [
  "'/placement/attempts'", "'/placement/attempts/{attemptId}/submit'", "'/placement/attempts/{attemptId}/cancel'",
], 'Placement API base contract');
containsAll(placement, [
  '/placement/attempts/${encodeURIComponent(attempt.id)}/submit',
  '/placement/attempts/${encodeURIComponent(attempt.id)}/cancel',
  '/placement/section-results/${encodeURIComponent(result.id)}/moderate',
  '/placement/section-results/${encodeURIComponent(result.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/recommend',
  '/placement/profiles/${encodeURIComponent(profile.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/release',
], 'Placement workspace lifecycle');

assert.match(navigation, /['\"]\/library['\"]/);
console.log('Backend↔frontend parity sentinels passed.');
