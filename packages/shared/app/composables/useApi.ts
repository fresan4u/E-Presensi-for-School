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
  /** Hanya dikirim ke panel admin/petugas; tidak pernah untuk siswa. */
  pin?: string
  status: ApiLookup | null
  /** Tahun ajaran untuk kelas yang sedang ditampilkan. */
  academic_year?: Pick<ApiAcademicYear, 'id' | 'label'> | null
  enrollment_active?: boolean | null
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
  /** Jarak HP ke titik sekolah saat scan (meter); null bila tanpa GPS. */
  distance_meter: number | null
  gps_accuracy_meter: number | null
  note: string | null
  status: { code: AttendanceStatus; label: string }
  method: { code: string; label: string }
  session: { code: string; label: string }
  student: ApiStudent | null
}

/**
 * Ringkasan jendela absensi mandiri untuk satu hari. Key lama
 * (`self_cutoff`, `handover_at`, `pulang_from`) tetap dikirim server supaya
 * klien yang sudah terdeploy tidak rusak; key baru dipakai untuk jadwal
 * per hari, hari libur, dan status buka/tutup.
 */
export interface ApiAttendanceWindow {
  date: string
  day_of_week: number
  day_name: string
  self_cutoff: string | null
  handover_at: string | null
  pulang_from: string | null
  masuk_start: string | null
  masuk_end: string | null
  pulang_end: string | null
  late_after: string | null
  self_scan_enabled: boolean
  is_school_day: boolean
  is_holiday: boolean
  holiday_name: string | null
  is_open: boolean
  current_session: 'masuk' | 'pulang' | null
  reason: 'holiday' | 'not_school_day' | 'disabled' | 'outside_window' | null
  message: string | null
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
  attendance_window: ApiAttendanceWindow
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
  /** PIN awal opsional; kosong = memakai NIS. */
  pin?: string | null
  classroom_id?: number | null
  academic_year_id?: number | null
  guardians?: GuardianPayload[]
}

export interface StudentQuery {
  search?: string
  classroom_id?: number | null
  status?: string
  academic_year_id?: number | null
  unassigned?: boolean
  sort?: 'name' | 'nis' | 'classroom'
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface StudentBulkPayload {
  academic_year_id: number
  action: 'activate' | 'deactivate' | 'delete'
  ids?: number[]
  all?: boolean
}

/** Titik pusat & radius area absensi sekolah (geofencing app siswa). */
export interface ApiSchoolLocation {
  latitude: number
  longitude: number
  radius_meter: number
  geofence_enabled: boolean
  /** `false` = masih memakai fallback config, admin belum pernah menyimpan. */
  configured: boolean
  updated_at: string | null
}

export interface SchoolLocationPayload {
  latitude: number
  longitude: number
  radius_meter: number
  geofence_enabled?: boolean
}

/* ---------- Pengaturan admin ---------- */

export interface ApiAppSettings {
  attendance: {
    self_scan_enabled: boolean
  }
  face: {
    match_threshold: number
    samples_per_student: number
  }
  notification: {
    wa_enabled: boolean
  }
  location: ApiSchoolLocation
}

export interface AppSettingsPayload {
  attendance?: {
    self_scan_enabled?: boolean
  }
  face?: {
    match_threshold?: number
    samples_per_student?: number
  }
  notification?: {
    wa_enabled?: boolean
  }
}

export interface ApiSettingsResponse {
  data: ApiAppSettings
  /** Ringkasan jendela absensi hari ini (server time). */
  status: ApiAttendanceWindow & { server_time: string }
}

export interface ApiAttendanceSchedule {
  day_of_week: number
  day_name: string
  is_active: boolean
  masuk_start: string
  masuk_end: string
  handover_at: string | null
  pulang_start: string
  pulang_end: string
  late_after: string | null
}

export interface AttendanceSchedulePayload {
  day_of_week: number
  is_active: boolean
  masuk_start: string
  masuk_end: string
  handover_at: string | null
  pulang_start: string
  pulang_end: string
  late_after: string | null
}

export interface ApiSchoolHoliday {
  id: number
  date: string
  name: string
  description: string | null
}

export interface SchoolHolidayPayload {
  date: string
  name: string
  description?: string | null
}

export interface ApiSettingsAuditLog {
  id: number
  user_id: number | null
  action: 'create' | 'update' | 'delete'
  subject_type: string
  subject_id: number | null
  changes: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  user?: Pick<ApiUser, 'id' | 'name' | 'email'>
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
  start_date: string
  end_date: string
  is_active: boolean
}

export interface AcademicYearPayload {
  label: string
  start_date: string
  end_date: string
  is_active?: boolean
}

export interface ApiGuardian {
  name: string
  relationship: string
  phone: string
}

export interface ApiRecapStudent {
  id: number
  /** ID baris absensi hari itu; null bila siswa belum punya absensi (alpa). */
  attendance_id: number | null
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
  const failure = error as {
    status?: number
    statusCode?: number
    data?: { message?: string; errors?: Record<string, string[]> }
  }

