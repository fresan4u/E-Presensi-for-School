/**
 * useApi
 * ------------------------------------------------------------------
 * Klien tipis untuk backend Laravel (API v1). Semua request lewat sini
 * supaya base URL dan header konsisten, serta mudah diganti saat auth
 * (Sanctum) diaktifkan nanti.
 *
 * Dipakai di dalam `setup()` karena memanggil `useRuntimeConfig()`.
 */
import type { AttendanceStatus } from '../utils/attendance'

/* ---------- Bentuk respons Laravel ---------- */

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface Collection<T> {
  data: T[]
}

export interface Item<T> {
  data: T
}

/* ---------- Sumber daya ---------- */

export interface ApiLookup {
  id: number
  code: string
  label: string
}

export interface ApiClassroom {
  id: number
  name: string
  rombel: number
  grade_level?: { id: number; name: string; level: number }
  major?: { id: number; code: string; name: string }
}

export interface ApiStudent {
  id: number
  nis: string
  full_name: string
  status: ApiLookup | null
  classroom: ApiClassroom | null
  guardians?: ApiStudentGuardian[]
  face_descriptors_count: number | null
}

/** Profil siswa untuk portal mobile (login NIS + PIN). */
export interface ApiStudentProfile extends ApiStudent {
  must_change_pin: boolean
  /** PIN hanya bisa diganti sekali; `false` = gerbang sudah tertutup. */
  can_change_pin: boolean
}

export interface StudentAuthResult {
  data: {
    token: string
    student: ApiStudentProfile
  }
}

export interface StudentPinPayload {
  current_pin: string
  pin: string
  pin_confirmation: string
}

export interface ApiFaceDescriptor {
  id: number
  student_id: number
  sample_no: number
  model_version: string | null
  quality_score: number | null
  is_active: boolean
  captured_at: string
}

export interface ApiAttendance {
  id: number
  attendance_date: string
  checked_at: string
  match_distance: number | null
  note: string | null
  status: { code: AttendanceStatus; label: string }
  method: { code: string; label: string }
  session: { code: string; label: string }
  student: ApiStudent | null
}

export interface ScanResult {
  data: ApiAttendance
  meta: { created: boolean; distance: number; threshold: number; wa_queued: boolean }
}

export interface ApiFaceMeta {
  samples_per_student: number
  descriptor_length: number
  match_threshold: number
  model_version: string
}

export interface ApiMeta {
  student_statuses: ApiLookup[]
  attendance_statuses: ApiLookup[]
  attendance_methods: ApiLookup[]
  attendance_sessions: Array<ApiLookup & { boundary_time: string | null }>
  attendance_window: {
    self_cutoff: string
    handover_at: string
    pulang_from: string
  }
  face: ApiFaceMeta
}

export interface ApiStudentGuardian {
  id: number
  name: string
  relationship: string | null
  phone: string
  is_primary: boolean
  consent_at: string | null
  biometric_consent_at: string | null
}

export interface GuardianPayload {
  name?: string | null
  relationship?: string | null
  phone: string
  is_primary?: boolean
}

export interface StudentPayload {
  nis: string
  full_name: string
  status?: string
  classroom_id?: number | null
  academic_year_id?: number | null
  guardians?: GuardianPayload[]
}

export interface StudentQuery {
  search?: string
  classroom_id?: number | null
  status?: string
  page?: number
  per_page?: number
}

export interface ApiRole {
  id: number
  name: string
  label: string
}

export interface ApiUser {
  id: number
  name: string
  email: string
  is_active: boolean
  role: ApiRole | null
  created_at?: string
  updated_at?: string
}

export interface OfficerPayload {
  name: string
  email: string
  password?: string
  is_active?: boolean
}

export interface OfficerQuery {
  search?: string
  is_active?: boolean
  page?: number
  per_page?: number
}

export interface ApiAcademicYear {
  id: number
  label: string
  is_active: boolean
}

export interface ApiGuardian {
  name: string
  relationship: string
  phone: string
}

export interface ApiRecapStudent {
  id: number
  nis: string
  full_name: string
  status: { code: AttendanceStatus; label: string }
  checked_at: string | null
  note: string | null
  guardian: ApiGuardian | null
}

export interface ApiAttendanceRecap {
  date: string
  academic_year_id: number | null
  classroom: ApiClassroom | null
  summary: {
    total: number
    hadir: number
    terlambat: number
    izin: number
    sakit: number
    alpa: number
  }
  students: ApiRecapStudent[]
}

export interface RecapQuery {
  classroom_id: number
  date?: string
  academic_year_id?: number | null
}

/** Ambil pesan error yang ramah dari error `$fetch`. */
export const apiErrorMessage = (error: unknown): string => {
  const data = (error as { data?: { message?: string; errors?: Record<string, string[]> } })?.data
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0]
    if (first) return first
  }
  if (data?.message) return data.message
  return (error as { message?: string })?.message || 'Terjadi kesalahan saat menghubungi server.'
}

/** Nama cookie penyimpan token Sanctum siswa (portal mobile). */
export const STUDENT_TOKEN_COOKIE = 'presensi-student-token'

/** Nama cookie penyimpan token Sanctum admin/petugas (panel). */
export const OFFICER_TOKEN_COOKIE = 'presensi-auth-token'

/** Hasil login panel admin/petugas (email + password → token Sanctum). */
export interface OfficerAuthResult {
  data: {
    token: string
    user: ApiUser
  }
}

