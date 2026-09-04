export type ApiPingResponse = {
  status: 'ok';
  message: string;
};

export async function pingApi(): Promise<ApiPingResponse> {
  const response = await fetch('/api/v1/ping', {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`API ping gagal: ${response.status}`);
  }

  return response.json() as Promise<ApiPingResponse>;
}

export async function loginApi(email: string, password: string) {
  const res = await fetch('/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.message || 'Login gagal.');
  }

  return res.json();
}

export async function fetchMeApi(token: string) {
  const res = await fetch('/api/v1/auth/me', {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (!res.ok) throw new Error('Unauthorized');
  return res.json();
}

export async function logoutApi(token: string) {
  await fetch('/api/v1/auth/logout', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });
}

export async function switchSchoolApi(token: string, schoolId: number) {
  const res = await fetch('/api/v1/auth/switch-school', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ school_id: schoolId }),
  });

  if (!res.ok) throw new Error('Gagal switch sekolah.');
  return res.json();
}

export async function usersApi(token: string) {
  const res = await fetch('/api/v1/auth/users', {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (!res.ok) throw new Error('403 Forbidden / Akses Ditolak');
  return res.json();
}

export type AcademicYear = {
  id: number;
  name: string;
  semester: 'ganjil' | 'genap';
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
};

export type Student = {
  id: number;
  nis: string;
  name: string;
  gender: 'L' | 'P' | null;
  class_id: number | null;
  is_active: boolean;
};

export type Paginated<T> = { data: T[] };

async function publicJson<T = unknown>(path: string, token: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(path, {
    ...init,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...(init.headers ?? {}),
    },
  });

  if (!res.ok) {
    if (res.status === 403) throw new Error('403 Forbidden');
    if (res.status === 404) throw new Error('404 Not Found');
    throw new Error(`Request gagal: ${res.status}`);
  }

  return res.json() as Promise<T>;
}

export function academicYearsApi(token: string, search = ''): Promise<Paginated<AcademicYear>> {
  const qs = search ? `?search=${encodeURIComponent(search)}` : '';
  return publicJson(`/api/v1/academic-years${qs}`, token);
}

export function studentsApi(token: string, search = ''): Promise<Paginated<Student>> {
  const qs = search ? `?search=${encodeURIComponent(search)}` : '';
  return publicJson(`/api/v1/students${qs}`, token);
}