  // Sesi kedaluwarsa/dicabut (mis. database demo di-reset saat deploy).
  // Login yang gagal juga memakai 401, jadi pesan dari server tetap dipakai
  // bila ada; fallback hanya untuk 401 tanpa pesan ("Unauthenticated.").
  if (failure.status === 401 || failure.statusCode === 401) {
    const message = failure.data?.message?.trim()
    if (!message || message.toLowerCase() === 'unauthenticated.') {
      return 'Sesi Anda sudah berakhir. Silakan login kembali.'
    }

    return message
  }

  const data = failure.data
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
    credentials: 'include',
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
    createAcademicYear: (body: AcademicYearPayload) =>
      client<Item<ApiAcademicYear>>('/academic-years', { method: 'POST', body }),
    updateAcademicYear: (id: number, body: AcademicYearPayload) =>
      client<Item<ApiAcademicYear>>(`/academic-years/${id}`, { method: 'PUT', body }),
    activateAcademicYear: (id: number) =>
      client<Item<ApiAcademicYear> & { message: string }>(`/academic-years/${id}/activate`, {
        method: 'PATCH',
      }),
    deleteAcademicYear: (id: number) =>
      client<{ message: string }>(`/academic-years/${id}`, { method: 'DELETE' }),

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
    bulkStudentAction: (body: StudentBulkPayload) =>
      client<Item<{ count: number; action: string }> & { message: string }>(
        '/students/bulk-action',
        { method: 'POST', body },
      ),
    importStudents: (file: File, academicYearId?: number | null) => {
      const form = new FormData()
      form.append('file', file)
      if (academicYearId) form.append('academic_year_id', String(academicYearId))
      return client<Item<{ created: number; updated: number; errors: string[] }>>(
        '/students/import',
        { method: 'POST', body: form },
      )
    },

    /* Lokasi sekolah (geofencing absensi mandiri) */
    schoolLocation: () => client<Item<ApiSchoolLocation>>('/school-location'),
    updateSchoolLocation: (body: SchoolLocationPayload) =>
      client<Item<ApiSchoolLocation> & { message: string }>('/school-location', {
        method: 'PUT',
        body,
      }),

    /* Pengaturan operasional (khusus admin) */
    settings: () => client<ApiSettingsResponse>('/settings'),
    updateSettings: (body: AppSettingsPayload) =>
      client<ApiSettingsResponse>('/settings', { method: 'PUT', body }),
    settingsAudit: (query: { page?: number; per_page?: number } = {}) =>
      client<Paginated<ApiSettingsAuditLog>>('/settings/audit', { query }),

    /* Jadwal absensi per hari (Senin–Minggu) */
    attendanceSchedules: () =>
      client<Collection<ApiAttendanceSchedule>>('/attendance-schedules'),
    updateAttendanceSchedules: (schedules: AttendanceSchedulePayload[]) =>
      client<Collection<ApiAttendanceSchedule>>('/attendance-schedules', {
        method: 'PUT',
        body: { schedules },
      }),

    /* Kalender hari libur */
    schoolHolidays: (year: number) =>
      client<Collection<ApiSchoolHoliday>>('/school-holidays', { query: { year } }),
    createSchoolHoliday: (body: SchoolHolidayPayload) =>
      client<Item<ApiSchoolHoliday>>('/school-holidays', { method: 'POST', body }),
    updateSchoolHoliday: (id: number, body: SchoolHolidayPayload) =>
      client<Item<ApiSchoolHoliday>>(`/school-holidays/${id}`, { method: 'PUT', body }),
    deleteSchoolHoliday: (id: number) =>
      client<{ message: string }>(`/school-holidays/${id}`, { method: 'DELETE' }),

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
    studentScan: (
      descriptor: number[],
      photo?: string,
      lat?: number | null,
      lng?: number | null,
      accuracy?: number | null,
    ) =>
      client<ScanResult>('/student/attendance/scan', {
        method: 'POST',
        body: {
          descriptor,
          ...(photo ? { photo } : {}),
          ...(lat !== null && lat !== undefined ? { lat } : {}),
          ...(lng !== null && lng !== undefined ? { lng } : {}),
          ...(accuracy !== null && accuracy !== undefined ? { accuracy } : {}),
        },
      }),
    /* Referensi untuk portal siswa (jendela absen, status, sesi). */
    studentMeta: () => client<Item<ApiMeta>>('/student/meta'),
    studentFaceDescriptors: () =>
      client<Collection<ApiFaceDescriptor>>('/student/face-descriptors'),
    studentRegisterFace: (
      descriptor: number[],
      options: { sample_no?: number; quality_score?: number } = {},
    ) =>
      client<Item<ApiFaceDescriptor>>('/student/face-descriptors', {
        method: 'POST',
        body: { descriptor, ...options },
      }),
    studentDeleteFace: (descriptorId: number) =>
      client<{ message: string }>(`/student/face-descriptors/${descriptorId}`, {
        method: 'DELETE',
      }),
  }
}
