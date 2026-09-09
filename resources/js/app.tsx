import { createRoot } from 'react-dom/client';
import './app.css';
import './experience-home.css';
import { AcademicApp } from './academic';
import { AcademicSetupApp } from './academic-setup';
import { TeacherApp } from './teacher';
import { CrmApp } from './crm';
import { ManagementApp } from './management';
import { StudentsApp } from './students';
import { WorkspaceApp } from './workspace';
import { IdentityApp } from './identity';
import { createApiClient } from './core/api';

const root = document.getElementById('react-console');
if (root) {
  const csrfToken = root.getAttribute('data-csrf-token') ?? '';
  const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken });
  const common = { ...api, csrfToken };
  const view = root.getAttribute('data-view');
  const academicSetupRequested = view === 'academic' && new URLSearchParams(window.location.search).get('view') === 'setup';
  const content = academicSetupRequested
    ? <AcademicSetupApp {...common} />
    : view === 'academic'
      ? <AcademicApp {...common} />
      : view === 'teachers'
        ? <TeacherApp {...common} />
        : view === 'crm'
          ? <CrmApp {...common} />
          : view === 'management'
            ? <ManagementApp {...common} />
            : view === 'identity'
              ? <IdentityApp {...common} />
              : view === 'students'
                ? <StudentsApp {...common} studentsView={root.getAttribute('data-students-view') ?? 'directory'} studentId={root.getAttribute('data-student-id') ?? ''} />
                : <WorkspaceApp {...common} />;
  createRoot(root).render(content);
}