import { ReactNode, SVGProps } from 'react';

type AppShellProps = {
  current?: string;
  csrfToken: string;
  children?: ReactNode;
};

type IconProps = SVGProps<SVGSVGElement> & { name: 'home' | 'users' | 'academic' | 'teacher' | 'crm' | 'finance' | 'reporting' | 'management' | 'menu' | 'logout' | 'chevron' };

export function Icon({ name, width = 17, height = 17, ...props }: IconProps) {
  const common = { width, height, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true };
  const paths: Record<IconProps['name'], ReactNode> = {
    home: <><path d="m3 10 9-7 9 7"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/></>,
    users: <><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></>,
    academic: <><path d="m3 8 9-5 9 5-9 5-9-5Z"/><path d="M7 10.2V16l5 3 5-3v-5.8"/><path d="M21 9v6"/></>,
    teacher: <><circle cx="12" cy="8" r="4"/><path d="M4 21c.6-4.3 3.2-6.5 8-6.5s7.4 2.2 8 6.5"/><path d="M17 7h4"/></>,
    crm: <><path d="M5 4h14v12H8l-3 3V4Z"/><path d="M8 8h8M8 11h6"/></>,
    finance: <><path d="M3 7h18M5 4h14v16H5z"/><path d="M8 12h8M8 15h5"/></>,
    reporting: <><path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 5-7"/></>,
    management: <><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 15v-3M12 15V9M16 15v-6"/></>,
    menu: <><path d="M4 7h16M4 12h16M4 17h16"/></>,
    logout: <><path d="M9 5H5v14h4"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></>,
    chevron: <path d="m9 18 6-6-6-6"/>,
  };
  return <svg {...common} {...props}>{paths[name]}</svg>;
}

const navigation = [
  { href: '/workspace', label: 'Workspace', key: 'workspace', icon: 'home' as const },
  { href: '/students', label: 'Students', key: 'students', icon: 'users' as const },
  { href: '/academic', label: 'Academic', key: 'academic', icon: 'academic' as const },
  { href: '/teachers', label: 'People', key: 'teachers', icon: 'teacher' as const },
  { href: '/crm', label: 'CRM', key: 'crm', icon: 'crm' as const },
  { href: '/finance', label: 'Finance', key: 'finance', icon: 'finance' as const },
  { href: '/reporting', label: 'Reports', key: 'reporting', icon: 'reporting' as const },
  { href: '/management', label: 'Management', key: 'management', icon: 'management' as const },
];

export function AppShell({ current = 'workspace', csrfToken }: AppShellProps) {
  return (
    <>
      <a className="skip-link" href="#workspace-main">Skip to main content</a>
      <header className="app-header">
      <div className="app-header-inner">
        <a className="app-brand" href="/workspace" aria-label="The TOEFL House workspace">
          <span className="brand-mark" aria-hidden="true">T</span>
          <span className="brand-copy"><strong>TOEFL House</strong><small>Operations platform</small></span>
        </a>
        <nav className="app-nav" aria-label="Primary navigation">
          {navigation.map((item) => (
            <a key={item.key} className={current === item.key ? 'active' : ''} href={item.href} aria-current={current === item.key ? 'page' : undefined}>
              <Icon name={item.icon} />
              <span>{item.label}</span>
            </a>
          ))}
        </nav>
        <div className="app-header-actions">
          <a className="header-utility" href="/workspace" title="Return to your workspace"><span className="utility-dot" aria-hidden="true" />My workspace</a>
          <form method="post" action="/logout">
            <input type="hidden" name="_token" value={csrfToken} />
            <button className="sign-out" type="submit"><Icon name="logout" /><span>Sign out</span></button>
          </form>
        </div>
      </div>
      </header>
    </>
  );
}

export function PageStatus({ children }: { children: ReactNode }) {
  return <main id="workspace-main" className="workspace status-page" aria-live="polite" aria-busy="true"><section className="panel status-panel"><div className="loading-orb" aria-hidden="true" /><div><p className="eyebrow">TOEFL House</p><p className="status-message">{children}</p></div></section></main>;
}
