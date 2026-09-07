import type { ReactNode } from 'react';
import { Tone, toneClass } from './ui-helpers';

export function Badge({ tone, children }: { tone: Tone; children: ReactNode }) {
  return <span className={`badge ${toneClass[tone]}`}>{children}</span>;
}

export function Card({ title, sub, children, actions, style }: {
  title?: string;
  sub?: string;
  children?: ReactNode;
  actions?: ReactNode;
  style?: React.CSSProperties;
}) {
  return (
    <section className="card" style={style}>
      {title && (
        <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.9rem' }}>
          <div>
            <h3 className="card-title">{title}</h3>
            {sub && <p className="card-sub">{sub}</p>}
          </div>
          {actions}
        </header>
      )}
      {children}
    </section>
  );
}

export function StatCard({ label, value, foot, icon, tone }: {
  label: string;
  value: string;
  foot?: string;
  icon: string;
  tone: 'a' | 'b' | 'c' | 'd';
}) {
  return (
    <div className="stat-card">
      <div className="stat-top">
        <span className={`stat-icon tone-${tone}`}>{icon}</span>
        <span className="stat-label">{label}</span>
      </div>
      <div className="stat-value">{value}</div>
      {foot && <div className="stat-foot">{foot}</div>}
    </div>
  );
}

export function Alert({ tone, children }: { tone: 'error' | 'success' | 'info'; children: ReactNode }) {
  return <div className={`alert alert-${tone}`}>{children}</div>;
}

export function Spinner() {
  return <div className="spinner" role="status" aria-label="Memuat..." />;
}

export function EmptyRow(colSpan: number, text = 'Belum ada data.') {
  return (
    <tr className="empty-row">
      <td colSpan={colSpan}>{text}</td>
    </tr>
  );
}