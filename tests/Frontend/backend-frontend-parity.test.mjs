import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(path, 'utf8');
const containsAll = (source, markers, label) => {
  for (const marker of markers) assert.ok(source.includes(marker), `${label} is missing: ${marker}`);
};

const [resourceRoutes, resourceController, library, apiRoutes, placement, teacher, hr, navigation] = await Promise.all([
  read('routes/resources-api.php'),
  read('app/Http/Controllers/Api/ResourcesApiController.php'),
  read('resources/js/library.tsx'),
  read('routes/api.php'),
  read('resources/js/placement.tsx'),
  read('resources/js/teacher.tsx'),
  read('resources/js/hr.tsx'),
  read('resources/js/core/navigation.ts'),
]);

containsAll(resourceRoutes, [
  "'/books'", "'/books/{copyId}/issue'", "'/issuances/{issuanceId}/return'", "'/issuances/{issuanceId}/loss'",
  "'/assets'", "'/assets/{assetId}/custody'", "'/assets/{assetId}/custody/release'", "'/assets/{assetId}/disposal'",
  "'/disposals/{requestId}/approve'", "'/disposals/{requestId}/execute'", "'/work-orders'", "'/work-orders/{orderId}/approve'",
  "'/work-orders/{orderId}/start'", "'/work-orders/{orderId}/complete'", "'/work-orders/{orderId}/cancel'",
], 'Resources API route contract');

containsAll(resourceController, [
  "'borrower_id' => ['required', 'string']",
  "'custodian_id' => ['required', 'string']",
  "'disposed_on' => ['required', 'date']",
], 'Resources transport validation contract');

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
  'selectedBorrowerId',
  'selectedCustodianId',
  "{ borrower_id: borrower.id, issued_on: today(), due_on: due }",
  "{ custodian_id: person.id, assigned_on: assigned }",
  "{ disposed_on: disposedOn }",
  'function executeDisposal(request: RecordMap)',
], 'Library workspace capability contract');

containsAll(apiRoutes, [
  "use App\\Http\\Controllers\\Api\\PlacementApiController;",
  "Route::prefix('placement')->name('api.placement.')",
  "Route::post('/attempts', [PlacementApiController::class, 'startAttempt'])",
  "Route::post('/attempts/{attemptId}/submit', [PlacementApiController::class, 'submitDigital'])",
  "Route::post('/attempts/{attemptId}/cancel', [PlacementApiController::class, 'cancelAttempt'])",
  "Route::prefix('teachers')->name('api.teachers.')",
  "Route::get('/workspace', [TeacherApiController::class, 'workspace'])",
  "Route::post('/profiles/{profileId}/qualifications', [TeacherApiController::class, 'qualification'])",
  "Route::post('/qualifications/{qualificationId}/verify', [TeacherApiController::class, 'verifyQualification'])",
  "Route::post('/assignments/{assignmentId}/skills', [TeacherApiController::class, 'assignSkill'])",
  "Route::prefix('hr')->name('api.hr.')",
  "Route::get('/workspace', [HrApiController::class, 'workspace'])",
  "Route::post('/employ', [HrApiController::class, 'employ'])",
], 'canonical People/Placement API route contract');

containsAll(placement, [
  '/placement/attempts/${encodeURIComponent(attempt.id)}/submit',
  '/placement/attempts/${encodeURIComponent(attempt.id)}/cancel',
  '/placement/section-results/${encodeURIComponent(result.id)}/moderate',
  '/placement/section-results/${encodeURIComponent(result.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/recommend',
  '/placement/profiles/${encodeURIComponent(profile.id)}/approve',
  '/placement/profiles/${encodeURIComponent(profile.id)}/release',
], 'Placement workspace lifecycle');

containsAll(teacher, [
  "getJson<{ data: TeacherWorkspace }>('/teachers/workspace')",
  '`/teachers/profiles/${encodeURIComponent(selected.id)}/qualifications'",
  '`/teachers/qualifications/${encodeURIComponent(item.id)}/verify'",
  '`/teachers/assignments/${encodeURIComponent(assignmentId)}/skills'",
  'React projection · domain commands own writes',
], 'Teacher workspace lifecycle');

containsAll(hr, [
  "getJson<{ data: Workspace }>('/hr/workspace')",
  "postJson('/hr/employ', { person_id: employPersonId })",
  '`/hr/employments/${encodeURIComponent(selected.id)}/${action}'",
  "'/hr/employments/${encodeURIComponent(selected.id)}/place-on-leave'",
  '`/hr/employments/${encodeURIComponent(selected.id)}/leave'",
], 'HR workspace lifecycle');

assert.match(navigation, /['\"]\/library['\"]/);
console.log('Backend↔frontend parity sentinels passed.');
