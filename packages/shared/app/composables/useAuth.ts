import { OFFICER_TOKEN_COOKIE, useApi, type ApiUser } from './useApi'

export type UserRole = 'Admin' | 'Petugas'

export interface AuthUser {
  name: string
  role: UserRole
  email: string
}

/** Halaman tujuan setelah login berdasarkan role. */
export const homeForRole = (role?: UserRole | string) =>
  role === 'Petugas' ? '/petugas' : '/'

/** Cookie profil sesi panel (dipakai untuk menampilkan nama/role). */
export const AUTH_USER_COOKIE = 'presensi-auth-user'

/** Role dari server (`admin`|`petugas`) → label UI (`Admin`|`Petugas`). */
const toUserRole = (name?: string): UserRole => (name === 'petugas' ? 'Petugas' : 'Admin')

const toAuthUser = (user: ApiUser): AuthUser => ({
  name: user.name,
  email: user.email,
  role: toUserRole(user.role?.name),
})

/**
 * Sesi login panel admin & petugas.
 *
 * Memakai token Sanctum (`presensi-auth-token`) hasil `POST /auth/login`.
 * Role TIDAK dipilih di UI — server yang menentukan dari akun, sehingga app
 * admin & petugas bisa mengarahkan user ke panel yang tepat.
 */
export const useAuth = () => {
  const token = useCookie<string | null>(OFFICER_TOKEN_COOKIE, {
    default: () => null,
    sameSite: 'lax',
    maxAge: 60 * 60 * 24 * 7,
  })

  const user = useCookie<AuthUser | null>(AUTH_USER_COOKIE, {
    default: () => null,
    sameSite: 'lax',
    maxAge: 60 * 60 * 24 * 7,
  })

  const api = useApi()

  const isLoggedIn = computed(() => Boolean(token.value))

  /** Login email + password. Role diambil dari respons server. */
  const login = async (email: string, password: string): Promise<AuthUser> => {
    const response = await api.login(email, password)
    token.value = response.data.token
    user.value = toAuthUser(response.data.user)

    return user.value
  }

  /** Segarkan profil dari server (opsional; sesi tetap valid tanpa ini). */
  const fetchMe = async (force = false): Promise<AuthUser | null> => {
    if (!token.value) return null
    if (user.value && !force) return user.value

    const response = await api.me()
    user.value = toAuthUser(response.data)

    return user.value
  }

  /** Akhiri sesi: cabut token di server lalu bersihkan cookie lokal. */
  const logout = async (): Promise<void> => {
    try {
      await api.logout()
    } catch {
      // Sesi lokal tetap dibersihkan walau server tidak terjangkau.
    }
    token.value = null
    user.value = null
  }

  return {
    token,
    user,
    isLoggedIn,
    login,
    fetchMe,
    logout,
  }
}
