import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import type { Invoice, Payment, Student, ArrearsReport } from '../../api/client';
import { invoicesApi, paymentsApi, studentsApi, arrearsApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, StatCard, Badge, EmptyRow, Alert } from '../../lib/ui';
import { invoiceTone, formatMoney, formatDate } from '../../lib/ui-helpers';

type WeekPoint = { label: string; total: number };

function buildWeekSeries(payments: Payment[]): WeekPoint[] {
  const days: { key: string; label: string; total: number }[] = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - i);
    days.push({
      key: d.toISOString().slice(0, 10),
      label: new Intl.DateTimeFormat('id-ID', { weekday: 'short' }).format(d),
      total: 0,
    });
  }
  const start = new Date(days[0].key + 'T00:00:00Z');
  for (const p of payments) {
    if (p.status !== 'SETTLED' || !p.created_at) continue;
    const d = new Date(p.created_at);
    if (d < start || d > new Date()) continue;
    const key = d.toISOString().slice(0, 10);
    const bucket = days.find((b) => b.key === key);
    if (bucket) bucket.total += p.total_cents;
  }
  return days;
}

export function DashboardPage() {
  const { token } = useAuth();
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [arrears, setArrears] = useState<ArrearsReport['data']>([]);
  const [error, setError] = useState('');

  const load = () => {
    if (!token) return;
    Promise.all([invoicesApi(token), paymentsApi(token), studentsApi(token), arrearsApi(token)])
      .then(([inv, pay, stu, arr]) => {
        setInvoices(inv.data);
        setPayments(pay.data);
        setStudents(stu.data);
        setArrears(Array.isArray(arr.data) ? arr.data : []);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat data dasbor.'));
  };

  useEffect(load, [token]);

  const totalBilled = invoices.filter((i) => i.status !== 'VOID').reduce((s, i) => s + (i.amount_cents ?? 0), 0);
  const totalPaid = payments.filter((p) => p.status === 'SETTLED').reduce((s, p) => s + (p.total_cents ?? 0), 0);
  const openCount = invoices.filter((i) => i.status === 'OPEN').length;
  const arrearsTotal = arrears.reduce((s, r) => s + (r.sisa_cents ?? 0), 0);
  const week = buildWeekSeries(payments);
  const maxWeek = Math.max(...week.map((w) => w.total), 1);
  const recent = invoices.slice(0, 6);

  return (
    <>
      <div className="page-head">
        <h2>Dashboard</h2>
        <p>Ringkasan keuangan sekolah — tagihan, pembayaran, dan tunggakan.</p>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      <div className="stat-grid mt">
        <StatCard
          label="Total Tagihan"
          value={formatMoney(totalBilled)}
          foot={`${invoices.length} tagihan (${openCount} terbuka)`}
          icon="📄"
          tone="a"
        />
        <StatCard
          label="Total Terbayar"
          value={formatMoney(totalPaid)}
          foot={`${payments.filter((p) => p.status === 'SETTLED').length} transaksi lunas`}
          icon="💵"
          tone="b"
        />
        <StatCard
          label="Total Tunggakan"
          value={formatMoney(arrearsTotal)}
          foot={`${arrears.length} baris tunggakan`}
          icon="⚠️"
          tone="c"
        />
        <StatCard
          label="Total Murid"
          value={String(students.length)}
          foot="murid terdaftar"
          icon="👥"
          tone="d"
        />
      </div>

      <div className="grid-2">
        <Card title="Penerimaan 7 Hari Terakhir" sub="Total pembayaran lunas per hari">
          {payments.length === 0 ? (
            <p className="muted">Belum ada data pembayaran untuk ditampilkan.</p>
          ) : (
            <div className="bar-chart">
              {week.map((w) => (
                <div className="bar-col" key={w.label}>
                  <span>
                    <span className="bar" style={{ height: `${Math.max((w.total / maxWeek) * 100, 3)}%` }} />
                  </span>
                  <span className="bar-label">{w.label}</span>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card title="Akses Cepat" sub="Pintas ke fungsi utama sistem">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.8rem' }}>
            <Link className="btn btn-primary" to="/invoices" style={{ width: '100%' }}>
              Buat Tagihan Baru
            </Link>
            <Link className="btn btn-ghost" to="/payments" style={{ width: '100%' }}>
              Catat Pembayaran Manual
            </Link>
            <Link className="btn btn-ghost" to="/reports" style={{ width: '100%' }}>
              Laporan &amp; Ekspor (PDF/Excel/CSV)
            </Link>
          </div>
        </Card>
      </div>

      <section className="card" style={{ marginTop: '1.5rem', padding: 0, overflow: 'hidden' }}>
        <header style={{ padding: '1rem 1.4rem' }}>
          <h3 className="card-title">Tagihan Terbaru</h3>
          <p className="card-sub">6 tagihan terakhir yang dibuat</p>
        </header>
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Murid</th>
                <th>Tipe Tagihan</th>
                <th>Periode</th>
                <th className="right">Jumlah</th>
                <th>Status</th>
                <th>Batas Pembayaran</th>
              </tr>
            </thead>
            <tbody>
              {recent.length === 0 && EmptyRow(7, 'Belum ada tagihan. Buat di halaman Tagihan.')}
              {recent.map((inv) => (
                <tr key={inv.id}>
                  <td>{inv.id}</td>
                  <td>{inv.student?.name ?? '—'}</td>
                  <td>{inv.bill_type?.name ?? '—'}</td>
                  <td>{inv.periode_bulan ? `${inv.periode_bulan}/${inv.periode_tahun}` : String(inv.periode_tahun)}</td>
                  <td className="right strong">{formatMoney(inv.amount_cents)}</td>
                  <td>
                    <Badge tone={invoiceTone(inv.status ?? '')}>
                      {inv.status === 'OPEN' ? 'Terbuka' : inv.status === 'PAID' ? 'Lunas' : inv.status === 'VOID' ? 'Batal' : inv.status}
                    </Badge>
                  </td>
                  <td>{formatDate(inv.due_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  );
}