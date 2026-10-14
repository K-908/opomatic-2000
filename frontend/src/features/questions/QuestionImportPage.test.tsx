import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { importQuestions } from './api/questionsApi'
import { QuestionImportPage } from './QuestionImportPage'

vi.mock('./api/questionsApi', () => ({ importQuestions: vi.fn() }))

const importQuestionsMock = vi.mocked(importQuestions)
const validJson = JSON.stringify({
  topics: [
    { name: 'Tema uno', questions: [{ statement: 'Pregunta', options: ['1', '2', '3', '4'], correctOption: 2 }] },
    { name: 'Tema dos', questions: [{ statement: 'Otra', options: ['1', '2', '3', '4'], correctOption: 1 }] },
  ],
})

describe('QuestionImportPage', () => {
  beforeEach(() => vi.resetAllMocks())

  it('previews and confirms a valid file before importing', async () => {
    importQuestionsMock.mockResolvedValue({ createdTopics: 1, reusedTopics: 1, importedQuestions: 2 })
    render(<QuestionImportPage />)

    fireEvent.change(screen.getByLabelText('Archivo JSON'), {
      target: { files: [jsonFile('preguntas.json', validJson)] },
    })

    expect(await screen.findByText('Resumen antes de importar')).toBeInTheDocument()
    expect(screen.getAllByText('2', { selector: 'dd' })).toHaveLength(2)
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar importación' }))

    expect(await screen.findByRole('status')).toHaveTextContent('2 preguntas importadas')
    expect(importQuestionsMock).toHaveBeenCalledWith(JSON.parse(validJson))
    expect(screen.getByRole('link', { name: 'Ver preguntas importadas' })).toHaveAttribute('href', '/questions')
  })

  it('rejects an oversized file before reading or sending it', async () => {
    render(<QuestionImportPage />)
    const file = jsonFile('grande.json', validJson)
    Object.defineProperty(file, 'size', { value: 5 * 1024 * 1024 + 1 })

    fireEvent.change(screen.getByLabelText('Archivo JSON'), { target: { files: [file] } })

    expect(await screen.findByRole('alert')).toHaveTextContent('no puede superar los 5 MB')
    expect(importQuestionsMock).not.toHaveBeenCalled()
  })

  it('presents every backend error with its exact path', async () => {
    importQuestionsMock.mockRejectedValue(new ApiError({
      title: 'Datos no válidos', status: 400, detail: 'Corrige los campos indicados.', errors: {
        'topics[0].questions[0].options': ['Debe contener tres o cuatro opciones.'],
        'topics[1].name': ['El tema está repetido.'],
      },
    }))
    render(<QuestionImportPage />)
    fireEvent.change(screen.getByLabelText('Archivo JSON'), {
      target: { files: [jsonFile('preguntas.json', validJson)] },
    })
    await screen.findByText('Resumen antes de importar')
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar importación' }))

    await waitFor(() => expect(screen.getByText(/topics\[0\]\.questions\[0\]\.options/)).toBeInTheDocument())
    expect(screen.getByText(/topics\[1\]\.name/)).toBeInTheDocument()
    expect(screen.queryByText('Importación completada')).not.toBeInTheDocument()
  })

  it('offers the published example', () => {
    render(<QuestionImportPage />)
    expect(screen.getByRole('link', { name: 'Descargar un JSON de ejemplo válido' }))
      .toHaveAttribute('href', '/examples/questions-import-example.json')
  })
})

function jsonFile(name: string, contents: string) {
  const file = new File([contents], name, { type: 'application/json' })
  Object.defineProperty(file, 'text', { value: vi.fn().mockResolvedValue(contents) })
  return file
}
