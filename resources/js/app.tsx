import { createRoot } from 'react-dom/client';
import './app.css';
import './experience-home.css';
import './academic-setup.css';
import './student-journey.css';
import './teacher-day.css';
import './front-office.css';
import './command-palette.css';
import './product-theme.css';
import { AcademicApp } from './academic';
import { AcademicSetupApp } from './academic-setup';
import { TeacherApp } from './teacher';
import { TeacherDayApp } from './teacher-day';
import { CrmApp } from './crm';
import { FrontOfficeApp } from './front-office';
import { ManagementApp } from './management';
import { StudentJourneyApp } from './student-journey';
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
  const query = new URLSearchParams(window.location.search);
  const academicSetupRequested = view === 'academic' && query.get('view') === 'setup';
  const studentJourneyRequested = view === 'students' && query.get('view') === 'journey' && root.getAttribute('data-student-id') !== null && root.getAttribute('data-student-id') !== '';
  const teacherDayRequested = view === 'teachers' && query.get('view') === 'day';
  const frontOfficeRequested = view === 'crm' && query.get('view') === 'front-office';
  const content = academicSetupRequested
    ? <AcademicSetupApp {...common} />
    : studentJourneyRequested
      ? <StudentJourneyApp {...common} studentId={root.getAttribute('data-student-id') ?? ''} />
      : teacherDayRequested
        ? <TeacherDayApp {...common} />
        : frontOfficeRequested
          ? <FrontOfficeApp {...common} />
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
