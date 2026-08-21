import { useState, type FormEvent } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import { ApiRequestError } from '@/lib/api'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sedangKirim, setSedangKirim] = useState(false)

  async function tanganiSubmit(event: FormEvent) {
    event.preventDefault()
    setGalat(null)
    setSedangKirim(true)

    try {
      await login({ email, password })
      const tujuan = (location.state as { dari?: string } | null)?.dari ?? '/dashboard'
      navigate(tujuan, { replace: true })
    } catch (error) {
      setGalat(
        error instanceof ApiRequestError
          ? error
          : new ApiRequestError('Terjadi kesalahan tak terduga.', 0),
      )
    } finally {
      setSedangKirim(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-slate-900">Masuk Dashboard</h1>
        <p className="mt-1 text-sm text-slate-500">Website Profil Desa Digital</p>

        <form onSubmit={tanganiSubmit} className="mt-6 space-y-4" noValidate>
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
            />
            {galat?.fieldError('email') && (
              <p className="mt-1 text-sm text-red-600">{galat.fieldError('email')}</p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              Kata Sandi
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
            />
          </div>

          {/* Galat non-validasi (mis. akun dinonaktifkan, rate limit, server mati). */}
          {galat && !galat.errors && (
            <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {galat.message}
            </p>
          )}

          <button
            type="submit"
            disabled={sedangKirim}
            className="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
          >
            {sedangKirim ? 'Memproses…' : 'Masuk'}
          </button>
        </form>
      </div>
    </div>
  )
}
