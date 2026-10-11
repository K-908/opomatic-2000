import { useEffect, useState } from 'react'
import { getAttempt, type AttemptDetails, type AttemptQuestionResult } from './api/attemptsApi'
import { AsyncFeedback } from '../../shared/components/AsyncFeedback'
import './attempts.css'

export function AttemptDetailPage({ attemptId }: { attemptId: number }) {
  const [attempt, setAttempt] = useState<AttemptDetails | null>(null)
  const [currentIndex, setCurrentIndex] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setError('')
    getAttempt(attemptId, controller.signal)
      .then(setAttempt)
      .catch(() => {
        if (!controller.signal.aborted) setError('No se ha podido cargar este intento.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [attemptId])

  if (loading) return <main className="attempt-detail-page"><AsyncFeedback kind="loading" message="Cargando revisión…" /></main>
  if (error || !attempt) return <main className="attempt-detail-page"><AsyncFeedback kind="error" message={error || 'No se ha encontrado este intento.'} /><a className="back-link" href="/attempts">Volver al historial</a></main>

  const question = attempt.questions[currentIndex]
  return (
    <main className="attempt-detail-page">
      <header className="attempts-header">
        <div><p className="eyebrow">Revisión del intento</p><h1>Nota {attempt.score.toFixed(2)}</h1></div>
        <a className="back-link" href="/attempts">Volver al historial</a>
      </header>
      <dl className="attempt-summary">
        <div><dt>Aciertos</dt><dd>{attempt.correctCount}</dd></div>
        <div><dt>Fallos</dt><dd>{attempt.incorrectCount}</dd></div>
        <div><dt>Sin contestar</dt><dd>{attempt.unansweredCount}</dd></div>
        <div><dt>Total</dt><dd>{attempt.totalQuestions}</dd></div>
      </dl>

      <div className="review-layout">
        <article className="review-question" aria-labelledby="review-question-title">
          <div className="review-question-heading">
            <div><p className="question-topic">{question.topicName}</p><p>Pregunta {currentIndex + 1} de {attempt.questions.length}</p></div>
            <ResultBadge result={question.result} />
          </div>
          <h2 id="review-question-title">{question.statement}</h2>
          <ol className="review-options">
            {question.options.map((option) => (
              <li key={option.displayOrder} className={option.isCorrect ? 'review-option review-option--correct' : option.isSelected ? 'review-option review-option--selected-wrong' : 'review-option'}>
                <span>{option.text}</span>
                <span className="option-labels">
                  {option.isSelected && <strong>Tu respuesta</strong>}
                  {option.isCorrect && <strong>Respuesta correcta</strong>}
                </span>
              </li>
            ))}
          </ol>
          {question.result === 'unanswered' && <p className="unanswered-note">No seleccionaste ninguna respuesta.</p>}
          <div className="runner-actions">
            <button className="button-secondary" type="button" disabled={currentIndex === 0} onClick={() => setCurrentIndex((current) => current - 1)}>Anterior</button>
            <button type="button" disabled={currentIndex === attempt.questions.length - 1} onClick={() => setCurrentIndex((current) => current + 1)}>Siguiente</button>
          </div>
        </article>

        <nav className="review-map" aria-label="Preguntas del intento">
          <ol>{attempt.questions.map((item, index) => <li key={item.displayOrder}>
            <button type="button" aria-current={index === currentIndex ? 'step' : undefined} aria-label={`Pregunta ${index + 1}: ${resultText(item.result)}`} data-result={item.result} onClick={() => setCurrentIndex(index)}>{index + 1}</button>
          </li>)}</ol>
        </nav>
      </div>
    </main>
  )
}

function ResultBadge({ result }: { result: AttemptQuestionResult }) {
  const icon = result === 'correct' ? '✓' : result === 'incorrect' ? '✕' : '—'
  return <p className="result-badge" data-result={result}><span aria-hidden="true">{icon}</span>{resultText(result)}</p>
}

function resultText(result: AttemptQuestionResult) {
  if (result === 'correct') return 'Acertada'
  if (result === 'incorrect') return 'Fallada'
  return 'Sin contestar'
}
