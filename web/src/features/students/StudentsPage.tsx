import { useEffect, useState } from 'react';
import type { Student, SchoolClass } from '../../api/client';
import { studentsApi, classesApi, createStudentApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, EmptyRow, Alert } from '../../lib/ui';

export function StudentsPage() {
  const { token } = useAuth();
  const [students, setStudents] = useState<Student[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [nis, setNis] = useState('');
  const [name, setName] = useState('');
  const [gender, setGender] = useState('');
  const [classId, setClassId] = useState('');
  const [birthDate, setBirthDate] = useState('');
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');

  const load = () => {
    if (!token) return;
    Promise.all([studentsApi(token, search), classesApi(token)])
      .then(([st, cl]) => {
        setStudents(st.data);
        setClasses(cl.data);
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat murid.'));
  };

  useEffect(load, [token, search]);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    setNotice('');
    if (!nis.trim() || !name.trim()) {
      setFormError('NIS en Naam wajib.');
      return;
    }
    setBusy(true);
    try {
      const res = await createStudentApi(token ?? '', {
        nis: nis.trim(),
        name: name.trim(),
        gender: gender || undefined,
        class_id: classId ? Number(classId) : undefined,
        birth_date: birthDate || undefined,
      });
      setNotice(`✓ Murid aangemeld${res.student ? ` (${res.student.name})` : ''}.`);
      setNis('');
      setName('');
      setGender('');
      setClassId('');
      setBirthDate('');
      load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Aanmeld murid gagal.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <div className="page-head">
        <h2>Murid</h2>
        <p>Daftar murid + aanmeld baru (NIS uniek per sekolah).</p>
      </div>

      {error && <Alert tone="error">{error}</Alert>}

      <div className="grid-2" style={{ marginTop: '1.25rem' }}>
        <Card title="Aanmeld Murid" sub="NIS uniek; klasse optioneel">
          {formError && <Alert tone="error">{formError}</Alert>}
          {notice && <Alert tone="success">{notice}</Alert>}
          <form onSubmit={handleCreate} style={{ display: 'flex', flexDirection: 'column', gap: '0.9rem', marginTop: '0.5rem' }}>
            <div className="form-grid">
              <div className="field">
                <label htmlFor="s-nis">NIS</label>
                <input id="s-nis" className="input" type="text" maxLength={30} placeholder="mis. 2026001" value={nis} onChange={(e) => setNis(e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="s-name">Naam</label>
                <input id="s-name" className="input" type="text" placeholder="Nama murid" value={name} onChange={(e) => setName(e.target.value)} />
              </div>
              <div className="field">
                <label htmlFor="s-gender">Gender</label>
                <select id="s-gender" className="select" value={gender} onChange={(e) => setGender(e.target.value)}>
                  <option value="">— blanco —</option>
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempaan</option>
                </select>
              </div>
              <div className="field">
                <label htmlFor="s-class">Klasse</label>
                <select id="s-class" className="select" value={classId} onChange={(e) => setClassId(e.target.value)}>
                  <option value="">— kies —</option>
                  {classes.map((c) => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label htmlFor="s-bd">Birth Date</label>
                <input id="s-bd" className="input" type="date" value={birthDate} onChange={(e) => setBirthDate(e.target.value)} />
              </div>
            </div>
            <button className="btn btn-primary" disabled={busy}>
              {busy ? 'Aanmeld...' : 'Aanmeld Murid'}
            </button>
          </form>
        </Card>

        <Card title="Daftar Murid" sub={`${students.length} murid`} actions={
          <span className="badge badge-blue">{classes.length} klasse</span>
        }>
          <div className="field" style={{ marginBottom: '0.75rem' }}>
            <input className="input" type="search" placeholder="Zoek op NIS of naam..." value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>NIS</th>
                  <th>Naam</th>
                  <th>Gender</th>
                  <th>Klasse</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {students.length === 0 && EmptyRow(5, 'Belum ada murid.')}
                {students.map((s) => (
                  <tr key={s.id}>
                    <td>{s.nis}</td>
                    <td className="strong">{s.name}</td>
                    <td>{s.gender ?? '—'}</td>
                    <td>{classes.find((c) => c.id === s.class_id)?.name ?? '—'}</td>
                    <td><span className={`badge ${s.is_active ? 'badge-green' : 'badge-gray'}`}>{s.is_active ? 'Aktif' : 'Inaktif'}</span></td>
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