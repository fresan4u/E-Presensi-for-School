/**
 * useStudentAuth
 * ------------------------------------------------------------------
 * Sesi login portal siswa (app mobile) memakai NIS + PIN.
 *
 * - Token Sanctum disimpan di cookie `presensi-student-token`; `useApi`
 *   memasangnya sebagai header Authorization secara otomatis.
 * - Profil siswa disimpan di `useState` supaya bertahan antar-navigasi.
 */
import type { ApiStudentProfile, StudentPinPayload } from './useApi'
import { STUDENT_TOKEN_COOKIE, useApi } from './useApi'

export const useStudentAuth = () => {
  const token = useCookie<string | null>(STUDENT_TOKEN_COOKIE, {
    default: () => null,
    sameSite: 'lax',
    maxAge: 60 * 60 * 24 * 30,
  })

  const student = useState<ApiStudentProfile | null>('student-profile', () => null)
  const api = useApi()

  const isLoggedIn = computed(() => Boolean(token.value))

  /** Login NIS + PIN; menyimpan token & profil. */
  async function login(nis: string, pin: string): Promise<ApiStudentProfile> {
    const response = await api.studentLogin(nis, pin)
    token.value = response.data.token
    student.value = response.data.student

    return student.value
  }

  /** Ambil profil siswa (dari cache kecuali `force`). */
  async function fetchMe(force = false): Promise<ApiStudentProfile | null> {
    if (!token.value) return null
    if (student.value && !force) return student.value

    const response = await api.studentMe()
    student.value = response.data

    return student.value
  }

  /** Ganti PIN (sekaligus menandai PIN bukan default). */
  async function changePin(payload: StudentPinPayload): Promise<ApiStudentProfile> {
    const response = await api.studentUpdatePin(payload)
    student.value = response.data

    return student.value
  }

  /** Akhiri sesi (token dicabut di server + dibersihkan lokal). */
  async function logout(): Promise<void> {
    try {
      await api.studentLogout()
    } catch {
      // Sesi lokal tetap dibersihkan walau server tidak terjangkau.
    }
    token.value = null
    student.value = null
  }

  return { token, student, isLoggedIn, login, fetchMe, changePin, logout }
}
