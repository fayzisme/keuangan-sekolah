import React, { useState, useEffect } from 'react';
import { AuthContext, type User, type School } from './auth-context';
import { fetchMeApi } from '../../api/client';

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [user, setUser] = useState<User | null>(() => {
    const saved = localStorage.getItem('user');
    return saved ? (JSON.parse(saved) as User) : null;
  });
  const [roles, setRoles] = useState<string[]>([]);
  const [activeSchool, setActiveSchool] = useState<School | null>(null);
  const [schools, setSchools] = useState<School[]>([]);

  // Bootstrap: saat halaman di-refresh, token tersimpan di localStorage.
  // Muat ulang roles + sekolah dari API agar state otorisasi tidak hilang.
  useEffect(() => {
    const storedToken = localStorage.getItem('token');
    if (!storedToken) return;

    let cancelled = false;
    fetchMeApi(storedToken)
      .then((data) => {
        if (cancelled) return;
        setRoles(data.roles ?? []);
        setActiveSchool(data.active_school);
        setSchools(data.schools);
      })
      .catch(() => {
        if (cancelled) return;
        // Token tidak valid/kedaluwarsa → bersihkan sesi.
        setToken(null);
        setUser(null);
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const login = (tokenVal: string, userVal: User, schoolVal: School | null, schoolsVal: School[]) => {
    setToken(tokenVal);
    setUser(userVal);
    setActiveSchool(schoolVal);
    setSchools(schoolsVal);
    localStorage.setItem('token', tokenVal);
    localStorage.setItem('user', JSON.stringify(userVal));
  };

  const logout = () => {
    setToken(null);
    setUser(null);
    setRoles([]);
    setActiveSchool(null);
    setSchools([]);
    localStorage.removeItem('token');
    localStorage.removeItem('user');
  };

  const setContextData = (data: { roles: string[]; activeSchool: School | null; schools: School[] }) => {
    setRoles(data.roles);
    setActiveSchool(data.activeSchool);
    setSchools(data.schools);
  };

  return (
    <AuthContext.Provider value={{ token, user, roles, activeSchool, schools, login, logout, setContextData }}>
      {children}
    </AuthContext.Provider>
  );
}
