export type NavigationIcon = 'home' | 'users' | 'academic' | 'teacher' | 'crm' | 'finance' | 'reporting' | 'management' | 'settings';
export type NavigationGroup = 'Work' | 'People' | 'Operations' | 'Governance' | 'Control';

export type NavigationItem = {
  href: string;
  label: string;
  key: string;
  icon: NavigationIcon;
  group: NavigationGroup;
};

export const navigation: readonly NavigationItem[] = [
  { href: '/workspace', label: 'Home', key: 'workspace', icon: 'home', group: 'Work' },
  { href: '/crm?view=front-office', label: 'Reception Desk', key: 'front-office', icon: 'crm', group: 'Work' },
  { href: '/students', label: 'Students & Admissions', key: 'students', icon: 'users', group: 'Work' },
  { href: '/academic', label: 'Academic Operations', key: 'academic', icon: 'academic', group: 'Work' },
  { href: '/placement', label: 'Placement', key: 'placement', icon: 'academic', group: 'Work' },
  { href: '/teachers', label: 'People & Faculty', key: 'teachers', icon: 'teacher', group: 'Work' },
  { href: '/hr', label: 'HR', key: 'hr', icon: 'users', group: 'People' },
  { href: '/crm', label: 'CRM & Follow-up', key: 'crm', icon: 'crm', group: 'People' },
  { href: '/finance', label: 'Finance & Funding', key: 'finance', icon: 'finance', group: 'Operations' },
  { href: '/payroll', label: 'Payroll', key: 'payroll', icon: 'finance', group: 'Operations' },
  { href: '/library', label: 'Library & Resources', key: 'library', icon: 'settings', group: 'Operations' },
  { href: '/communication', label: 'Communication', key: 'communication', icon: 'crm', group: 'Operations' },
  { href: '/reporting', label: 'Reports & Dashboards', key: 'reporting', icon: 'reporting', group: 'Operations' },
  { href: '/documents', label: 'Documents & Evidence', key: 'documents', icon: 'settings', group: 'Operations' },
  { href: '/organization', label: 'Organization', key: 'organization', icon: 'management', group: 'Governance' },
  { href: '/identity', label: 'Identity', key: 'identity', icon: 'users', group: 'Governance' },
  { href: '/access', label: 'Access Governance', key: 'access', icon: 'settings', group: 'Governance' },
  { href: '/privacy', label: 'Privacy & Consent', key: 'privacy', icon: 'settings', group: 'Governance' },
  { href: '/audit', label: 'Audit & History', key: 'audit', icon: 'reporting', group: 'Governance' },
  { href: '/management', label: 'Command Center', key: 'management', icon: 'management', group: 'Control' },
] as const;

export const navigationGroups: readonly NavigationGroup[] = ['Work', 'People', 'Operations', 'Governance', 'Control'];

export function getNavigationItem(key: string): NavigationItem | undefined {
  return navigation.find((item) => item.key === key);
}
