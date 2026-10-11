import { useEffect, useMemo, useState } from 'react'
import { getTopicStatistics, type TopicStatistics } from './api/statisticsApi'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import './statistics.css'

type SortDirection = 'best' | 'worst'

export function StatisticsPage() {
  const [topics, setTopics] = useState<TopicStatistics[] | null>(null)
  const [error, setError] = useState('')
  const [sortDirection, setSortDirection] = useState<SortDirection>('best')

  useEffect(() => {
    const controller = new AbortController()
    getTopicStatistics(controller.signal)
      .then(setTopics)
      .catch(() => {
        if (!controller.signal.aborted) setError('No se han podido cargar las estadísticas por tema.')
      })
    return () => controller.abort()
  }, [])

  const sortedTopics = useMemo(() => {
    if (!topics) return []
    const ranked = topics.filter((topic) => topic.rankingPosition !== null)
      .sort((left, right) => sortDirection === 'best'
        ? left.rankingPosition! - right.rankingPosition!
        : right.rankingPosition! - left.rankingPosition!)
    const withoutData = topics.filter((topic) => topic.rankingPosition === null)
      .sort((left, right) => left.topicName.localeCompare(right.topicName))
    return [...ranked, ...withoutData]
  }, [sortDirection, topics])

  return (
    <main className="statistics-page">
      <header className="statistics-header">
        <div><p className="eyebrow">Tu progreso</p><h1>Estadísticas por tema</h1><p>Compara resultados sin perder de vista cuántas preguntas has contestado.</p></div>
      </header>

      {topics === null && !error && <AsyncFeedback kind="loading" message="Calculando estadísticas…" />}
      {error && <AsyncFeedback kind="error" message={error} />}
      {topics?.length === 0 && <section className="statistics-empty"><h2>Todavía no hay resultados por tema</h2><p>Completa un test para empezar a comparar tu rendimiento.</p><a className="quick-action quick-action--primary" href="/tests/new">Crear un test</a></section>}
      {topics && topics.length > 0 && (
        <section className="statistics-panel" aria-labelledby="topic-ranking-title">
          <div className="statistics-toolbar">
            <div><h2 id="topic-ranking-title">Ranking de temas</h2><p>“Muestra pequeña” indica menos de 10 respuestas.</p></div>
            <label>Orden
              <select value={sortDirection} onChange={(event) => setSortDirection(event.target.value as SortDirection)}>
                <option value="best">Mejor resultado primero</option>
                <option value="worst">Peor resultado primero</option>
              </select>
            </label>
          </div>
          <div className="statistics-table-wrap">
            <table>
              <caption className="visually-hidden">Rendimiento acumulado por tema</caption>
              <thead><tr><th scope="col">Puesto</th><th scope="col">Tema</th><th scope="col">Contestadas</th><th scope="col">Aciertos</th><th scope="col">Fallos</th><th scope="col">En blanco</th><th scope="col">Precisión</th></tr></thead>
              <tbody>{sortedTopics.map((topic) => <TopicRow key={topic.topicId} topic={topic} />)}</tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  )
}

function TopicRow({ topic }: { topic: TopicStatistics }) {
  const hasSmallSample = topic.answeredQuestions > 0 && topic.answeredQuestions < 10
  return (
    <tr>
      <td data-label="Puesto">{topic.rankingPosition === null ? '—' : `#${topic.rankingPosition}`}</td>
      <th scope="row" data-label="Tema"><span>{topic.topicName}</span>{hasSmallSample && <small>Muestra pequeña</small>}</th>
      <td data-label="Contestadas">{topic.answeredQuestions}</td>
      <td data-label="Aciertos">{topic.correctCount}</td>
      <td data-label="Fallos">{topic.incorrectCount}</td>
      <td data-label="En blanco">{topic.unansweredCount}</td>
      <td data-label="Precisión">{topic.accuracyPercentage === null ? 'Sin datos' : `${topic.accuracyPercentage.toFixed(2)} %`}</td>
    </tr>
  )
}
