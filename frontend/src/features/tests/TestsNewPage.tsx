import { type FormEvent, useEffect, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import { getTopics, type Topic } from '../topics/api/topicsApi'
import { generateTest, type GeneratedTest } from './api/testsApi'
import './tests.css'

interface TestsNewPageProps {
  onStarted: (test: GeneratedTest) => void
  lostProgress?: boolean
}

export function TestsNewPage({ onStarted, lostProgress = false }: TestsNewPageProps) {
  const [topics, setTopics] = useState<Topic[]>([])
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [questionCount, setQuestionCount] = useState('20')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [formError, setFormError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    getTopics(false, controller.signal)
      .then(setTopics)
      .catch(() => {
        if (!controller.signal.aborted) setLoadError('No se han podido cargar los temas disponibles.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [])

  const availableCount = topics
    .filter((topic) => selectedIds.includes(topic.id))
    .reduce((total, topic) => total + topic.activeQuestionCount, 0)

  function toggleTopic(topicId: number, checked: boolean) {
    setSelectedIds((current) => checked
      ? [...current, topicId]
      : current.filter((id) => id !== topicId))
    setFormError('')
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    setFormError('')
    const count = Number(questionCount)
    if (selectedIds.length === 0) {
      setFormError('Selecciona al menos un tema.')
      return
    }
    if (!Number.isInteger(count) || count < 1 || count > 200) {
      setFormError('El número de preguntas debe estar entre 1 y 200.')
      return
    }
    if (count > availableCount) {
      setFormError(`Has pedido ${count} preguntas, pero solo hay ${availableCount} disponibles.`)
      return
    }

    setSubmitting(true)
    try {
      onStarted(await generateTest(selectedIds, count))
    } catch (error) {
      if (error instanceof ApiError && error.problemDetails.availableCount !== undefined) {
        setFormError(`Has pedido ${error.problemDetails.requestedCount ?? count} preguntas, pero solo hay ${error.problemDetails.availableCount} disponibles.`)
      } else if (error instanceof ApiError) {
        setFormError(error.message)
      } else {
        setFormError('No se ha podido generar el test. Inténtalo de nuevo.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="tests-page">
      <header className="tests-header">
        <div><p className="eyebrow">Práctica dirigida</p><h1>Nuevo test</h1></div>
      </header>

      {lostProgress && <p className="inline-alert" role="alert">El test anterior ya no está disponible porque se recargó o abrió directamente la página.</p>}
      <form className="test-config-card" onSubmit={submit} noValidate>
        <section aria-labelledby="test-topics-title">
          <div className="test-section-heading">
            <div><h2 id="test-topics-title">1. Elige los temas</h2><p>Puedes combinar varias materias.</p></div>
            <strong>{availableCount} preguntas disponibles</strong>
          </div>
          {loading && <AsyncFeedback kind="loading" message="Cargando temas…" />}
          {!loading && loadError && <AsyncFeedback kind="error" message={loadError} />}
          {!loading && !loadError && topics.length === 0 && <div className="empty-state"><h3>No hay contenido disponible</h3><p>Crea temas y preguntas activas antes de generar un test.</p></div>}
          {!loading && !loadError && topics.length > 0 && <ul className="test-topic-list">
            {topics.map((topic) => <li key={topic.id}>
              <label>
                <input type="checkbox" checked={selectedIds.includes(topic.id)} onChange={(event) => toggleTopic(topic.id, event.target.checked)} />
                <span><strong>{topic.name}</strong><small>{topic.activeQuestionCount} {topic.activeQuestionCount === 1 ? 'pregunta' : 'preguntas'}</small></span>
              </label>
            </li>)}
          </ul>}
        </section>

        <section aria-labelledby="test-count-title">
          <h2 id="test-count-title">2. Decide la duración</h2>
          <label className="question-count-field">Número de preguntas
            <input type="number" min="1" max="200" step="1" value={questionCount} onChange={(event) => { setQuestionCount(event.target.value); setFormError('') }} />
          </label>
        </section>

        {formError && <p className="inline-alert" role="alert">{formError}</p>}
        <button type="submit" disabled={loading || submitting || topics.length === 0}>
          {submitting ? 'Preparando test…' : 'Comenzar test'}
        </button>
      </form>
    </main>
  )
}
