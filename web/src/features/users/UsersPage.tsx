import { useEffect, useState } from 'react';
import { usersApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';
import { Card, EmptyRow, Alert } from '../../lib/ui';

type SchoolUser = {
  id: number;
  name: string;
  email: string;
  roles: string[];
};

export function UsersPage() {
  const [users, setUsers] = useState<SchoolUser[]>([]);
  const [error, setError] = useState('');
  const { token } = useAuth();

  useEffect(() => {
    usersApi(token ?? '')
      .then((data) => setUsers(data.data as SchoolUser[]))
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat pengguna.'));
  }, [token]);

  return (
    <>
      <div className="page-head">
        <h2>Pengguna</h2>
        <p>Daftar pengguna sekolah met hun peran (RBAC). Admin dapat alle acties; bendahara alleen payment flow.</p>
      </div>

      <Card title="Daftar Pengguna" sub={`${users.length} pengguna`} style={{ marginTop: '1.25rem' }}>
        {error ? (
          <Alert tone="error">Akses Ditolak: {error}</Alert>
        ) : (
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Naam</th>
                  <th>Email</th>
                  <th>Peran</th>
                </tr>
              </thead>
              <tbody>
                {users.length === 0 && EmptyRow(3, 'Belum ada pengguna.')}
                {users.map((u) => (
                  <tr key={u.id}>
                    <td className="strong">{u.name}</td>
                    <td>{u.email}</td>
                    <td>
                      {(u.roles ?? []).map((r) => (
                        <span key={r} className="badge badge-purple" style={{ marginRight: '0.35rem' }}>
                          {r}
                        </span>
                      ))}
                      {(u.roles ?? []).length === 0 && <span className="badge badge-gray">Geen peran</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}