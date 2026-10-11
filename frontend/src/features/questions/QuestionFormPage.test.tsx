import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { getTopics } from '../topics/api/topicsApi'
import { createQuestion, getQuestion, updateQuestion } from './api/questionsApi'
import { QuestionFormPage } from './QuestionFormPage'

vi.mock('../topics/api/topicsApi', () => ({ getTopics: vi.fn() }))
vi.mock('./api/questionsApi', () => ({ createQuestion: vi.fn(), getQuestion: vi.fn(), updateQuestion: vi.fn() }))

const getTopicsMock = vi.mocked(getTopics)
const createQuestionMock = vi.mocked(createQuestion)
const getQuestionMock = vi.mocked(getQuestion)
const updateQuestionMock = vi.mocked(updateQuestion)
const topics = [{ id: 1, name: 'Tema uno', isActive: true, activeQuestionCount: 0 }]
const detail = { id: 10, topicId: 1, statement: 'Pregunta original', isActive: true, options: Array.from({ length: 4 }, (_, index) => ({ id: index + 100, position: index + 1, text: `Opción ${index + 1}`, isCorrect: index === 1 })) }

describe('QuestionFormPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    window.history.replaceState(null, '', '/questions/new')
    getTopicsMock.mockResolvedValue(topics)
  })

  it('validates all required fields and keeps exactly one correct answer', async () => {
    render(<QuestionFormPage />)
    await screen.findByRole('option', { name: 'Tema uno' })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar pregunta' }))
    expect(await screen.findByText('Selecciona un tema.')).toBeInTheDocument()
    expect(screen.getByLabelText('Tema')).toHaveFocus()
    expect(screen.getByLabelText('Tema')).toHaveAccessibleDescription('Selecciona un tema.')
    expect(screen.getByText('El enunciado es obligatorio.')).toBeInTheDocument()
    expect(screen.getAllByText(/es obligatoria/)).toHaveLength(4)
    const radios = screen.getAllByRole('radio')
    fireEvent.click(radios[2])
    expect(radios[2]).toBeChecked()
    expect(radios[0]).not.toBeChecked()
  })

  it('creates a valid question using the selected correct option', async () => {
    createQuestionMock.mockResolvedValue(detail)
    render(<QuestionFormPage />)
    await screen.findByRole('option', { name: 'Tema uno' })
    fireEvent.change(screen.getByLabelText('Tema'), { target: { value: '1' } })
    fireEvent.change(screen.getByLabelText('Enunciado'), { target: { value: 'Nueva pregunta' } })
    for (let position = 1; position <= 4; position += 1) fireEvent.change(screen.getByLabelText(`Opción ${position}`), { target: { value: `Respuesta ${position}` } })
    fireEvent.click(screen.getByLabelText('Opción 3 correcta'))
    fireEvent.click(screen.getByRole('button', { name: 'Guardar pregunta' }))
    expect(await screen.findByRole('heading', { name: 'Pregunta guardada' })).toBeInTheDocument()
    expect(createQuestionMock).toHaveBeenCalledWith(expect.objectContaining({
      topicId: 1,
      options: expect.arrayContaining([expect.objectContaining({ position: 3, isCorrect: true })]),
    }))
  })

  it('loads and updates an existing question', async () => {
    getQuestionMock.mockResolvedValue(detail)
    updateQuestionMock.mockResolvedValue({ ...detail, statement: 'Editada' })
    render(<QuestionFormPage questionId={10} />)
    const statement = await screen.findByLabelText('Enunciado')
    expect(statement).toHaveValue('Pregunta original')
    expect(screen.getByLabelText('Opción 2 correcta')).toBeChecked()
    fireEvent.change(statement, { target: { value: 'Editada' } })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar pregunta' }))
    await waitFor(() => expect(updateQuestionMock).toHaveBeenCalledWith(10, expect.objectContaining({ statement: 'Editada' })))
  })

  it('shows backend validation beside the related control', async () => {
    createQuestionMock.mockRejectedValue(new ApiError({ title: 'Datos no válidos', status: 400, errors: { statement: ['Revisa el enunciado.'] } }))
    render(<QuestionFormPage />)
    await screen.findByRole('option', { name: 'Tema uno' })
    fireEvent.change(screen.getByLabelText('Tema'), { target: { value: '1' } })
    fireEvent.change(screen.getByLabelText('Enunciado'), { target: { value: 'Pregunta' } })
    for (let position = 1; position <= 4; position += 1) fireEvent.change(screen.getByLabelText(`Opción ${position}`), { target: { value: `Respuesta ${position}` } })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar pregunta' }))
    expect(await screen.findByText('Revisa el enunciado.')).toBeInTheDocument()
    expect(screen.getByLabelText('Enunciado')).toHaveFocus()
    expect(screen.getByLabelText('Enunciado')).toHaveAccessibleDescription('Revisa el enunciado.')
  })
})
