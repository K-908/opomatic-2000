import { useEffect, useState } from 'react'
import { getAttempts, type PagedAttempts } from './api/attemptsApi'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import './attempts.css'

const pageSize = 20

function initialPage() {
  const page = Number(new URLSearchParams(window.location.search).get('page'))
  return Number.isInteger(page) && page > 0 ? page : 1
}

export function AttemptsPage() {
  const [page, setPage] = useState(initialPage)
  const [result, setResult] = useState<PagedAttempts | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setError('')
    window.history.replaceState(null, '', page > 1 ? `/attempts?page=${page}` : '/attempts')
    getAttempts(page, pageSize, controller.signal)
      .then(setResult)
      .catch(() => {
        if (!controller.signal.aborted) setError('No se ha podido cargar el historial.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [page])

  return (
    <main className="attempts-page">
      <header className="attempts-header">
        <div><p className="eyebrow">Tu progreso</p><h1>Historial de tests</h1></div>
      </header>

      <section className="attempts-panel" aria-labelledby="attempt-list-title">
        <div className="attempt-list-heading">
          <h2 id="attempt-list-title">Intentos finalizados</h2>
          {result && !loading && <p>{result.totalItems} {result.totalItems === 1 ? 'test' : 'tests'}</p>}
        </div>
        {loading && <AsyncFeedback kind="loading" message="Cargando historial…" />}
        {!loading && error && <AsyncFeedback kind="error" message={error} />}
        {!loading && !error && result?.items.length === 0 && (
          <div className="empty-state"><h3>Todavía no has terminado ningún test</h3><p>Cuando completes uno aparecerá aquí con su nota y sus temas.</p><a className="primary-link" href="/tests/new">Crear un test</a></div>
        )}
        {!loading && !error && result && result.items.length > 0 && <>
          <ol className="attempt-list">
            {result.items.map((attempt) => (
              <li key={attempt.id}>
                <a href={`/attempts/${attempt.id}`}>
                  <div className="attempt-main">
                    <time dateTime={attempt.completedAtUtc}>{formatDate(attempt.completedAtUtc)}</time>
                    <h3>{attempt.topics.map((topic) => topic.name).join(' · ')}</h3>
                    <p>{attempt.totalQuestions} preguntas · {attempt.correctCount} aciertos · {attempt.incorrectCount} fallos · {attempt.unansweredCount} en blanco</p>
                  </div>
                  <strong className={attempt.score < 0 ? 'attempt-score attempt-score--negative' : 'attempt-score'}>{attempt.score.toFixed(2)}</strong>
                </a>
              </li>
            ))}
          </ol>
          <nav className="pagination" aria-label="Paginación del historial">
            <button type="button" disabled={result.page <= 1 || loading} onClick={() => setPage((current) => Math.max(1, current - 1))}>Anterior</button>
            <span>Página {result.page} de {Math.max(1, result.totalPages)}</span>
            <button type="button" disabled={result.page >= result.totalPages || loading} onClick={() => setPage((current) => current + 1)}>Siguiente</button>
          </nav>
        </>}
      </section>
    </main>
  )
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
