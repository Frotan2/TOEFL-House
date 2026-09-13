import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(path, 'utf8');
const containsAll = (source, markers, label) => {
  for (const marker of markers) assert.ok(source.includes(marker), `${label} is missing: ${marker}`);
};

const [routes, controller, students] = await Promise.all([
  read('routes/api.php'),
  read('app/Http/Controllers/Api/StudentsApiController.php'),
  read('resources/js/students.tsx'),
]);

containsAll(routes, [
  "Route::post('/{studentId}/status/{action}'",
  "Route::post('/{studentId}/transfer'",
  "Route::post('/{studentId}/hold'",
  "Route::post('/{studentId}/guardians'",
  "Route::post('/guardians/{relationshipId}/{action}'",
  "Route::post('/{studentId}/communication-preference'",
], 'Students lifecycle API closure route contract');

containsAll(controller, [
  'TransitionStudentStatus::class',
  'TransferStudentHomeBranch::class',
  'ManageStudentHold::class',
  'MaintainGuardianRelationship::class',
  'MaintainStudentCommunicationPreference::class',
  "'status_manage'",
  "'status_reactivate'",
  "'transfer'",
  "'hold'",
  "'communication'",
  "'guardian'",
], 'Students lifecycle server authority contract');

containsAll(students, [
  'statusAction',
  '/students/${encodeURIComponent(lifecycle.student_id)}/status/',
  '/students/${encodeURIComponent(lifecycle.student_id)}/transfer',
  '/students/${encodeURIComponent(lifecycle.student_id)}/hold',
  '/students/${encodeURIComponent(lifecycle.student_id)}/guardians',
  '/students/guardians/${encodeURIComponent',
  '/students/${encodeURIComponent(lifecycle.student_id)}/communication-preference',
  'lifecycle.capabilities.status_reactivate',
  'lifecycle.capabilities.transfer',
  'lifecycle.capabilities.hold',
  'lifecycle.capabilities.communication',
  'lifecycle.capabilities.guardian',
], 'Students lifecycle React contract');

console.log('Students lifecycle backend/controller/React closure sentinels passed.');