export const useApi = () => {
  const config = useRuntimeConfig()

  /*
   * Dua jenis sesi disimpan di cookie terpisah dan cookie tidak ter-scope per
   * port. Agar app admin/petugas tidak pernah mengirim token siswa (dan
   * sebaliknya), header Authorization hanya dipasang sesuai jenis endpoint:
   * `/student/*` memakai token siswa, sisanya memakai token petugas.
   */
  const studentToken = useCookie<string | null>(STUDENT_TOKEN_COOKIE, { default: () => null })
  const officerToken = useCookie<string | null>(OFFICER_TOKEN_COOKIE, { default: () => null })

  const client = $fetch.create({
    baseURL: config.public.apiBase as string,
    headers: { Accept: 'application/json' },
    onRequest({ request, options }) {
      const token = String(request).includes('/student/')
        ? studentToken.value
        : officerToken.value
      if (!token) return
      const headers = new Headers(options.headers as HeadersInit | undefined)
      headers.set('Authorization', `Bearer ${token}`)
      options.headers = headers
    },
  })

  return {
    /* Sesi panel admin & petugas */
    login: (email: string, password: string) =>
      client<OfficerAuthResult>('/auth/login', { method: 'POST', body: { email, password } }),
    me: () => client<Item<ApiUser>>('/auth/me'),
    logout: () => client<{ message: string }>('/auth/logout', { method: 'POST' }),

    /* Referensi */
    meta: () => client<Item<ApiMeta>>('/meta'),
    classrooms: () => client<Collection<ApiClassroom>>('/classrooms'),
    academicYears: () => client<Collection<ApiAcademicYear>>('/academic-years'),

    /* Siswa */
    students: (query: StudentQuery = {}) =>
      client<Paginated<ApiStudent>>('/students', { query }),
    student: (id: number) => client<Item<ApiStudent>>(`/students/${id}`),
    createStudent: (body: StudentPayload) =>
      client<Item<ApiStudent>>('/students', { method: 'POST', body }),
    updateStudent: (id: number, body: Partial<StudentPayload>) =>
      client<Item<ApiStudent>>(`/students/${id}`, { method: 'PUT', body }),
    deleteStudent: (id: number) =>
      client<{ message: string }>(`/students/${id}`, { method: 'DELETE' }),
    importStudents: (file: File) => {
      const form = new FormData()
      form.append('file', file)
      return client<Item<{ created: number; updated: number; errors: string[] }>>(
        '/students/import',
        { method: 'POST', body: form },
      )
    },

    /* Petugas (akun admin panel) */
    officers: (query: OfficerQuery = {}) =>
      client<Paginated<ApiUser>>('/officers', { query }),
    officer: (id: number) => client<Item<ApiUser>>(`/officers/${id}`),
    createOfficer: (body: OfficerPayload) =>
      client<Item<ApiUser>>('/officers', { method: 'POST', body }),
    updateOfficer: (id: number, body: Partial<OfficerPayload>) =>
      client<Item<ApiUser>>(`/officers/${id}`, { method: 'PUT', body }),
    deleteOfficer: (id: number) =>
      client<{ message: string }>(`/officers/${id}`, { method: 'DELETE' }),

    /* Descriptor wajah */
    faceDescriptors: (studentId: number) =>
      client<Collection<ApiFaceDescriptor>>(`/students/${studentId}/face-descriptors`),
    registerFace: (
      studentId: number,
      descriptor: number[],
      options: { sample_no?: number; quality_score?: number } = {},
    ) =>
      client<Item<ApiFaceDescriptor>>(`/students/${studentId}/face-descriptors`, {
        method: 'POST',
        body: { descriptor, ...options },
      }),
    deleteFace: (studentId: number, descriptorId: number) =>
      client<{ message: string }>(`/students/${studentId}/face-descriptors/${descriptorId}`, {
        method: 'DELETE',
      }),

    /* Absensi */
    scan: (descriptor: number[], session: 'masuk' | 'pulang', photo?: string) =>
      client<ScanResult>('/attendance/scan', {
        method: 'POST',
        body: { descriptor, session, ...(photo ? { photo } : {}) },
      }),
    attendances: (query: Record<string, unknown> = {}) =>
      client<Paginated<ApiAttendance>>('/attendances', { query }),
    attendanceRecap: (query: RecapQuery) =>
      client<Item<ApiAttendanceRecap>>('/attendances/recap', { query }),
    storeAttendance: (body: Record<string, unknown>) =>
      client<Item<ApiAttendance>>('/attendances', { method: 'POST', body }),
    updateAttendance: (id: number, body: { status?: string; note?: string | null }) =>
      client<Item<ApiAttendance>>(`/attendances/${id}`, { method: 'PUT', body }),

    /* Portal siswa (app mobile) — login NIS + PIN, absensi wajah 1:1 */
    studentLogin: (nis: string, pin: string) =>
      client<StudentAuthResult>('/student/login', { method: 'POST', body: { nis, pin } }),
    studentMe: () => client<Item<ApiStudentProfile>>('/student/me'),
    studentUpdatePin: (body: StudentPinPayload) =>
      client<Item<ApiStudentProfile> & { message: string }>('/student/pin', { method: 'PUT', body }),
    studentLogout: () => client<{ message: string }>('/student/logout', { method: 'POST' }),
    studentAttendances: (query: Record<string, unknown> = {}) =>
      client<Paginated<ApiAttendance>>('/student/attendances', { query }),
    studentScan: (descriptor: number[], photo?: string, lat?: number | null, lng?: number | null) =>
      client<ScanResult>('/student/attendance/scan', {
        method: 'POST',
        body: {
          descriptor,
          ...(photo ? { photo } : {}),
          ...(lat !== null && lat !== undefined ? { lat } : {}),
          ...(lng !== null && lng !== undefined ? { lng } : {}),
        },
      }),
  }
}
