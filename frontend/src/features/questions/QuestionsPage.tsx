import { type FormEvent, useEffect, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import { getTopics, type Topic } from '../topics/api/topicsApi'
import {
  getQuestions,
  setQuestionStatus,
  type PagedQuestions,
  type QuestionFilters,
  type QuestionListItem,
} from './api/questionsApi'
import './questions.css'

const pageSize = 20

function readFilters(): QuestionFilters {
  const query = new URLSearchParams(window.location.search)
  const page = Number(query.get('page'))
  const topicId = Number(query.get('topicId'))
  return {
    topicId: topicId > 0 ? topicId : undefined,
    includeInactive: query.get('includeInactive') === 'true',
    search: query.get('search') ?? '',
    page: page > 0 ? page : 1,
    pageSize,
  }
}

export function QuestionsPage() {
  const [filters, setFilters] = useState(readFilters)
  const [searchInput, setSearchInput] = useState(filters.search)
  const [topics, setTopics] = useState<Topic[]>([])
  const [result, setResult] = useState<PagedQuestions | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [operationError, setOperationError] = useState('')
  const [changingId, setChangingId] = useState<number | null>(null)
  const [operationMessage, setOperationMessage] = useState('')

  useEffect(() => {
    getTopics(true).then(setTopics).catch(() => setLoadError('No se han podido cargar los temas.'))
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setLoadError('')
    const query = new URLSearchParams()
    if (filters.topicId) query.set('topicId', String(filters.topicId))
    if (filters.search) query.set('search', filters.search)
    if (filters.includeInactive) query.set('includeInactive', 'true')
    if (filters.page > 1) query.set('page', String(filters.page))
    window.history.replaceState(null, '', `/questions${query.size ? `?${query}` : ''}`)

    getQuestions(filters, controller.signal)
      .then(setResult)
      .catch(() => {
        if (!controller.signal.aborted) setLoadError('No se han podido cargar las preguntas.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [filters])

  function submitSearch(event: FormEvent) {
    event.preventDefault()
    setFilters((current) => ({ ...current, search: searchInput.trim(), page: 1 }))
  }

  async function changeStatus(question: QuestionListItem) {
    if (question.isActive && !window.confirm('¿Desactivar esta pregunta?')) return
    setOperationError('')
    setOperationMessage('')
    setChangingId(question.id)
    try {
      await setQuestionStatus(question.id, !question.isActive)
      const refreshed = await getQuestions(filters)
      if (refreshed.items.length === 0 && filters.page > 1) {
        setFilters((current) => ({ ...current, page: current.page - 1 }))
      } else {
        setResult(refreshed)
      }
      setOperationMessage(question.isActive ? 'Pregunta desactivada.' : 'Pregunta reactivada.')
    } catch (error) {
      setOperationError(error instanceof ApiError ? error.message : 'No se ha podido cambiar el estado.')
    } finally {
      setChangingId(null)
    }
  }

  const returnPath = `${window.location.pathname}${window.location.search}`

  return (
    <main className="questions-page">
      <header className="questions-header">
        <div><p className="eyebrow">Banco de preguntas</p><h1>Preguntas</h1></div>
        <nav aria-label="Navegación de preguntas">
          <a className="back-link" href="/topics">Temas</a>
          <a className="button-secondary" href="/questions/import">Importar JSON</a>
          <a className="primary-button-link" href={`/questions/new?return=${encodeURIComponent(returnPath)}`}>Nueva pregunta</a>
        </nav>
      </header>

      <section className="question-panel" aria-labelledby="question-filters-title">
        <h2 id="question-filters-title">Buscar y filtrar</h2>
        <form className="question-filters" onSubmit={submitSearch}>
          <label>Texto
            <input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} />
          </label>
          <label>Tema
            <select value={filters.topicId ?? ''} onChange={(event) => setFilters((current) => ({ ...current, topicId: Number(event.target.value) || undefined, page: 1 }))}>
              <option value="">Todos los temas</option>
              {topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}{!topic.isActive ? ' (inactivo)' : ''}</option>)}
            </select>
          </label>
          <label className="checkbox-label"><input type="checkbox" checked={filters.includeInactive} onChange={(event) => setFilters((current) => ({ ...current, includeInactive: event.target.checked, page: 1 }))} />Mostrar inactivas</label>
          <button type="submit">Buscar</button>
        </form>
      </section>

      <section className="question-panel" aria-labelledby="question-list-title">
        <div className="list-heading"><h2 id="question-list-title">Banco actual</h2>{result && !loading && <p>{result.totalItems} preguntas</p>}</div>
        {operationError && <p className="inline-alert" role="alert">{operationError}</p>}
        {operationMessage && <p role="status">{operationMessage}</p>}
        {loading && <AsyncFeedback kind="loading" message="Cargando preguntas…" />}
        {!loading && loadError && <AsyncFeedback kind="error" message={loadError} />}
        {!loading && !loadError && result?.items.length === 0 && <div className="empty-state"><h3>No hay preguntas</h3><p>Prueba otros filtros o crea una pregunta nueva.</p></div>}
        {!loading && !loadError && result && result.items.length > 0 && <>
          <ul className="question-list">
            {result.items.map((question) => <li key={question.id} className={!question.isActive ? 'question-row question-row--inactive' : 'question-row'}>
              <div><p className="question-topic">{question.topicName}</p><h3>{question.statement}</h3>{!question.isActive && <span className="status-badge">Inactiva</span>}</div>
              <div className="question-actions">
                <a className="button-secondary" href={`/questions/${question.id}/edit?return=${encodeURIComponent(returnPath)}`}>Editar <span className="visually-hidden">{question.statement}</span></a>
                <button type="button" className={question.isActive ? 'button-danger' : 'button-secondary'} onClick={() => changeStatus(question)} disabled={changingId !== null}>{changingId === question.id ? 'Actualizando…' : question.isActive ? 'Desactivar' : 'Reactivar'} <span className="visually-hidden">{question.statement}</span></button>
              </div>
            </li>)}
          </ul>
          <nav className="pagination" aria-label="Paginación">
            <button type="button" disabled={result.page <= 1 || loading} onClick={() => setFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))}>Anterior</button>
            <span>Página {result.page} de {Math.max(1, result.totalPages)}</span>
            <button type="button" disabled={result.page >= result.totalPages || loading} onClick={() => setFilters((current) => ({ ...current, page: current.page + 1 }))}>Siguiente</button>
          </nav>
        </>}
      </section>
    </main>
  )
}
