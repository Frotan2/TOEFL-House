import { ReactNode, SVGProps, useEffect, useMemo, useRef, useState } from 'react';
import { navigation, navigationGroups } from './core/navigation';

type AppShellProps = {
  current?: string;
  csrfToken?: string;
  children?: ReactNode;
};

type IconProps = SVGProps<SVGSVGElement> & { name: 'home' | 'users' | 'academic' | 'teacher' | 'crm' | 'finance' | 'reporting' | 'management' | 'menu' | 'logout' | 'chevron' | 'settings' | 'tasks' | 'search' | 'close' | 'pin' };

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
    settings: <><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .04 2l.04.04-1.7 1.7-.04-.04a1.8 1.8 0 0 0-2-.04l-.3.17a1.8 1.8 0 0 0-1 1.63V21h-2.4v-.58a1.8 1.8 0 0 0-1-1.63l-.3-.17a1.8 1.8 0 0 0-2 .04l-.04.04-1.7-1.7.04-.04a1.8 1.8 0 0 0 .04-2l-.17-.3a1.8 1.8 0 0 0-1.63-1H3.7v-2.4h.58a1.8 1.8 0 0 0 1.63-1l.17-.3a1.8 1.8 0 0 0-.04-2L6 7.96l1.7-1.7.04.04a1.8 1.8 0 0 0 2 .04l.3-.17a1.8 1.8 0 0 0 1-1.63V4h2.4v.58a1.8 1.8 0 0 0 1 1.63l.3.17a1.8 1.8 0 0 0 2-.04l.04-.04 1.7 1.7-.04.04a1.8 1.8 0 0 0-.04 2l.17.3a1.8 1.8 0 0 0 1.63 1H21v2.4h-.58a1.8 1.8 0 0 0-1.63 1l-.17.3Z"/></>,
    tasks: <><path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1.5 1.5L6.5 5M3 12l1.5 1.5L6.5 11M3 18l1.5 1.5L6.5 17"/></>,
    search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
    close: <><path d="m6 6 12 12M18 6 6 18"/></>,
    pin: <><path d="m9 4 6 6-3 3 4 4-2 2-4-4-3 3-3-3 6-6-3-3 2-2Z"/></>,
    menu: <><path d="M4 7h16M4 12h16M4 17h16"/></>,
    logout: <><path d="M9 5H5v14h4"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></>,
    chevron: <path d="m9 18 6-6-6-6"/>,
  };
  return <svg {...common} {...props}>{paths[name]}</svg>;
}

function NavigationLinks({ current, compact = false, onNavigate }: { current: string; compact?: boolean; onNavigate?: () => void }) {
  return <>
    {navigationGroups.map((group) => <div className="nav-group" key={group}>
      {!compact && <div className="nav-group-label">{group}</div>}
      {navigation.filter((item) => item.group === group).map((item) => {
        const active = current === item.key;
        return <a key={item.key} className={active ? 'active' : ''} href={item.href} aria-current={active ? 'page' : undefined} onClick={onNavigate} title={compact ? item.label : undefined}>
          <Icon name={item.icon} />
          <span>{item.label}</span>
        </a>;
      })}
    </div>)}
  </>;
}

function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const resultRefs = useRef<Array<HTMLAnchorElement | null>>([]);
  const returnFocusRef = useRef<HTMLElement | null>(null);
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  useEffect(() => {
    if (!open) return;
    returnFocusRef.current = document.activeElement as HTMLElement | null;
    setQuery('');
    setActiveIndex(0);
    resultRefs.current = [];
    window.setTimeout(() => inputRef.current?.focus(), 0);
    return () => returnFocusRef.current?.focus();
  }, [open]);
  const results = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return navigation;
    return navigation.filter((item) => `${item.label} ${item.group}`.toLowerCase().includes(term));
  }, [query]);
  useEffect(() => {
    setActiveIndex((index) => Math.min(Math.max(index, 0), Math.max(results.length - 1, 0)));
  }, [results.length]);
  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') { event.preventDefault(); onClose(); return; }
      if (!results.length) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActiveIndex((index) => (index + 1) % results.length);
        return;
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActiveIndex((index) => (index - 1 + results.length) % results.length);
        return;
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        resultRefs.current[activeIndex]?.click();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose, results.length, activeIndex]);
  useEffect(() => {
    resultRefs.current[activeIndex]?.scrollIntoView({ block: 'nearest' });
  }, [activeIndex]);
  if (!open) return null;
  return <div className="command-palette-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <div className="command-palette" role="dialog" aria-modal="true" aria-label="Navigate TOEFL House">
      <div className="command-palette-header"><Icon name="search" /><input ref={inputRef} value={query} onChange={(event) => { setQuery(event.target.value); setActiveIndex(0); }} placeholder="Jump to a workspace…" aria-label="Search workspaces" aria-controls="command-palette-results" /><span className="command-palette-key">Esc</span></div>
      <div id="command-palette-results" className="command-palette-list" role="listbox" aria-label="Workspace results">
        {results.length === 0 ? <p className="command-palette-empty">No matching workspace.</p> : results.map((item, index) => <a key={item.key} ref={(node) => { resultRefs.current[index] = node; }} className={`command-palette-item ${index === activeIndex ? 'active' : ''}`} href={item.href} role="option" aria-selected={index === activeIndex} onMouseEnter={() => setActiveIndex(index)} onClick={onClose}><Icon name={item.icon} /><span><strong>{item.label}</strong><small>{item.group}</small></span><Icon name="chevron" /></a>)}
      </div>
      <div className="command-palette-footer"><span>↑ ↓ move · Enter open · Esc close</span><span>Ctrl/⌘ + K</span></div>
    </div>
  </div>;
}

