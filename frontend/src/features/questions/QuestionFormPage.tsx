import { type FormEvent, useEffect, useRef, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { getTopics, type Topic } from '../topics/api/topicsApi'
import { createQuestion, getQuestion, updateQuestion, type SaveQuestionInput } from './api/questionsApi'
import './questions.css'

interface Props { questionId?: number }
const emptyOptions = () => Array.from({ length: 4 }, (_, index) => ({ position: index + 1, text: '' }))

export function QuestionFormPage({ questionId }: Props) {
  const [topics, setTopics] = useState<Topic[]>([])
  const [topicId, setTopicId] = useState('')
  const [statement, setStatement] = useState('')
  const [options, setOptions] = useState(emptyOptions)
  const [correctPosition, setCorrectPosition] = useState(1)
  const [loading, setLoading] = useState(questionId !== undefined)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [generalError, setGeneralError] = useState('')
  const [saved, setSaved] = useState(false)
  const statementRef = useRef<HTMLTextAreaElement>(null)
  const formRef = useRef<HTMLFormElement>(null)
  const savedHeadingRef = useRef<HTMLHeadingElement>(null)
  const query = new URLSearchParams(window.location.search)
  const requestedReturn = query.get('return')
  const returnTo = requestedReturn?.startsWith('/questions') ? requestedReturn : '/questions'

  useEffect(() => {
    const controller = new AbortController()
    Promise.all([
      getTopics(false, controller.signal),
      questionId === undefined ? Promise.resolve(null) : getQuestion(questionId, controller.signal),
    ]).then(([availableTopics, question]) => {
      setTopics(availableTopics)
      if (question) {
        setTopicId(String(question.topicId))
        setStatement(question.statement)
        setOptions(question.options.sort((a, b) => a.position - b.position).map((option) => ({ position: option.position, text: option.text })))
        setCorrectPosition(question.options.find((option) => option.isCorrect)?.position ?? 1)
      }
    }).catch(() => setGeneralError('No se han podido cargar los datos del formulario.'))
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [questionId])

  useEffect(() => {
    if (Object.keys(errors).length > 0) {
      formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
    }
  }, [errors])

  useEffect(() => {
    if (saved) savedHeadingRef.current?.focus()
  }, [saved])

  async function submit(event: FormEvent) {
    event.preventDefault()
    const clientErrors: Record<string, string> = {}
    if (!topicId) clientErrors.topicId = 'Selecciona un tema.'
    if (!statement.trim()) clientErrors.statement = 'El enunciado es obligatorio.'
    options.forEach((option) => { if (!option.text.trim()) clientErrors[`options[${option.position}].text`] = `La opción ${option.position} es obligatoria.` })
    setErrors(clientErrors)
    setGeneralError('')
    if (Object.keys(clientErrors).length > 0) return

    const input: SaveQuestionInput = {
      topicId: Number(topicId), statement: statement.trim(),
      options: options.map((option) => ({ ...option, text: option.text.trim(), isCorrect: option.position === correctPosition })),
    }
    setSaving(true)
    try {
      if (questionId === undefined) await createQuestion(input)
      else await updateQuestion(questionId, input)
      setSaved(true)
    } catch (error) {
      if (error instanceof ApiError && error.problemDetails.errors) {
        setErrors(Object.fromEntries(Object.entries(error.problemDetails.errors).map(([key, messages]) => [key, messages[0]])))
      } else setGeneralError(error instanceof ApiError ? error.message : 'No se ha podido guardar la pregunta.')
    } finally { setSaving(false) }
  }

  if (loading) return <main className="questions-page"><p role="status">Cargando pregunta…</p></main>
  if (saved) return <main className="questions-page"><section className="question-panel saved-state"><h1 ref={savedHeadingRef} tabIndex={-1}>Pregunta guardada</h1><p>Los cambios se han guardado correctamente.</p><a className="primary-button-link" href={returnTo}>Volver al banco</a></section></main>

  return <main className="questions-page">
    <header className="questions-header"><div><p className="eyebrow">Banco de preguntas</p><h1>{questionId === undefined ? 'Nueva pregunta' : 'Editar pregunta'}</h1></div><a className="back-link" href={returnTo}>Cancelar</a></header>
    <section className="question-panel">
      {generalError && <p className="inline-alert" role="alert">{generalError}</p>}
      <form ref={formRef} className="question-form" onSubmit={submit} noValidate>
        <label htmlFor="question-topic">Tema</label>
        <select id="question-topic" value={topicId} onChange={(event) => setTopicId(event.target.value)} aria-invalid={Boolean(errors.topicId)} aria-describedby={errors.topicId ? 'question-topic-error' : undefined} disabled={saving}>
          <option value="">Selecciona un tema</option>{topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}</option>)}
        </select>{errors.topicId && <p id="question-topic-error" className="field-error">{errors.topicId}</p>}
        <label htmlFor="question-statement">Enunciado</label>
        <textarea ref={statementRef} id="question-statement" value={statement} maxLength={10000} onChange={(event) => setStatement(event.target.value)} aria-invalid={Boolean(errors.statement)} aria-describedby={errors.statement ? 'question-statement-error' : undefined} disabled={saving} />
        {errors.statement && <p id="question-statement-error" className="field-error">{errors.statement}</p>}
        <fieldset><legend>Opciones y respuesta correcta</legend><p>Marca el botón de la única opción correcta.</p>
          {options.map((option, index) => <div className="option-row" key={option.position}>
            <input type="radio" name="correctOption" aria-label={`Opción ${option.position} correcta`} checked={correctPosition === option.position} onChange={() => setCorrectPosition(option.position)} disabled={saving} />
            <label htmlFor={`option-${option.position}`}>Opción {option.position}</label>
            <input id={`option-${option.position}`} value={option.text} maxLength={1000} onChange={(event) => setOptions((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, text: event.target.value } : item))} aria-invalid={Boolean(errors[`options[${option.position}].text`])} aria-describedby={errors[`options[${option.position}].text`] ? `option-${option.position}-error` : undefined} disabled={saving} />
            {errors[`options[${option.position}].text`] && <p id={`option-${option.position}-error`} className="field-error">{errors[`options[${option.position}].text`]}</p>}
          </div>)}
        </fieldset>
        {errors.options && <p className="field-error">{errors.options}</p>}{errors.correctOption && <p className="field-error">{errors.correctOption}</p>}
        <button type="submit" disabled={saving}>{saving ? 'Guardando…' : 'Guardar pregunta'}</button>
      </form>
    </section>
  </main>
}
