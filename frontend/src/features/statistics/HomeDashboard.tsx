import { useEffect, useState } from 'react'
import { getAttempts, type AttemptListItem } from '../attempts/api/attemptsApi'
import { getHealth, type HealthResponse } from '../health/api/getHealth'
import { getStatisticsSummary, type StatisticsSummary } from './api/statisticsApi'
import './statistics.css'

type DashboardState =
  | { status: 'loading' }
  | { status: 'error' }
  | { status: 'ready'; summary: StatisticsSummary; recent: AttemptListItem[] }

type HealthState =
  | { status: 'loading' }
  | { status: 'available'; data: HealthResponse }
  | { status: 'unavailable' }

export function HomeDashboard() {
  const [dashboard, setDashboard] = useState<DashboardState>({ status: 'loading' })
  const [health, setHealth] = useState<HealthState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()

    Promise.all([
      getStatisticsSummary(controller.signal),
      getAttempts(1, 3, controller.signal),
    ])
      .then(([summary, attempts]) => {
        if (!controller.signal.aborted) {
          setDashboard({ status: 'ready', summary, recent: attempts.items })
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setDashboard({ status: 'error' })
      })

    getHealth(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setHealth({ status: 'available', data })
      })
      .catch(() => {
        if (!controller.signal.aborted) setHealth({ status: 'unavailable' })
      })

    return () => controller.abort()
  }, [])

  return (
    <main className="dashboard-page">
      <header className="dashboard-header">
        <div>
          <p className="eyebrow">OpoMatic-2000</p>
          <h1>Tu preparación, pregunta a pregunta.</h1>
          <p>Convierte cada test en una señal clara de qué estudiar después.</p>
        </div>
        <HealthStatus health={health} />
      </header>

      <nav className="quick-actions" aria-label="Acciones principales">
        <a className="quick-action quick-action--primary" href="/tests/new">Crear un test</a>
        <a className="quick-action" href="/questions">Preguntas</a>
        <a className="quick-action" href="/attempts">Historial</a>
        <a className="quick-action" href="/statistics">Estadísticas por tema</a>
        <a className="quick-action" href="/accesses">Accesos recientes</a>
      </nav>

      {dashboard.status === 'loading' && <p className="dashboard-feedback" role="status">Cargando tu progreso…</p>}
      {dashboard.status === 'error' && <p className="inline-alert dashboard-feedback" role="alert">No se han podido cargar tus estadísticas. Puedes seguir creando tests y volver a intentarlo más tarde.</p>}
      {dashboard.status === 'ready' && (
        <DashboardContent summary={dashboard.summary} recent={dashboard.recent} />
      )}
    </main>
  )
}

function DashboardContent({ summary, recent }: { summary: StatisticsSummary; recent: AttemptListItem[] }) {
  return (
    <>
      <section aria-labelledby="summary-title">
        <div className="section-heading"><h2 id="summary-title">Resumen</h2><a href="/statistics">Ver detalle</a></div>
        <dl className="stat-cards">
          <div><dt>Tests terminados</dt><dd>{summary.completedTests}</dd></div>
          <div><dt>Preguntas contestadas</dt><dd>{summary.answeredQuestions}</dd></div>
          <div><dt>Precisión global</dt><dd>{formatAccuracy(summary.accuracyPercentage)}</dd></div>
        </dl>
        <p className="answer-breakdown">{summary.correctCount} aciertos · {summary.incorrectCount} fallos · {summary.unansweredCount} en blanco</p>
      </section>

      {summary.completedTests === 0 ? (
        <section className="dashboard-empty" aria-labelledby="first-test-title">
          <h2 id="first-test-title">Empieza con tu primer test</h2>
          <p>Elige uno o varios temas y, cuando termines, aquí verás cómo evoluciona tu preparación.</p>
          <a className="quick-action quick-action--primary" href="/tests/new">Crear mi primer test</a>
        </section>
      ) : (
        <section className="recent-activity" aria-labelledby="recent-title">
          <div className="section-heading"><h2 id="recent-title">Actividad reciente</h2><a href="/attempts">Ver todo</a></div>
          <ol>
            {recent.map((attempt) => (
              <li key={attempt.id}>
                <a href={`/attempts/${attempt.id}`}>
                  <span><strong>{attempt.topics.map((topic) => topic.name).join(' · ')}</strong><time dateTime={attempt.completedAtUtc}>{formatDate(attempt.completedAtUtc)}</time></span>
                  <span className="recent-score">{attempt.score.toFixed(2)}</span>
                </a>
              </li>
            ))}
          </ol>
        </section>
      )}
    </>
  )
}

function HealthStatus({ health }: { health: HealthState }) {
  if (health.status === 'unavailable') {
    return <p className="project-status project-status--error" role="alert"><span className="status-dot" aria-hidden="true" />No se puede conectar con la API. Comprueba que el backend esté ejecutándose.</p>
  }
  if (health.status === 'available') {
    return <p className="project-status project-status--available" role="status"><span className="status-dot" aria-hidden="true" />API disponible: {health.data.application}</p>
  }
  return <p className="project-status project-status--loading" role="status"><span className="status-dot" aria-hidden="true" />Comprobando la conexión con la API…</p>
}

function formatAccuracy(value: number | null) {
  return value === null ? 'Sin datos' : `${value.toFixed(2)} %`
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}
