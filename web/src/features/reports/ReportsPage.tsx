import { useEffect, useState } from 'react';
import type { ArrearsRow, AcademicYear, BillType, SchoolClass } from '../../api/client';
import { arrearsApi, academicYearsApi, billTypesApi, classesApi, downloadExport, exportUrls } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, Alert, EmptyRow } from '../../lib/ui';
import { formatMoney } from '../../lib/ui-helpers';

export function ReportsPage() {
  const { token } = useAuth();
  const [rows, setRows] = useState<ArrearsRow[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [billTypes, setBillTypes] = useState<BillType[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);

  const [classId, setClassId] = useState('');
  const [yearId, setYearId] = useState('');
  const [billTypeId, setBillTypeId] = useState('');

  const [error, setError] = useState('');
  const [busyExport, setBusyExport] = useState('');

  const load = () => {
    if (!token) return;
    Promise.all([arrearsApi(token, {
      class_id: classId ? Number(classId) : undefined,
      academic_year_id: yearId ? Number(yearId) : undefined,
      bill_type_id: billTypeId ? Number(billTypeId) : undefined,
    }), academicYearsApi(token), billTypesApi(token), classesApi(token)])
      .then(([arr, ay, bt, cl]) => {
        setRows(Array.isArray(arr.data) ? arr.data : []);
        setYears(ay.data);
        setBillTypes(bt.data);
        setClasses(cl.data);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat laporan.'));
  };

  useEffect(load, [token, classId, yearId, billTypeId]);

  const totalTagihan = rows.reduce((s, r) => s + (r.tagihan_cents ?? 0), 0);
  const totalDibayar = rows.reduce((s, r) => s + (r.dibayar_cents ?? 0), 0);
  const totalSisa = rows.reduce((s, r) => s + (r.sisa_cents ?? 0), 0);

  const handleExport = async (kind: 'pdf' | 'excel' | 'csv', url: string) => {
    try {
      setBusyExport(kind);
      const suffix = kind === 'pdf' ? '.pdf' : kind === 'excel' ? '.xlsx' : '.csv';
      await downloadExport(token ?? '', url, `tunggakan-${new Date().toISOString().slice(0, 10)}${suffix}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Export gagal.');
    } finally {
      setBusyExport('');
    }
  };

  return (
    <>
      <div className="page-head">
        <h2>Laporan</h2>
        <p>Rapport tunggakan per murid, filterbaar en eksportable naar PDF/Excel/CSV.</p>
      </div>

      <Card title="Rapport Tunggakan" sub="Filter per klasse, jaar ajaran, of tipe tagihan" style={{ marginTop: '1.25rem' }}>
        {error && <Alert tone="error">{error}</Alert>}
        <div className="form-grid" style={{ marginBottom: '0.75rem' }}>
          <div className="field">
            <label htmlFor="r-class">Klasse</label>
            <select id="r-class" className="select" value={classId} onChange={(e) => setClassId(e.target.value)}>
              <option value="">Alle klasse</option>
              {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="r-year">Tahun Ajaran</label>
            <select id="r-year" className="select" value={yearId} onChange={(e) => setYearId(e.target.value)}>
              <option value="">Alle jaar</option>
              {years.map((y) => <option key={y.id} value={y.id}>{y.name} {y.semester}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="r-bt">Tipe Tagihan</label>
            <select id="r-bt" className="select" value={billTypeId} onChange={(e) => setBillTypeId(e.target.value)}>
              <option value="">Alle tipe</option>
              {billTypes.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </select>
          </div>
        </div>

        <div className="row" style={{ marginBottom: '0.9rem' }}>
          <button className="btn btn-primary" onClick={() => handleExport('pdf', exportUrls.arrearsPdf)} disabled={busyExport !== ''}>
            {busyExport === 'pdf' ? 'Generating...' : 'Export PDF'}
          </button>
          <button className="btn btn-ghost" onClick={() => handleExport('excel', exportUrls.arrearsExcel)} disabled={busyExport !== ''}>
            {busyExport === 'excel' ? 'Generating...' : 'Export Excel'}
          </button>
          <button className="btn btn-ghost" onClick={() => handleExport('csv', exportUrls.arrearsCsv)} disabled={busyExport !== ''}>
            {busyExport === 'csv' ? 'Generating...' : 'Export CSV'}
          </button>
        </div>

        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>NIS</th>
                <th>Naam</th>
                <th>Klasse</th>
                <th>Tipe</th>
                <th>Periode</th>
                <th className="right">Tagihan</th>
                <th className="right">Dibayar</th>
                <th className="right">Sisa</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && EmptyRow(8, 'Belum ada tunggakan voor deze filter.')}
              {rows.map((r) => (
                <tr key={`${r.nis}-${r.periode}-${r.bill_type}`}>
                  <td>{r.nis}</td>
                  <td className="strong">{r.nama}</td>
                  <td>{r.kelas}</td>
                  <td>{r.bill_type}</td>
                  <td>{r.periode}</td>
                  <td className="right">{formatMoney(r.tagihan_cents)}</td>
                  <td className="right">{formatMoney(r.dibayar_cents)}</td>
                  <td className="right strong" style={{ color: r.sisa_cents > 0 ? '#c2333c' : undefined }}>
                    {formatMoney(r.sisa_cents)}
                  </td>
                </tr>
              ))}
            </tbody>
            {rows.length > 0 && (
              <tfoot>
                <tr>
                  <td colSpan={5} className="strong" style={{ textAlign: 'right' }}>Total</td>
                  <td className="right strong">{formatMoney(totalTagihan)}</td>
                  <td className="right strong">{formatMoney(totalDibayar)}</td>
                  <td className="right strong" style={{ color: '#c2333c' }}>{formatMoney(totalSisa)}</td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </Card>
    </>
  );
}