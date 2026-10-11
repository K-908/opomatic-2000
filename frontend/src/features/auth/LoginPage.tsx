import { useState, type FormEvent } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { login, type AuthSession } from './api/authApi'
import './auth.css'

export function LoginPage({ onAuthenticated }: { onAuthenticated: (session: AuthSession) => void }) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!username.trim() || !password) {
      setError('Introduce el nombre de usuario y la contraseña.')
      return
    }

    setSubmitting(true)
    setError('')
    try {
      onAuthenticated(await login(username, password))
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 429) {
        setError('Demasiados intentos. Espera un minuto antes de volver a probar.')
      } else {
        setError('Usuario o contraseña incorrectos.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <form className="login-form" onSubmit={submit} noValidate>
        <label>
          <span className="visually-hidden">Nombre de usuario</span>
          <input
            name="username"
            autoComplete="username"
            placeholder="Nombre de usuario"
            value={username}
            onChange={(event) => setUsername(event.target.value)}
            autoFocus
          />
        </label>
        <label>
          <span className="visually-hidden">Contraseña</span>
          <input
            name="password"
            type="password"
            autoComplete="current-password"
            placeholder="Contraseña"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </label>
        {error && <p className="login-error" role="alert">{error}</p>}
        <button type="submit" disabled={submitting}>{submitting ? 'Entrando…' : 'Iniciar sesión'}</button>
      </form>
    </main>
  )
}
