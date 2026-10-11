import { type FormEvent, useEffect, useRef, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import {
  createTopic,
  getTopics,
  setTopicStatus,
  type Topic,
  updateTopic,
} from './api/topicsApi'
import './topics.css'

export function TopicsPage() {
  const [topics, setTopics] = useState<Topic[]>([])
  const [includeInactive, setIncludeInactive] = useState(false)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [loadError, setLoadError] = useState(false)
  const [operationError, setOperationError] = useState('')
  const [nameError, setNameError] = useState('')
  const [name, setName] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const nameInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setLoadError(false)

    getTopics(includeInactive, controller.signal)
      .then(setTopics)
      .catch(() => {
        if (!controller.signal.aborted) setLoadError(true)
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [includeInactive])

  useEffect(() => {
    if (nameError && !saving) {
      nameInputRef.current?.focus()
    }
  }, [nameError, saving])

  async function refreshTopics() {
    setTopics(await getTopics(includeInactive))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNameError('')
    setOperationError('')

    if (!name.trim()) {
      setNameError('El nombre del tema es obligatorio.')
      nameInputRef.current?.focus()
      return
    }

    setSaving(true)
    try {
      if (editingId === null) {
        await createTopic(name)
      } else {
        await updateTopic(editingId, name)
      }

      await refreshTopics()
      cancelEditing()
      nameInputRef.current?.focus()
    } catch (error) {
      handleApiError(error)
    } finally {
      setSaving(false)
    }
  }

  function startEditing(topic: Topic) {
    setEditingId(topic.id)
    setName(topic.name)
    setNameError('')
    setOperationError('')
    requestAnimationFrame(() => nameInputRef.current?.focus())
  }

  function cancelEditing() {
    setEditingId(null)
    setName('')
    setNameError('')
  }

  async function changeStatus(topic: Topic) {
    if (
      topic.isActive &&
      !window.confirm(
        `¿Desactivar “${topic.name}”? Sus preguntas no estarán disponibles para nuevos tests.`,
      )
    ) {
      return
    }

    setOperationError('')
    setSaving(true)
    try {
      await setTopicStatus(topic.id, !topic.isActive)
      await refreshTopics()
      if (editingId === topic.id) cancelEditing()
    } catch (error) {
      handleApiError(error)
    } finally {
      setSaving(false)
    }
  }

  function handleApiError(error: unknown) {
    if (error instanceof ApiError) {
      const fieldMessage = error.problemDetails.errors?.name?.[0]
      if (fieldMessage) {
        setNameError(fieldMessage)
        nameInputRef.current?.focus()
        return
      }
      setOperationError(error.message)
      return
    }

    setOperationError('No se ha podido completar la operación. Inténtalo de nuevo.')
  }

  return (
    <main className="topics-page">
      <header className="topics-header">
        <div>
          <p className="eyebrow">Banco de preguntas</p>
          <h1>Temas</h1>
          <p>Organiza aquí las materias que utilizarás para preparar tus tests.</p>
        </div>
      </header>

      <section className="topic-form-card" aria-labelledby="topic-form-title">
        <h2 id="topic-form-title">
          {editingId === null ? 'Crear un tema' : 'Editar el tema'}
        </h2>
        <form onSubmit={handleSubmit} noValidate>
          <label htmlFor="topic-name">Nombre</label>
          <div className="form-row">
            <input
              ref={nameInputRef}
              id="topic-name"
              name="name"
              value={name}
              maxLength={150}
              aria-invalid={Boolean(nameError)}
              aria-describedby={nameError ? 'topic-name-error' : undefined}
              onChange={(event) => setName(event.target.value)}
              disabled={saving}
            />
            <button type="submit" disabled={saving}>
              {saving ? 'Guardando…' : editingId === null ? 'Crear tema' : 'Guardar cambios'}
            </button>
            {editingId !== null && (
              <button type="button" className="button-secondary" onClick={cancelEditing} disabled={saving}>
                Cancelar
              </button>
            )}
          </div>
          {nameError && <p id="topic-name-error" className="field-error">{nameError}</p>}
        </form>
      </section>

      <section className="topic-list-card" aria-labelledby="topic-list-title">
        <div className="list-heading">
          <div>
            <h2 id="topic-list-title">Temas disponibles</h2>
            {!loading && <p>{topics.length} {topics.length === 1 ? 'tema' : 'temas'}</p>}
          </div>
          <label className="inactive-filter">
            <input
              type="checkbox"
              checked={includeInactive}
              onChange={(event) => setIncludeInactive(event.target.checked)}
            />
            Mostrar inactivos
          </label>
        </div>

        {operationError && <p className="inline-alert" role="alert">{operationError}</p>}
        {loading && <AsyncFeedback kind="loading" message="Cargando temas…" />}
        {!loading && loadError && (
          <AsyncFeedback kind="error" message="No se han podido cargar los temas. Comprueba la conexión con la API." />
        )}
        {!loading && !loadError && topics.length === 0 && (
          <div className="empty-state">
            <h3>Todavía no hay temas</h3>
            <p>Crea el primero para empezar a organizar tus preguntas.</p>
          </div>
        )}
        {!loading && !loadError && topics.length > 0 && (
          <ul className="topic-list">
            {topics.map((topic) => (
              <li key={topic.id} className={!topic.isActive ? 'topic-row topic-row--inactive' : 'topic-row'}>
                <div>
                  <div className="topic-name-line">
                    <h3>{topic.name}</h3>
                    {!topic.isActive && <span className="status-badge">Inactivo</span>}
                  </div>
                  <p>{topic.activeQuestionCount} {topic.activeQuestionCount === 1 ? 'pregunta activa' : 'preguntas activas'}</p>
                </div>
                <div className="topic-actions">
                  <button type="button" className="button-secondary" onClick={() => startEditing(topic)} disabled={saving}>
                    Editar <span className="visually-hidden">{topic.name}</span>
                  </button>
                  <button type="button" className={topic.isActive ? 'button-danger' : 'button-secondary'} onClick={() => changeStatus(topic)} disabled={saving}>
                    {topic.isActive ? 'Desactivar' : 'Reactivar'} <span className="visually-hidden">{topic.name}</span>
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </main>
  )
}
