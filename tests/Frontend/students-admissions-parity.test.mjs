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
  "Route::prefix('students')",
  "Route::post('/applicants'",
  "Route::post('/applicants/{applicantId}/reopen'",
  "Route::post('/applicants/{applicantId}/initiate'",
  "Route::post('/decisions/{decisionId}/review'",
  "Route::post('/decisions/{decisionId}/approve'",
  "Route::post('/applicants/{applicantId}/enroll'",
  "Route::post('/{studentId}/status/{action}'",
  "Route::post('/{studentId}/transfer'",
  "Route::post('/{studentId}/hold'",
  "Route::post('/{studentId}/guardians'",
  "Route::post('/{studentId}/communication-preference'",
], 'Students API route contract');

containsAll(controller, [
  "'admission_register'",
  "'admission_initiate'",
  "'admission_review'",
  "'admission_approve'",
  "'student_transfer'",
  "'student_guardian'",
  "finance_obligation",
  "finance_payment",
  "app(RegisterApplicant::class)",
  "app(ReopenApplicant::class)",
  "app(DecideAdmission::class)",
  "app(EnrollAdmittedApplicant::class)",
], 'Students server authority contract');

containsAll(students, [
  '/students/applicants',
  '/students/applicants/${encodeURIComponent(applicant.id)}/reopen',
  '/students/applicants/${encodeURIComponent(applicant.id)}/initiate',
  '/students/decisions/${encodeURIComponent(decision.id)}/review',
  '/students/decisions/${encodeURIComponent(decision.id)}/approve',
  '/students/applicants/${encodeURIComponent(applicant.id)}/enroll',
  "capabilities.admission_review",
  "capabilities.admission_approve",
  "capabilities.admission_register",
], 'Students React lifecycle contract');

console.log('Students↔Admissions backend/frontend parity sentinels passed.');
