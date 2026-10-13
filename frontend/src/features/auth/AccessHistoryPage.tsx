import { useEffect, useState } from 'react'
import { getLoginAccesses, type LoginAccess } from './api/authApi'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import './auth.css'

type AccessState =
  | { status: 'loading' }
  | { status: 'error' }
  | { status: 'ready'; accesses: LoginAccess[] }

export function AccessHistoryPage() {
  const [state, setState] = useState<AccessState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    getLoginAccesses(controller.signal)
      .then((accesses) => setState({ status: 'ready', accesses }))
      .catch(() => {
        if (!controller.signal.aborted) setState({ status: 'error' })
      })
    return () => controller.abort()
  }, [])

  return (
    <main className="access-page">
      <header className="access-header">
        <div><p className="eyebrow">Seguridad de la cuenta</p><h1>Accesos recientes</h1></div>
      </header>
      {state.status === 'loading' && <AsyncFeedback kind="loading" message="Cargando accesos…" />}
      {state.status === 'error' && <AsyncFeedback kind="error" message="No se han podido cargar los accesos." />}
      {state.status === 'ready' && (
        <section className="access-panel">
          <ul className="access-list">
            {state.accesses.map((access, index) => (
              <li key={`${access.username}-${access.accessedAtUtc}-${access.ipAddress}-${index}`}>
                <time dateTime={access.accessedAtUtc}>{formatDate(access.accessedAtUtc)}</time>
                <strong>{access.username}</strong>
                <code>{access.ipAddress}</code>
              </li>
            ))}
          </ul>
        </section>
      )}
    </main>
  )
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
