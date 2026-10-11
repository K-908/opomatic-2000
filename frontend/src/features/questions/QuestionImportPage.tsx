import { type ChangeEvent, useState } from 'react'
import { ApiError } from '../../shared/api/apiClient'
import {
  importQuestions,
  type ImportQuestionsDocument,
  type ImportQuestionsResult,
} from './api/questionsApi'
import './questions.css'

const maximumFileSize = 5 * 1024 * 1024

interface SelectedDocument {
  fileName: string
  fileSize: number
  topicCount: number
  questionCount: number
  document: ImportQuestionsDocument
}

export function QuestionImportPage() {
  const [selected, setSelected] = useState<SelectedDocument | null>(null)
  const [fileError, setFileError] = useState('')
  const [serverErrors, setServerErrors] = useState<Record<string, string[]>>({})
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState<ImportQuestionsResult | null>(null)

  async function selectFile(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    setSelected(null)
    setResult(null)
    setServerErrors({})
    setFileError('')
    if (!file) return
    if (!file.name.toLowerCase().endsWith('.json')) {
      setFileError('Selecciona un archivo con extensión .json.')
      return
    }
    if (file.size > maximumFileSize) {
      setFileError('El archivo JSON no puede superar los 5 MB.')
      return
    }

    try {
      const document = JSON.parse(await file.text()) as Partial<ImportQuestionsDocument>
      if (!Array.isArray(document.topics)) throw new Error('invalid structure')
      const questionCount = document.topics.reduce(
        (total, topic) => total + (Array.isArray(topic?.questions) ? topic.questions.length : 0), 0)
      setSelected({
        fileName: file.name,
        fileSize: file.size,
        topicCount: document.topics.length,
        questionCount,
        document: document as ImportQuestionsDocument,
      })
    } catch {
      setFileError('El archivo no contiene un documento JSON válido con una lista de temas.')
    }
  }

  async function submitImport() {
    if (!selected) return
    setSubmitting(true)
    setFileError('')
    setServerErrors({})
    try {
      setResult(await importQuestions(selected.document))
      setSelected(null)
    } catch (error) {
      if (error instanceof ApiError) {
        setServerErrors(error.problemDetails.errors ?? {})
        setFileError(error.message)
      } else {
        setFileError('No se ha podido completar la importación.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="questions-page">
      <header className="questions-header">
        <div><p className="eyebrow">Banco de preguntas</p><h1>Importar JSON</h1></div>
        <nav aria-label="Navegación de importación">
          <a className="back-link" href="/questions">Volver a preguntas</a>
        </nav>
      </header>

      <section className="question-panel import-panel" aria-labelledby="import-title">
        <h2 id="import-title">Selecciona el archivo</h2>
        <p>Hasta 5 MB y 2.000 preguntas. No se guardará nada hasta validar el documento completo.</p>
        <label className="file-field">Archivo JSON
          <input type="file" accept=".json,application/json" onChange={selectFile} disabled={submitting} />
        </label>
        <a href="/examples/questions-import-example.json" download>Descargar un JSON de ejemplo válido</a>

        {fileError && <p className="inline-alert" role="alert">{fileError}</p>}
        {Object.keys(serverErrors).length > 0 && (
          <div className="import-errors" aria-labelledby="import-errors-title">
            <h3 id="import-errors-title">Errores encontrados</h3>
            <ul>{Object.entries(serverErrors).flatMap(([path, messages]) =>
              messages.map((message) => <li key={`${path}-${message}`}><code>{path}</code>: {message}</li>))}</ul>
          </div>
        )}

        {selected && (
          <div className="import-summary" aria-labelledby="import-summary-title">
            <h3 id="import-summary-title">Resumen antes de importar</h3>
            <dl>
              <div><dt>Archivo</dt><dd>{selected.fileName}</dd></div>
              <div><dt>Tamaño</dt><dd>{formatBytes(selected.fileSize)}</dd></div>
              <div><dt>Temas</dt><dd>{selected.topicCount}</dd></div>
              <div><dt>Preguntas</dt><dd>{selected.questionCount}</dd></div>
            </dl>
            <button type="button" className="primary-button-link" onClick={submitImport} disabled={submitting}>
              {submitting ? 'Importando…' : 'Confirmar importación'}
            </button>
          </div>
        )}

        {result && (
          <div className="import-success" role="status">
            <h2>Importación completada</h2>
            <p>{result.importedQuestions} preguntas importadas; {result.createdTopics} temas creados y {result.reusedTopics} reutilizados.</p>
            <a className="primary-button-link" href="/questions">Ver preguntas importadas</a>
          </div>
        )}
      </section>
    </main>
  )
}

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  return `${(bytes / 1024).toFixed(1)} KB`
}
