import { useEffect, useState } from 'react';
import type { Invoice, BillType, AcademicYear } from '../../api/client';
import { invoicesApi, billTypesApi, academicYearsApi, invoiceGenerateApi, invoiceVoidApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, Badge, EmptyRow, Alert } from '../../lib/ui';
import { invoiceTone, formatMoney, formatDate } from '../../lib/ui-helpers';

export function InvoicesPage() {
  const { token } = useAuth();
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [billTypes, setBillTypes] = useState<BillType[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [status, setStatus] = useState('');
  const [periode, setPeriode] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  // form generate
  const [genBillType, setGenBillType] = useState('');
  const [genYear, setGenYear] = useState('');
  const [genBulan, setGenBulan] = useState('');
  const [genTahun, setGenTahun] = useState(String(new Date().getFullYear()));
  const [genDue, setGenDue] = useState('');
  const [busy, setBusy] = useState(false);
  const [genError, setGenError] = useState('');

  const load = () => {
    if (!token) return;
    Promise.all([invoicesApi(token, { status, periode_tahun: periode ? Number(periode) : undefined }), billTypesApi(token), academicYearsApi(token)])
      .then(([inv, bt, ay]) => {
        setInvoices(inv.data);
        setBillTypes(bt.data);
        setYears(ay.data);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat tagihan.'));
  };

  useEffect(load, [token, status, periode]);

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    setGenError('');
    setNotice('');
    setBusy(true);
    try {
      const res = await invoiceGenerateApi(token ?? '', {
        bill_type_id: Number(genBillType),
        academic_year_id: Number(genYear),
        periode_bulan: genBulan ? Number(genBulan) : undefined,
        periode_tahun: Number(genTahun),
        due_at: genDue || undefined,
      });
      setNotice(`✓ ${res.message ?? `Generate ${res.count ?? 0} invoice.`}`);
      setGenBillType('');
      setGenBulan('');
      setGenDue('');
      load();
    } catch (err) {
      setGenError(err instanceof Error ? err.message : 'Generate gagal.');
    } finally {
      setBusy(false);
    }
  };

  const handleVoid = async (id: number) => {
    if (!confirm('Void invoice ini? Maju bisa direverse.')) return;
    try {
      await invoiceVoidApi(token ?? '', id);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Void gagal.');
    }
  };

  return (
    <>
      <div className="page-head">
        <h2>Tagihan</h2>
        <p>Generate tagihan peren bulan/one-time, en beheer status invoice.</p>
              </div>

              {error && <Alert tone="error">{error}</Alert>}

              <div className="grid-2" style={{ marginTop: '1.25rem' }}>
        <Card title="Generate Tagihan" sub="Peren bulan için bill-type monthly, atau one-time" actions={
          <span className="badge badge-purple">{billTypes.length} tipe</span>
        }>
          {genError && <Alert tone="error">{genError}</Alert>}
          {notice && <Alert tone="success">{notice}</Alert>}
          <form onSubmit={handleGenerate} style={{ display: 'flex', flexDirection: 'column', gap: '0.9rem', marginTop: '0.5rem' }}>
            <div className="form-grid">
              <div className="field">
                <label htmlFor="gen-bt">Tipe Tagihan</label>
                <select id="gen-bt" className="select" value={genBillType} onChange={(e) => setGenBillType(e.target.value)}>
                  <option value="">— kies —</option>
                  {billTypes.filter((b) => b.is_active).map((b) => (
                    <option key={b.id} value={b.id}>{b.name} ({b.tipe_bayar})</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="gen-ay">Tahun Ajaran</label>
                <select id="gen-ay" className="select" value={genYear} onChange={(e) => setGenYear(e.target.value)}>
                  <option value="">— kies —</option>
                  {years.map((y) => (
                    <option key={y.id} value={y.id}>{y.name} {y.semester}{y.is_active ? ' (aktif)' : ''}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="gen-bulan">Periode Bulan</label>
                <select id="gen-bulan" className="select" value={genBulan} onChange={(e) => setGenBulan(e.target.value)}>
                  <option value="">— (blanco) —</option>
                  {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                    <option key={m} value={m}>{String(m).padStart(2, '0')}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="gen-tahun">Periode Tahun</label>
                <input id="gen-tahun" className="input" type="number" min={2000} max={2100} value={genTahun} onChange={(e) => setGenTahun(e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="gen-due">Batas (due_at)</label>
                <input id="gen-due" className="input" type="date" value={genDue} onChange={(e) => setGenDue(e.target.value)} />
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy || !genBillType || !genYear}>
              {busy ? 'Generating...' : 'Generate Invoice'}
            </button>
          </form>
        </Card>

        <Card title="Lijst Tagihan" sub="Filter status & jaar periode">
          <div className="form-grid" style={{ marginBottom: '0.75rem' }}>
            <div className="field">
              <label htmlFor="f-status">Status</label>
              <select id="f-status" className="select" value={status} onChange={(e) => setStatus(e.target.value)}>
                <option value="">Alle status</option>
                <option value="OPEN">OPEN</option>
                <option value="PAID">PAID</option>
                <option value="VOID">VOID</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="f-periode">Periode Tahun</label>
              <input id="f-periode" className="input" type="number" placeholder="mis. 2026" value={periode} onChange={(e) => setPeriode(e.target.value)} />
            </div>
          </div>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Murid</th>
                  <th>Tipe</th>
                  <th>Periode</th>
                  <th className="right">Jumlah</th>
                  <th>Status</th>
                  <th>Batas</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {invoices.length === 0 && EmptyRow(8, 'Belum ada invoice voor deze filter.')}
                {invoices.map((inv) => (
                  <tr key={inv.id}>
                    <td>{inv.id}</td>
                    <td>{inv.student?.name ?? '—'}<small className="muted"> · {inv.student?.nis ?? ''}</small></td>
                    <td>{inv.bill_type?.name ?? '—'}</td>
                    <td>{inv.periode_bulan ? `${String(inv.periode_bulan).padStart(2, '0')}/${inv.periode_tahun}` : String(inv.periode_tahun)}</td>
                    <td className="right strong">{formatMoney(inv.amount_cents)}</td>
                    <td><Badge tone={invoiceTone(inv.status ?? '')}>{inv.status}</Badge></td>
                    <td>{formatDate(inv.due_at)}</td>
                    <td>
                      {inv.status === 'OPEN' && (
                        <button className="btn btn-danger" onClick={() => handleVoid(inv.id)}>Void</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </>
  );
}