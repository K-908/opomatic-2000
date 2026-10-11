import { useEffect, useRef, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import { finishTest, type FinishedTest } from './api/testsApi'
import { useTestSession } from './useTestSession'
import './tests.css'

interface TestRunPageProps {
  onFinished: (result: FinishedTest) => void
  onAbandon: () => void
}

export function TestRunPage({ onFinished, onAbandon }: TestRunPageProps) {
  const session = useTestSession()
  const [showConfirmation, setShowConfirmation] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const dialogRef = useRef<HTMLElement>(null)
  const cancelButtonRef = useRef<HTMLButtonElement>(null)
  const returnFocusRef = useRef<HTMLElement | null>(null)
  const submittingRef = useRef(false)

  useEffect(() => {
    const warnBeforeUnload = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', warnBeforeUnload)
    return () => window.removeEventListener('beforeunload', warnBeforeUnload)
  }, [])

  useEffect(() => {
    submittingRef.current = submitting
  }, [submitting])

  useEffect(() => {
    if (!showConfirmation) return
    returnFocusRef.current = document.activeElement as HTMLElement | null
    cancelButtonRef.current?.focus()

    function handleDialogKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape' && !submittingRef.current) {
        event.preventDefault()
        setShowConfirmation(false)
        return
      }
      if (event.key !== 'Tab') return
      const focusable = dialogRef.current?.querySelectorAll<HTMLElement>(
        'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      )
      if (!focusable?.length) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', handleDialogKeyDown)
    return () => {
      document.removeEventListener('keydown', handleDialogKeyDown)
      returnFocusRef.current?.focus()
    }
  }, [showConfirmation])

  const test = session.generatedTest!
  const question = test.questions[session.currentIndex]
  const unansweredCount = test.questions.filter((item) => session.answers[item.id] === undefined).length

  async function submitTest() {
    if (submitting || !session.submissionId) return
    setSubmitting(true)
    setError('')
    try {
      const result = await finishTest(
        session.submissionId,
        test.testToken,
        test.questions.map((item) => ({
          questionId: item.id,
          selectedOptionId: session.answers[item.id] ?? null,
        })),
      )
      onFinished(result)
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 410) {
        setError('El test ha caducado y ya no se puede entregar. Tendrás que crear uno nuevo.')
      } else if (cause instanceof ApiError) {
        setError(cause.message)
      } else {
        setError('No se ha podido entregar el test. Puedes volver a intentarlo sin duplicarlo.')
      }
      setShowConfirmation(false)
    } finally {
      setSubmitting(false)
    }
  }

  function abandon() {
    if (window.confirm('¿Abandonar el test? Perderás todo el progreso.')) onAbandon()
  }

  function openConfirmation() {
    setShowConfirmation(true)
  }

  return (
    <main className="test-run-page">
      <header className="runner-header">
        <div>
          <p className="eyebrow">Test en curso</p>
          <h1>Pregunta {session.currentIndex + 1} de {test.questions.length}</h1>
        </div>
        <button className="button-secondary button-danger" type="button" onClick={abandon}>Abandonar</button>
      </header>

      <div className="runner-layout">
        <section className="question-card" aria-labelledby="current-question">
          <p className="question-topic">{question.topicName}</p>
          <fieldset>
            <legend id="current-question">{question.statement}</legend>
            <div className="answer-options">
              {question.options.map((option) => (
                <label key={option.id} className={session.answers[question.id] === option.id ? 'answer-option answer-option--selected' : 'answer-option'}>
                  <input
                    type="radio"
                    name={`question-${question.id}`}
                    checked={session.answers[question.id] === option.id}
                    onChange={() => session.selectAnswer(question.id, option.id)}
                  />
                  <span>{option.text}</span>
                </label>
              ))}
            </div>
          </fieldset>
          {session.answers[question.id] !== undefined && (
            <button className="text-button" type="button" onClick={() => session.clearAnswer(question.id)}>
              Dejar sin contestar
            </button>
          )}
          <div className="runner-actions">
            <button className="button-secondary" type="button" disabled={session.currentIndex === 0} onClick={() => session.navigateTo(session.currentIndex - 1)}>Anterior</button>
            {session.currentIndex < test.questions.length - 1 ? (
              <button type="button" onClick={() => session.navigateTo(session.currentIndex + 1)}>Siguiente</button>
            ) : (
              <button type="button" onClick={openConfirmation}>Revisar y finalizar</button>
            )}
          </div>
        </section>

        <aside className="question-map" aria-labelledby="question-map-title">
          <div><h2 id="question-map-title">Revisión</h2><p>{unansweredCount} sin contestar</p></div>
          <ol>
            {test.questions.map((item, index) => (
              <li key={item.id}>
                <button
                  type="button"
                  aria-label={`Ir a la pregunta ${index + 1}, ${session.answers[item.id] === undefined ? 'sin contestar' : 'contestada'}`}
                  aria-current={index === session.currentIndex ? 'step' : undefined}
                  className={session.answers[item.id] === undefined ? 'question-index' : 'question-index question-index--answered'}
                  onClick={() => session.navigateTo(index)}
                >{index + 1}</button>
              </li>
            ))}
          </ol>
          <button type="button" className="finish-wide" onClick={openConfirmation}>Finalizar test</button>
        </aside>
      </div>

      {error && <p className="inline-alert runner-error" role="alert">{error}</p>}
      {showConfirmation && (
        <div className="dialog-backdrop" role="presentation">
          <section ref={dialogRef} className="confirmation-dialog" role="dialog" aria-modal="true" aria-labelledby="finish-title" aria-describedby="finish-description">
            <h2 id="finish-title">¿Entregar el test?</h2>
            <div id="finish-description"><p>{unansweredCount === 0 ? 'Has contestado todas las preguntas.' : `Dejarás ${unansweredCount} ${unansweredCount === 1 ? 'pregunta' : 'preguntas'} sin contestar.`}</p>
            <p>Después de entregarlo ya no podrás cambiar las respuestas.</p>
            </div>
            <div className="runner-actions">
              <button ref={cancelButtonRef} className="button-secondary" type="button" disabled={submitting} onClick={() => setShowConfirmation(false)}>Seguir revisando</button>
              <button type="button" disabled={submitting} onClick={submitTest}>{submitting ? 'Entregando…' : 'Confirmar entrega'}</button>
            </div>
          </section>
        </div>
      )}
    </main>
  )
}
