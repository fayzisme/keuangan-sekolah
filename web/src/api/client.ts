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

export type SchoolUser = {
  id: number;
  name: string;
  email: string;
  roles: string[];
};

export type UserPayload = {
  name: string;
  email: string;
  password?: string;
  roles: string[];
};

export async function createUserApi(token: string, payload: UserPayload): Promise<SchoolUser> {
  return authJson('/api/v1/auth/users', token, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateUserApi(token: string, id: number, payload: Partial<UserPayload>): Promise<SchoolUser> {
  return authJson(`/api/v1/auth/users/${id}`, token, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function deleteUserApi(token: string, id: number): Promise<void> {
  const res = await fetch(`/api/v1/auth/users/${id}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.message || `Hapus gagal: ${res.status}`);
  }
}

// ---------------------------------------------------------------- types

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

export type SchoolClass = {
  id: number;
  name: string;
  is_active?: boolean;
};

export type BillType = {
  id: number;
  name: string;
  tipe_bayar: 'monthly' | 'one_time';
  tarif_cents: number;
  is_active: boolean;
};

export type Invoice = {
  id: number;
  student_id: number | null;
  bill_type_id: number | null;
  academic_year_id: number | null;
  periode_bulan: number | null;
  periode_tahun: number | null;
  amount_cents: number;
  status: 'OPEN' | 'PAID' | 'VOID';
  due_at: string | null;
  created_at?: string | null;
  student?: { id: number; name: string; nis: string } | null;
  bill_type?: { id: number; name: string; tipe_bayar?: string } | null;
};

export type Payment = {
  id: number;
  method: string;
  status: 'PENDING_VERIFICATION' | 'SETTLED' | 'FAILED' | 'REFUNDED';
  total_cents: number;
  created_by: number;
  verified_by: number | null;
  verified_at: string | null;
  created_at?: string | null;
  cashier_name?: string | null;
  gateway_trx_id?: string | null;
  creator?: { id: number; name: string } | null;
  verifier?: { id: number; name: string } | null;
  invoices?: { id: number; student_id: number | null; amount_cents: number }[];
  receipt?: { id: number; receipt_number?: string } | null;
};

export type ArrearsRow = {
  nis: string;
  nama: string;
  kelas: string;
  bill_type: string;
  tipe_bayar: string;
  periode: string;
  tagihan_cents: number;
  dibayar_cents: number;
  sisa_cents: number;
};

export type ArrearsReport = {
  data: ArrearsRow[];
  total_tagihan_cents?: number;
  total_dibayar_cents?: number;
  total_sisa_cents?: number;
};

export type Paginated<T> = { data: T[] };

// ---------------------------------------------------------------- helpers

async function authJson<T = unknown>(path: string, token: string, init: RequestInit = {}): Promise<T> {
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
    let message = `Request gagal: ${res.status}`;
    const data = await res.json().catch(() => null);
    if (data && typeof data.message === 'string') message = data.message;
    throw new Error(message);
  }

  return res.json() as Promise<T>;
}

function queryString(params: Record<string, string | number | undefined>): string {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
  return parts.length ? `?${parts.join('&')}` : '';
}

// ---------------------------------------------------------------- endpoints

export function academicYearsApi(token: string, search = ''): Promise<Paginated<AcademicYear>> {
  const qs = search ? `?search=${encodeURIComponent(search)}` : '';
  return authJson(`/api/v1/academic-years${qs}`, token);
}

export function classesApi(token: string): Promise<Paginated<SchoolClass>> {
  return authJson('/api/v1/classes', token);
}

export function billTypesApi(token: string): Promise<Paginated<BillType>> {
  return authJson('/api/v1/bill-types', token);
}

export function studentsApi(token: string, search = ''): Promise<Paginated<Student>> {
  const qs = search ? `?search=${encodeURIComponent(search)}` : '';
  return authJson(`/api/v1/students${qs}`, token);
}

export function createStudentApi(token: string, payload: {
  nis: string;
  name: string;
  class_id?: number;
  gender?: string;
  birth_date?: string;
  is_active?: boolean;
}): Promise<{ student?: Student }> {
  return authJson('/api/v1/students', token, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function invoicesApi(
  token: string,
  params: { status?: string; periode_tahun?: number; search?: string } = {},
): Promise<Paginated<Invoice>> {
  return authJson(`/api/v1/invoices${queryString(params)}`, token);
}

export function invoiceGenerateApi(token: string, payload: {
  bill_type_id: number;
  academic_year_id: number;
  periode_bulan?: number;
  periode_tahun: number;
  due_at?: string;
  student_ids?: number[];
}): Promise<{ message?: string; count?: number; invoices?: Invoice[] }> {
  return authJson('/api/v1/invoices/generate', token, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function invoiceVoidApi(token: string, id: number): Promise<{ message: string }> {
  return authJson(`/api/v1/invoices/${id}/void`, token, { method: 'POST' });
}

export function paymentsApi(
  token: string,
  params: { status?: string } = {},
): Promise<Paginated<Payment>> {
  return authJson(`/api/v1/payments${queryString(params)}`, token);
}

export function paymentManualApi(token: string, payload: {
  allocations: { invoice_id: number; amount_cents: number }[];
  cashier_name?: string;
  idempotency_key?: string;
}): Promise<Payment> {
  return authJson('/api/v1/payments/manual', token, {
    method: 'POST',
    headers: payload.idempotency_key ? { 'Idempotency-Key': payload.idempotency_key } : {},
    body: JSON.stringify(payload),
  });
}

export function paymentVerifyApi(token: string, id: number): Promise<Payment> {
  return authJson(`/api/v1/payments/${id}/verify`, token, { method: 'POST' });
}

export function arrearsApi(
  token: string,
  filters: { class_id?: number; academic_year_id?: number; bill_type_id?: number } = {},
): Promise<ArrearsReport> {
  return authJson(`/api/v1/reports/arrears${queryString(filters)}`, token);
}

export function classReportApi(token: string, classId: number): Promise<{ data?: unknown[] }> {
  return authJson(`/api/v1/reports/class/${classId}`, token);
}

export function ampleStudentReportUrl(token: string, studentId: number): string {
  return `/api/v1/reports/student/${studentId}?access_token=${encodeURIComponent(token)}`;
}

export async function downloadExport(token: string, path: string, filename: string): Promise<void> {
  const res = await fetch(path, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!res.ok) throw new Error(`Export gagal: ${res.status}`);
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export const exportUrls = {
  arrearsPdf: '/api/v1/exports/arrears/pdf',
  arrearsExcel: '/api/v1/exports/arrears/excel',
  arrearsCsv: '/api/v1/reports/arrears.csv',
  studentPdf: (studentId: number) => `/api/v1/exports/student/${studentId}/pdf`,
};