import { createRoot } from 'react-dom/client';
import './app.css';
import './experience-home.css';
import './academic-setup.css';
import './student-journey.css';
import './teacher-day.css';
import './front-office.css';
import './command-palette.css';
import './product-theme.css';
import './core/error-boundary.css';
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
import { LibraryApp } from './library';
import { PrivacyApp } from './privacy';
import { AuditApp } from './audit';
import { createApiClient, type ApiClient } from './core/api';
import { AppErrorBoundary } from './core/error-boundary';

type ConsoleProps = ApiClient & { csrfToken: string };

type ConsoleView =
  | 'workspace'
  | 'students'
  | 'academic'
  | 'teachers'
  | 'crm'
  | 'management'
  | 'identity'
  | 'library'
  | 'privacy'
  | 'audit';

function resolveContent(view: string | null, query: URLSearchParams, props: ConsoleProps) {
  switch (view as ConsoleView | null) {
    case 'academic':
      return query.get('view') === 'setup' ? <AcademicSetupApp {...props} /> : <AcademicApp {...props} />;
    case 'students': {
      const studentId = document.getElementById('react-console')?.getAttribute('data-student-id') ?? '';
      if (query.get('view') === 'journey' && studentId) return <StudentJourneyApp {...props} studentId={studentId} />;
      return <StudentsApp {...props} studentsView={document.getElementById('react-console')?.getAttribute('data-students-view') ?? 'directory'} studentId={studentId} />;
    }
    case 'teachers':
      return query.get('view') === 'day' ? <TeacherDayApp {...props} /> : <TeacherApp {...props} />;
    case 'crm':
      return query.get('view') === 'front-office' ? <FrontOfficeApp {...props} /> : <CrmApp {...props} />;
    case 'management':
      return <ManagementApp {...props} />;
    case 'identity':
      return <IdentityApp {...props} />;
    case 'library':
      return <LibraryApp {...props} />;
    case 'privacy':
      return <PrivacyApp {...props} />;
    case 'audit':
      return <AuditApp {...props} />;
    case 'workspace':
    default:
      return <WorkspaceApp {...props} />;
  }
}

const root = document.getElementById('react-console');
if (root) {
  const csrfToken = root.getAttribute('data-csrf-token') ?? '';
  const api = createApiClient({ apiBase: root.getAttribute('data-api-base') ?? '/api/v1', csrfToken });
  const props = { ...api, csrfToken } satisfies ConsoleProps;
  const content = resolveContent(root.getAttribute('data-view'), new URLSearchParams(window.location.search), props);
  createRoot(root).render(<AppErrorBoundary>{content}</AppErrorBoundary>);
}
