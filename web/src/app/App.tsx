import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../features/auth/useAuth';
import { logoutApi } from '../api/client';
import { initials } from '../lib/ui-helpers';

function Icon(name: string) {
  const paths: Record<string, string> = {
    dashboard:
      '<rect x="3" y="3" width="4" height="4" rx="1"/><rect x="9" y="3" width="4" height="4" rx="1"/><rect x="3" y="9" width="4" height="4" rx="1"/><rect x="9" y="9" width="4" height="4" rx="1"/>',
    invoices:
      '<path d="M6 2h1.2v6h1.2v6h1.2" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>',
    payments: '<circle cx="8" cy="7" r="2.4"/><path d="M4 12h8" width="8" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>',
    students:
      '<circle cx="8" cy="5" r="2.8" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M5 9q3 3 6 1 6 1 3-3h6 -2" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>',
    reports:
      '<path d="M4 12L8 5L12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M7 4h2v5" stroke="currentColor" stroke-width="1.6" fill="none"/>',
    users:
      '<circle cx="5.5" cy="6" r="2.6"/><circle cx="10.5" cy="6" r="2.6"/><path d="M5.5 9v4M10.5 9v4" stroke="currentColor" stroke-width="1.5" fill="none"/>',
    logout:
      '<path d="M5 4l6 6-6 6-6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    school: '<rect x="4" y="3" width="8" height="4" rx="1"/><path d="M5 7v5M11 7v5" stroke="currentColor" stroke-width="1.4" fill="none"/>',
  };
  return (
    <span
      className="nav-icon"
      aria-hidden="true"
      dangerouslySetInnerHTML={{
        __html: `<svg width="20" height="20" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">${paths[name] ?? ''}</svg>`,
      }}
    />
  );
}

const NAV = [
  { to: '/', label: 'Dashboard', icon: 'dashboard', end: true },
  { to: '/invoices', label: 'Tagihan', icon: 'invoices' },
  { to: '/payments', label: 'Pembayaran', icon: 'payments' },
  { to: '/students', label: 'Murid', icon: 'students' },
  { to: '/reports', label: 'Laporan', icon: 'reports' },
  { to: '/users', label: 'Pengguna', icon: 'users' },
];

export function App() {
  const { token, user, activeSchool, roles } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      if (token) await logoutApi(token);
    } finally {
      localStorage.clear();
      navigate('/login');
    }
  };

  return (
    <div className="app-layout">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <span className="brand-logo">K</span>
          <span>Keuangan Sekolah</span>
        </div>

        <div className="sidebar-label">Navigasi</div>
        {NAV.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
          >
            {Icon(item.icon)}
            <span>{item.label}</span>
          </NavLink>
        ))}

        <div className="sidebar-label" style={{ marginTop: '1.6rem' }}>
          Sekolah Aktif
        </div>
        <div className="nav-item" onClick={() => undefined}>
          {Icon('school')}
          <span style={{ fontSize: '0.82rem' }}>{activeSchool?.name ?? '—'}</span>
        </div>

        <div className="nav-item" onClick={handleLogout} style={{ marginTop: '1.25rem', color: 'rgba(255,255,255,0.6)' }}>
          {Icon('logout')}
          <span>Logout</span>
        </div>
      </aside>

      <div className="main-wrap">
        <header className="topbar">
          <div className="topbar-title">School Finance</div>
          <div className="topbar-right">
            <span className="school-chip">{activeSchool?.name ?? 'Sekolah'}</span>
            <div className="user-chip">
              <span className="user-avatar">{user ? initials(user.name) : '?'}</span>
              <div className="user-meta">
                <strong>{user?.name ?? '—'}</strong>
                <small>{roles.join(', ') || 'geen peran'}</small>
              </div>
            </div>
          </div>
        </header>

        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}