export function AppShell({ current = 'workspace', csrfToken }: AppShellProps) {
  const resolvedCsrfToken = csrfToken ?? document.getElementById('react-console')?.getAttribute('data-csrf-token') ?? '';
  const [collapsed, setCollapsed] = useState(() => window.localStorage.getItem('toefl-house.sidebar.collapsed') === '1');
  const [mobileOpen, setMobileOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const mobileTriggerRef = useRef<HTMLButtonElement | null>(null);

  useEffect(() => {
    window.localStorage.setItem('toefl-house.sidebar.collapsed', collapsed ? '1' : '0');
  }, [collapsed]);

  useEffect(() => {
    document.body.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
    return () => { delete document.body.dataset.sidebar; };
  }, [collapsed]);

  useEffect(() => {
    if (!mobileOpen) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setMobileOpen(false);
        window.setTimeout(() => mobileTriggerRef.current?.focus(), 0);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [mobileOpen]);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setPaletteOpen((value) => !value);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  const focusMainContent = (event: React.MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault();
    const main = document.querySelector('main') as HTMLElement | null;
    if (!main) return;
    if (!main.hasAttribute('tabindex')) main.tabIndex = -1;
    main.scrollIntoView({ block: 'start' });
    main.focus({ preventScroll: true });
  };

  return <>
    <a className="skip-link" href="#workspace-main" onClick={focusMainContent}>Skip to main content</a>
    <header className="app-header">
      <div className="app-header-inner">
        <button ref={mobileTriggerRef} className="mobile-shell-trigger" type="button" onClick={() => setMobileOpen(true)} aria-label="Open navigation" aria-controls="primary-navigation" aria-expanded={mobileOpen}><Icon name="menu" /></button>
        <a className="app-brand" href="/workspace" aria-label="The TOEFL House home">
          <span className="brand-mark" aria-hidden="true">T</span>
          <span className="brand-copy"><strong>TOEFL House</strong><small>Academic operations platform</small></span>
        </a>
        <div className="topbar-context"><span className="context-dot" aria-hidden="true" /> <span>Authorized workspace</span></div>
        <div className="app-header-actions">
          <button className="header-utility" type="button" onClick={() => setPaletteOpen(true)} title="Open command palette" aria-haspopup="dialog" aria-expanded={paletteOpen}><Icon name="search" />Navigate <span className="command-palette-key">⌘/Ctrl K</span></button>
          <a className="header-utility" href="/workspace#work-queue"><Icon name="tasks" />My work</a>
          <a className="header-utility" href="/management?view=administration"><Icon name="settings" />Administration</a>
          <button className="sidebar-toggle" type="button" onClick={() => setCollapsed((value) => !value)} aria-label={collapsed ? 'Expand navigation' : 'Collapse navigation'} title={collapsed ? 'Expand navigation' : 'Collapse navigation'}><Icon name="menu" /></button>
          <form method="post" action="/logout">
            <input type="hidden" name="_token" value={resolvedCsrfToken} />
            <button className="sign-out" type="submit" aria-label="Sign out"><Icon name="logout" /><span>Sign out</span></button>
          </form>
        </div>
      </div>
    </header>

    <div className="app-frame">
      <aside id="primary-navigation" className={`app-sidebar ${mobileOpen ? 'mobile-open' : ''}`} aria-label="Primary navigation">
        <div className="sidebar-inner">
          <div className="sidebar-heading"><span>Navigate</span><button type="button" onClick={() => { setMobileOpen(false); window.setTimeout(() => mobileTriggerRef.current?.focus(), 0); }} aria-label="Close navigation"><Icon name="close" /></button></div>
          <nav className="sidebar-nav"><NavigationLinks current={current} compact={collapsed} onNavigate={() => setMobileOpen(false)} /></nav>
          <div className="sidebar-footer">
            <a href="/workspace#work-queue" className="sidebar-utility"><Icon name="tasks" /><span>My work queue</span></a>
            <a href="/management?view=administration" className="sidebar-utility"><Icon name="settings" /><span>Administration</span></a>
          </div>
        </div>
      </aside>
      {mobileOpen && <button className="sidebar-backdrop" type="button" onClick={() => { setMobileOpen(false); window.setTimeout(() => mobileTriggerRef.current?.focus(), 0); }} aria-label="Close navigation" />}
    </div>
    <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
  </>;
}

export function PageStatus({ children }: { children: ReactNode }) {
  return <main id="workspace-main" className="workspace status-page" aria-live="polite" aria-busy="true"><section className="panel status-panel"><div className="loading-orb" aria-hidden="true" /><div><p className="eyebrow">TOEFL House</p><p className="status-message">{children}</p></div></section></main>;
}
