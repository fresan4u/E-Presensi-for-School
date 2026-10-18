/**
 * Pemetaan gaya visual status kehadiran untuk halaman siswa.
 * Label teks diambil dari respons API (`status.label`), di sini hanya
 * kelas warna + ikon agar tampilan konsisten.
 */

export type StatusKode = 'hadir' | 'terlambat' | 'izin' | 'sakit' | 'alpa'
export type StatusIkon = 'check' | 'clock' | 'alert'

export interface GayaStatus {
  /** Kelas Tailwind untuk badge status. */
  badge: string
  /** Kelas Tailwind untuk kotak ikon. */
  ikonBox: string
  ikon: StatusIkon
}

export const GAYA_STATUS: Record<StatusKode, GayaStatus> = {
  hadir: { badge: 'bg-brand-100 text-brand-700', ikonBox: 'bg-brand-50 text-brand-700', ikon: 'check' },
  terlambat: { badge: 'bg-amber-100 text-amber-700', ikonBox: 'bg-amber-50 text-amber-600', ikon: 'clock' },
  izin: { badge: 'bg-sky-100 text-sky-700', ikonBox: 'bg-sky-50 text-sky-600', ikon: 'alert' },
  sakit: { badge: 'bg-slate-100 text-slate-600', ikonBox: 'bg-slate-50 text-slate-500', ikon: 'alert' },
  alpa: { badge: 'bg-danger-100 text-danger-600', ikonBox: 'bg-danger-50 text-danger-500', ikon: 'alert' },
}

/** Gaya untuk satu kode status (jatuh ke `alpa` bila kode tak dikenal). */
export function gayaStatus(kode: string): GayaStatus {
  return GAYA_STATUS[kode as StatusKode] ?? GAYA_STATUS.alpa
}
