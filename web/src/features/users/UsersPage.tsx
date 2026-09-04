import React, { useEffect, useState } from 'react';
import { usersApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';

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
    <div className="hero-card">
      <p className="eyebrow">RBAC Protected Area</p>
      <h2>Daftar Pengguna Sekolah</h2>
      {error ? (
        <div style={{ color: 'red' }}>Akses Ditolak: {error}</div>
      ) : (
        <ul>
          {users.map((u) => (
            <li key={u.id}>
              {u.name} ({u.email}) - Peran: {u.roles?.join(', ') || 'None'}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
