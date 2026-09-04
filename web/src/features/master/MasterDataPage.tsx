import React, { useEffect, useState } from 'react';
import { academicYearsApi, studentsApi, type AcademicYear, type Student } from '../../api/client';
import { useAuth } from '../auth/useAuth';

export function MasterDataPage() {
  const { token } = useAuth();
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token) return;
    let cancelled = false;

    Promise.all([academicYearsApi(token), studentsApi(token)])
      .then(([y, s]) => {
        if (cancelled) return;
        setYears(y.data);
        setStudents(s.data);
      })
      .catch((err) => {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : 'Gagal memuat master data.');
      });

    return () => {
      cancelled = true;
    };
  }, [token]);

  return (
    <div className="hero-card" style={{ width: '100%' }}>
      <p className="eyebrow">Master Data</p>
      <h2>Tahun Ajaran & Murid</h2>

      {error && <div style={{ color: 'red', marginBottom: '1rem' }}>Error: {error}</div>}

      <h3 style={{ marginTop: '1.5rem' }}>Tahun Ajaran</h3>
      <ul>
        {years.length === 0 && <li><em>Belum ada data.</em></li>}
        {years.map((y) => (
          <li key={y.id}>
            {y.name} - {y.semester} {y.is_active ? '(aktif)' : ''}
          </li>
        ))}
      </ul>

      <h3 style={{ marginTop: '1.5rem' }}>Murid</h3>
      <ul>
        {students.length === 0 && <li><em>Belum ada data.</em></li>}
        {students.map((s) => (
          <li key={s.id}>
            {s.nis} - {s.name} ({s.gender ?? '-'})
          </li>
        ))}
      </ul>
    </div>
  );
}