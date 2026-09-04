import { formatRupiahFromCents } from '../lib/format-money';
import { Link } from 'react-router-dom';

export function App() {
  return (
    <main className="app-shell">
      <section className="hero-card">
        <p className="eyebrow">School Finance System</p>
        <h1>Fondasi React SPA siap.</h1>
        <nav style={{ display: 'flex', gap: '1rem', marginBottom: '1rem' }}>
          <Link to="/users">Daftar Pengguna</Link>
          <Link to="/master-data">Master Data</Link>
        </nav>
        <p>
          Frontend ini akan memakai OpenAPI generated client dan hanya berkomunikasi dengan Laravel API.
        </p>
        <dl className="status-grid" aria-label="Status fondasi">
          <div>
            <dt>Stack</dt>
            <dd>React + Vite + TypeScript</dd>
          </div>
          <div>
            <dt>Money util</dt>
            <dd>{formatRupiahFromCents(15000000)}</dd>
          </div>
        </dl>
      </section>
    </main>
  );
}
