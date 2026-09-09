import { ReactNode, useRef } from 'react';

export function PageHeader({ eyebrow, title, description, actions }: { eyebrow?: string; title: string; description?: string; actions?: ReactNode }) {
  return <header className="workspace-header product-page-header"><div>{eyebrow && <p className="eyebrow">{eyebrow}</p>}<h1>{title}</h1>{description && <p className="lede">{description}</p>}</div>{actions && <div className="page-header-actions">{actions}</div>}</header>;
}

export function SectionHeader({ eyebrow, title, description, trailing }: { eyebrow?: string; title: string; description?: string; trailing?: ReactNode }) {
  return <div className="section-heading product-section-heading"><div>{eyebrow && <p className="eyebrow">{eyebrow}</p>}<h2>{title}</h2>{description && <p className="muted">{description}</p>}</div>{trailing}</div>;
}

export function StatCard({ label, value, detail, href, tone = 'neutral' }: { label: string; value: ReactNode; detail?: string; href?: string; tone?: 'neutral' | 'success' | 'warning' | 'danger' }) {
  const content = <><span className="stat-card-label">{label}</span><strong className={`stat-card-value ${tone}`}>{value}</strong>{detail && <span className="stat-card-detail">{detail}</span>}</>;
  return href ? <a className="panel stat-card stat-card-link" href={href}>{content}</a> : <div className="panel stat-card">{content}</div>;
}

export function StatusBadge({ state, label }: { state: string; label?: string }) {
  return <span className={`status-chip ${state}`}><span>{label ?? state.replaceAll('_', ' ')}</span></span>;
}

export function EmptyState({ title, description, action }: { title: string; description: string; action?: ReactNode }) {
  return <div className="empty-state"><div className="empty-state-mark" aria-hidden="true">—</div><strong>{title}</strong><p>{description}</p>{action}</div>;
}

export function ActionCard({ href, title, description, icon, footer }: { href: string; title: string; description: string; icon: ReactNode; footer?: string }) {
  return <a className="action-card" href={href}><span className="action-card-icon" aria-hidden="true">{icon}</span><span className="action-card-body"><strong>{title}</strong><small>{description}</small>{footer && <em>{footer}</em>}</span><span className="action-card-arrow" aria-hidden="true">→</span></a>;
}

type TabItem<T extends string> = { value: T; label: string; count?: number; panelId?: string };

export function SegmentedTabs<T extends string>({ items, value, onChange, ariaLabel }: { items: Array<TabItem<T>>; value: T; onChange: (value: T) => void; ariaLabel: string }) {
  const buttonRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const move = (index: number, delta: number) => {
    if (!items.length) return;
    const next = (index + delta + items.length) % items.length;
    const target = items[next];
    if (!target) return;
    onChange(target.value);
    window.setTimeout(() => buttonRefs.current[next]?.focus(), 0);
  };

  return <div className="segmented-tabs" role="tablist" aria-label={ariaLabel}>
    {items.map((item, index) => {
      const selected = value === item.value;
      return <button key={item.value} ref={(node) => { buttonRefs.current[index] = node; }} id={`tab-${item.value}`} className={selected ? 'active' : ''} role="tab" aria-selected={selected} {...(item.panelId ? { 'aria-controls': item.panelId } : {})} tabIndex={selected ? 0 : -1} type="button" onClick={() => onChange(item.value)} onKeyDown={(event) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') { event.preventDefault(); move(index, 1); }
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') { event.preventDefault(); move(index, -1); }
        if (event.key === 'Home') { event.preventDefault(); move(index, -index); }
        if (event.key === 'End') { event.preventDefault(); move(index, items.length - 1 - index); }
      }}>{item.label}{item.count !== undefined && <span>{item.count}</span>}</button>;
    })}
  </div>;
}
