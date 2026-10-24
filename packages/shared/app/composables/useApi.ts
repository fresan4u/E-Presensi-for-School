import { $fetch } from 'ofetch'
import type { ApiUser, ApiStudent, ApiClassroom, ApiAcademicYear, ApiFaceDescriptor, ApiAttendance, ApiAttendanceSession, ApiMeta, AttendanceStatusCode, Paginated, Collection, Item, OfficerAuthResult, StudentAuthResult, ScanResult } from '../types'

export const useApi = () => {
  const config = useRuntimeConfig()
  const token = useCookie('presensi-admin-token', { sameSite: 'lax', maxAge: 60 * 60 * 24 * 7 })
  const studentToken = useCookie('presensi-student-token', { sameSite: 'lax', maxAge: 60 * 60 * 24 * 7 })

  const client = $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const headers = new Headers(options.headers)
      if (token.value) {
        headers.set('Authorization', `Bearer ${token.value}`)
      } else if (studentToken.value) {
        headers.set('Authorization', `Bearer ${studentToken.value}`)
      }
      options.headers = headers
    },
  })

  return {
    meta: () => client<Item<ApiMeta>>('/meta'),
    classrooms: () => client<Collection<ApiClassroom>>('/classrooms'),
    academicYears: () => client<Collection<ApiAcademicYear>>('/academic-years'),

    students: (query: Record<string, unknown> = {}) =>
      client<Paginated<ApiStudent>>('/students', { query }),
    student: (id: number) => client<Item<ApiStudent>>(`/students/${id}`),
    createStudent: (body: Partial<ApiStudent> & { pin?: string }) =>
      client<Item<ApiStudent>>('/students', { method: 'POST', body }),
    updateStudent: (id: number, body: Partial<ApiStudent> & { pin?: string; remove_photo?: boolean }) =>
      client<Item<ApiStudent>>(`/students/${id}`, { method: 'PUT', body }),
    deleteStudent: (id: number) => client<{ message: string }>(`/students/${id}`, { method: 'DELETE' }),
    importStudents: (file: File) => {
      const formData = new FormData()
      formData.append('file', file)
      return client<Item<{ created: number; updated: number; errors: string[] }>>('/students/import', {
        method: 'POST',
        body: formData,
      })
    },

    officers: (query: Record<string, unknown> = {}) =>
      client<Paginated<ApiUser>>('/officers', { query }),
    officer: (id: number) => client<Item<ApiUser>>(`/officers/${id}`),
    createOfficer: (body: Partial<ApiUser> & { password?: string }) =>
      client<Item<ApiUser>>('/officers', { method: 'POST', body }),
    updateOfficer: (id: number, body: Partial<ApiUser> & { password?: string }) =>
      client<Item<ApiUser>>(`/officers/${id}`, { method: 'PUT', body }),
    deleteOfficer: (id: number) => client<{ message: string }>(`/officers/${id}`, { method: 'DELETE' }),

    faceDescriptors: (studentId: number) =>
      client<Collection<ApiFaceDescriptor>>(`/students/${studentId}/face-descriptors'),
    registerFace: (studentId: number, descriptor: number[], metadata = {}) =>
      client<Item<ApiFaceDescriptor>>(`/students/${studentId}/face-descriptors', {
        method: 'POST',
        body: { descriptor, ...metadata },
        formData: false,
      }),
    deleteFace: (studentId: number, descriptorId: number) =>
      client<{ message: string }>(`/students/${studentId}/face-descriptors/${descriptorId}`, { method: 'DELETE' }),

    scan: (descriptor: number[], session?: string) =>
      client<ScanResult>('/attendance/scan', {
        method: 'POST',
        body: { descriptor, ...(session ? { session } : {}) },
      }),

    attendances: (query: Record<string, unknown> = {}) =>
      client<Paginated<ApiAttendance>>('/attendances', { query }),
    attendanceRecap: (query: Record<string, unknown> = {}) =>
      client<Paginated<APIRecapItem>>('/attendances/recap', { query }),
    storeAttendance: (body: {
      student_id: number;
      attendance_date: string;
      session_id: number;
      status: AttendanceStatusCode;
      note?: string;
    }) =>
      client<Item<ApiAttendance>>('/attendances', { method: 'POST', body }),
    updateAttendance: (id: number, body: Partial<{ status: AttendanceStatusCode; note: string }>) =>
      client<Item<ApiAttendance>>(`/attendances/${id}`, { method: 'PUT', body }),

    officerLogin: (email: string, password: string) =>
      client<OfficerAuthResult>('/auth/login', { method: 'POST', body: { email, password } }),
    officerMe: () => client<Item<ApiUser>>('/auth/me'),
    officerLogout: () => client<{ message: string }>('/auth/logout', { method: 'POST' }),

    studentLogin: (nis: string, pin: string) =>
      client<StudentAuthResult>('/student/login', { method: 'POST', body: { nis, pin } }),
    studentMe: () => client<Item<ApiStudent>>('/student/me'),
    studentUpdatePin: (body: { old_pin: string; new_pin: string; new_pin_confirmation: string }) =>
      client<Item<ApiStudent>>('/student/pin', { method: 'PUT', body }),
    studentLogout: () => client<{ message: string }>('/student/logout', { method: 'POST' }),
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

export type APIRecapItem = {
  student_id: number
  name: string
  nis: string
  kelas: string | null
  hadir: number
  terlambat: number
  sakit: number
  izin: number
  alpha: number
  total: number
}
