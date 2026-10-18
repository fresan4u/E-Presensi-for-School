/** Status kehadiran resmi — dipakai konsisten di seluruh halaman. */
export type AttendanceStatus = 'hadir' | 'terlambat' | 'izin' | 'sakit' | 'alpa'

export interface StatusMeta {
  label: string
  /** Kelas Tailwind untuk badge status. */
  badge: string
  /** Warna titik indikator. */
  dot: string
}

export const ATTENDANCE_STATUSES: AttendanceStatus[] = [
  'hadir',
  'terlambat',
  'izin',
  'sakit',
  'alpa',
]

export const STATUS_META: Record<AttendanceStatus, StatusMeta> = {
  hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-800', dot: 'bg-emerald-600' },
  terlambat: { label: 'Terlambat', badge: 'bg-amber-50 text-amber-800', dot: 'bg-amber-500' },
  izin: { label: 'Izin', badge: 'bg-sky-50 text-sky-800', dot: 'bg-sky-500' },
  sakit: { label: 'Sakit', badge: 'bg-slate-100 text-slate-700', dot: 'bg-slate-500' },
  alpa: { label: 'Alpa', badge: 'bg-red-50 text-red-700', dot: 'bg-red-500' },
}

export type AttendanceMethod = 'face recognition' | 'fingerprint' | 'manual'

export const METHOD_LABEL: Record<AttendanceMethod, string> = {
  'face recognition': 'Face Recognition',
  fingerprint: 'Fingerprint',
  manual: 'Manual',
}

export interface AttendanceRecord {
  id: number
  nis: string
  name: string
  kelas: string
  tanggal: string
  jamMasuk: string
  jamKeluar: string
  metode: AttendanceMethod
  status: AttendanceStatus
  catatan?: string
}