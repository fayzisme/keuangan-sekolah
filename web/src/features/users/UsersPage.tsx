import { useEffect, useState } from 'react';
import type { SchoolUser } from '../../api/client';
import { usersApi, createUserApi, updateUserApi, deleteUserApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, EmptyRow, Alert } from '../../lib/ui';

const ROLES = [
  { value: 'admin', label: 'Admin' },
  { value: 'bendahara', label: 'Bendahara' },
  { value: 'murid', label: 'Murid' },
  { value: 'ortua', label: 'Orang Tua' },
];

export function UsersPage() {
  const { token, user: currentUser, roles: currentRoles } = useAuth();
  const isAdmin = currentRoles.includes('admin');

  const [users, setUsers] = useState<SchoolUser[]>([]);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  // Form state
  const [editingId, setEditingId] = useState<number | null>(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [selectedRoles, setSelectedRoles] = useState<string[]>(['bendahara']);
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');

  const load = () => {
    if (!token) return;
    usersApi(token)
      .then((data) => setUsers(data.data as SchoolUser[]))
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat pengguna.'));
  };

  useEffect(load, [token]);

  const resetForm = () => {
    setEditingId(null);
    setName('');
    setEmail('');
    setPassword('');
    setSelectedRoles(['bendahara']);
    setFormError('');
  };

  const startEdit = (u: SchoolUser) => {
    setEditingId(u.id);
    setName(u.name);
    setEmail(u.email);
    setPassword('');
    setSelectedRoles(u.roles.length ? u.roles : ['bendahara']);
    setFormError('');
  };

  const toggleRole = (val: string) => {
    setSelectedRoles((prev) =>
      prev.includes(val) ? prev.filter((r) => r !== val) : [...prev, val],
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    setNotice('');

    if (!name.trim() || !email.trim()) {
      setFormError('Nama dan Email wajib diisi.');
      return;
    }
    if (selectedRoles.length === 0) {
      setFormError('Pilih minimal satu peran (role).');
      return;
    }
    if (editingId === null && (!password || password.length < 8)) {
      setFormError('Password minimal 8 karakter untuk pengguna baru.');
      return;
    }

    setBusy(true);
    try {
      if (editingId !== null) {
        const updated = await updateUserApi(token ?? '', editingId, {
          name: name.trim(),
          email: email.trim(),
          ...(password ? { password } : {}),
          roles: selectedRoles,
        });
        setNotice(`✓ Pengguna "${updated.name}" berhasil diperbarui.`);
      } else {
        const created = await createUserApi(token ?? '', {
          name: name.trim(),
          email: email.trim(),
          password,
          roles: selectedRoles,
        });
        setNotice(`✓ Pengguna "${created.name}" berhasil ditambahkan.`);
      }
      resetForm();
      load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Gagal menyimpan pengguna.');
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (u: SchoolUser) => {
    if (u.id === currentUser?.id) {
      alert('Anda tidak bisa menghapus akun Anda sendiri.');
      return;
    }
    if (!confirm(`Hapus pengguna "${u.name}" (${u.email}) dari sekolah ini?`)) return;

    setError('');
    setNotice('');
    try {
      await deleteUserApi(token ?? '', u.id);
      setNotice(`✓ Pengguna "${u.name}" berhasil dihapus.`);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal menghapus pengguna.');
    }
  };

  return (
    <>
      <div className="page-head">
        <h2>Pengguna</h2>
        <p>
          Manajemen pengguna sekolah &amp; peran (RBAC). Admin dapat menambah, mengubah, dan menghapus pengguna (kecuali akun sendiri).
        </p>
      </div>

      {error && <Alert tone="error">{error}</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}

      <div className="grid-2" style={{ marginTop: '1.25rem' }}>
        {isAdmin ? (
          <Card
            title={editingId !== null ? `Edit Pengguna #${editingId}` : 'Tambah Pengguna Baru'}
            sub={editingId !== null ? 'Kosongkan password bila tidak diubah' : 'Email unik per sekolah, password min 8 karakter'}
            actions={
              editingId !== null ? (
                <button className="btn btn-ghost" onClick={resetForm}>
                  Batal Edit
                </button>
              ) : undefined
            }
          >
            {formError && <Alert tone="error">{formError}</Alert>}
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '0.9rem', marginTop: '0.5rem' }}>
              <div className="field">
                <label htmlFor="u-name">Nama Lengkap</label>
                <input
                  id="u-name"
                  className="input"
                  type="text"
                  placeholder="mis. Khalid Bendahara"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                />
              </div>

              <div className="field">
                <label htmlFor="u-email">Alamat Email</label>
                <input
                  id="u-email"
                  className="input"
                  type="email"
                  placeholder="khalid@sekolah.sch.id"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>

              <div className="field">
                <label htmlFor="u-pass">Password {editingId !== null && '(opsional)'}</label>
                <input
                  id="u-pass"
                  className="input"
                  type="password"
                  placeholder={editingId !== null ? 'Biarkan kosong untuk mempertahankan' : 'Min. 8 karakter'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </div>

              <div className="field">
                <label>Peran (Roles)</label>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.6rem', marginTop: '0.2rem' }}>
                  {ROLES.map((r) => (
                    <label
                      key={r.value}
                      style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '0.35rem',
                        fontSize: '0.84rem',
                        background: selectedRoles.includes(r.value) ? 'var(--accent-bg)' : '#f8fbff',
                        padding: '0.3rem 0.65rem',
                        borderRadius: '8px',
                        border: '1px solid var(--border)',
                        cursor: 'pointer',
                      }}
                    >
                      <input
                        type="checkbox"
                        checked={selectedRoles.includes(r.value)}
                        onChange={() => toggleRole(r.value)}
                      />
                      {r.label}
                    </label>
                  ))}
                </div>
              </div>

              <button className="btn btn-primary" disabled={busy}>
                {busy ? 'Menyimpan...' : editingId !== null ? 'Simpan Perubahan' : 'Tambah Pengguna'}
              </button>
            </form>
          </Card>
        ) : (
          <Card title="Akses Dibatasi" sub="Hanya Admin yang dapat mengelola pengguna">
            <p className="muted" style={{ margin: 0 }}>
              Peran Anda saat ini tidak memiliki izin untuk tambah, ubah, atau hapus pengguna.
            </p>
          </Card>
        )}

        <Card title="Daftar Pengguna Sekolah" sub={`${users.length} pengguna terdaftar`}>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Peran</th>
                  {isAdmin && <th>Aksi</th>}
                </tr>
              </thead>
              <tbody>
                {users.length === 0 && EmptyRow(isAdmin ? 4 : 3, 'Belum ada pengguna.')}
                {users.map((u) => {
                  const isSelf = u.id === currentUser?.id;
                  return (
                    <tr key={u.id}>
                      <td className="strong">
                        {u.name}
                        {isSelf && (
                          <span className="badge badge-blue" style={{ marginLeft: '0.4rem', fontSize: '0.68rem' }}>
                            Anda
                          </span>
                        )}
                      </td>
                      <td>{u.email}</td>
                      <td>
                        {(u.roles ?? []).map((r) => (
                          <span key={r} className="badge badge-purple" style={{ marginRight: '0.3rem' }}>
                            {r}
                          </span>
                        ))}
                        {(u.roles ?? []).length === 0 && <span className="badge badge-gray">Tidak ada</span>}
                      </td>
                      {isAdmin && (
                        <td>
                          <div style={{ display: 'flex', gap: '0.35rem' }}>
                            <button className="btn btn-ghost" style={{ padding: '0.25rem 0.55rem', fontSize: '0.78rem' }} onClick={() => startEdit(u)}>
                              Ubah
                            </button>
                            <button
                              className="btn btn-danger"
                              style={{ padding: '0.25rem 0.55rem', fontSize: '0.78rem' }}
                              disabled={isSelf}
                              title={isSelf ? 'Tidak bisa menghapus akun sendiri' : 'Hapus pengguna'}
                              onClick={() => handleDelete(u)}
                            >
                              Hapus
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </>
  );
}