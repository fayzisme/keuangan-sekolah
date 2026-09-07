import { useEffect, useState } from 'react';
import type { Payment, Invoice } from '../../api/client';
import { paymentsApi, invoicesApi, paymentManualApi, paymentVerifyApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, Badge, EmptyRow, Alert } from '../../lib/ui';
import { paymentTone, formatMoney, formatDate, humanize } from '../../lib/ui-helpers';

function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
  return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export function PaymentsPage() {
  const { token, user } = useAuth();
  const [payments, setPayments] = useState<Payment[]>([]);
  const [openInvoices, setOpenInvoices] = useState<Invoice[]>([]);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [selInvoice, setSelInvoice] = useState('');
  const [amount, setAmount] = useState('');
  const [cashier, setCashier] = useState('');
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');

  const load = () => {
    if (!token) return;
    Promise.all([paymentsApi(token), invoicesApi(token, { status: 'OPEN' })])
      .then(([pay, inv]) => {
        setPayments(pay.data);
        setOpenInvoices(inv.data);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat pembayaran.'));
  };

  useEffect(load, [token]);

  const handleRecord = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    setNotice('');
    const invoice = openInvoices.find((i) => String(i.id) === selInvoice);
    const cents = Math.round(Number(amount) * 100);
    if (!invoice) {
      setFormError('Kies invoice OPEN eerst.');
      return;
    }
    if (!Number.isFinite(cents) || cents <= 0) {
      setFormError('Vul bedrag (in Rupiah) correct in.');
      return;
    }
    if (cents > invoice.amount_cents) {
      setFormError(`Bedrag melebihi sisa invoice (${formatMoney(invoice.amount_cents)}).`);
      return;
    }
    setBusy(true);
    try {
      const created = await paymentManualApi(token ?? '', {
        allocations: [{ invoice_id: Number(selInvoice), amount_cents: cents }],
        cashier_name: cashier || undefined,
        idempotency_key: newIdempotencyKey(),
      });
      setNotice(`✓ Payment #${created.id} gecreeerd (${created.status}) — wacht op verificatie door ander gebruiker.`);
      setSelInvoice('');
      setAmount('');
      setCashier('');
      load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Record pembayaran gagal.');
    } finally {
      setBusy(false);
    }
  };

  const handleVerify = async (id: number) => {
    setError('');
    setNotice('');
    try {
      const done = await paymentVerifyApi(token ?? '', id);
      setNotice(`✓ Payment #${done.id} geverifieerd → ${done.status}`);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verificatie gagal.');
    }
  };

  return (
    <>
      <div className="page-head">
        <h2>Pembayaran</h2>
        <p>Record pembayaran manual + maker-checker verificatie (admin ≠ bendahara).</p>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      <div className="grid-2" style={{ marginTop: '1.25rem' }}>
        <Card title="Record Pembayaran Manual" sub="Alocoer bedrag naar invoice OPEN (idempotent via Idempotency-Key)">
          {formError && <Alert tone="error">{formError}</Alert>}
          {notice && <Alert tone="success">{notice}</Alert>}
          <form onSubmit={handleRecord} style={{ display: 'flex', flexDirection: 'column', gap: '0.9rem', marginTop: '0.5rem' }}>
            <div className="form-grid">
              <div className="field">
                <label htmlFor="p-inv">Invoice</label>
                <select id="p-inv" className="select" value={selInvoice} onChange={(e) => setSelInvoice(e.target.value)}>
                  <option value="">— kies invoice OPEN —</option>
                  {openInvoices.map((i) => (
                    <option key={i.id} value={i.id}>
                      #{i.id} {i.student?.name ?? ''} · {formatMoney(i.amount_cents)} ({i.status})
                    </option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="p-amount">Bedrag (Rupiah)</label>
                <input id="p-amount" className="input" type="number" min={1} step={100} placeholder="mis. 150000" value={amount} onChange={(e) => setAmount(e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="p-cashier">Cashier Naam</label>
                <input id="p-cashier" className="input" type="text" placeholder="optioneel" value={cashier} onChange={(e) => setCashier(e.target.value)} />
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy || !selInvoice}>
              {busy ? 'Recording...' : 'Record Pembayaran (PENDING)'}
            </button>
          </form>
        </Card>

        <Card title="Transacties" sub={`${payments.length} pembayaran opgeslagen`}>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Method</th>
                  <th className="right">Total</th>
                  <th>Status</th>
                  <th>Gecreeerd door</th>
                  <th>Geverifieerd door</th>
                  <th>Datum</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {payments.length === 0 && EmptyRow(8, 'Belum ada pembayaran.')}
                {payments.map((p) => (
                  <tr key={p.id}>
                    <td>{p.id}</td>
                    <td>{humanize(p.method)}</td>
                    <td className="right strong">{formatMoney(p.total_cents)}</td>
                    <td><Badge tone={paymentTone(p.status ?? '')}>{p.status}</Badge></td>
                    <td>{p.creator?.name ?? '—'}</td>
                    <td>{p.verifier?.name ?? '—'}</td>
                    <td>{formatDate(p.created_at)}</td>
                    <td>
                      {p.status === 'PENDING_VERIFICATION' && p.created_by !== user?.id && (
                        <button className="btn btn-ghost" onClick={() => handleVerify(p.id)}>Verify</button>
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