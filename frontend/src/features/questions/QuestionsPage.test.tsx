import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getTopics } from '../topics/api/topicsApi'
import { getQuestions, setQuestionStatus } from './api/questionsApi'
import { QuestionsPage } from './QuestionsPage'

vi.mock('../topics/api/topicsApi', () => ({ getTopics: vi.fn() }))
vi.mock('./api/questionsApi', () => ({ getQuestions: vi.fn(), setQuestionStatus: vi.fn() }))

const getTopicsMock = vi.mocked(getTopics)
const getQuestionsMock = vi.mocked(getQuestions)
const setQuestionStatusMock = vi.mocked(setQuestionStatus)
const question = { id: 10, topicId: 1, topicName: 'Tema uno', statement: 'Una pregunta', isActive: true, updatedAtUtc: '2026-08-20T10:00:00Z' }
const page = (items = [question], currentPage = 1, totalPages = 1) => ({ items, page: currentPage, pageSize: 20, totalItems: items.length, totalPages })

describe('QuestionsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    window.history.replaceState(null, '', '/questions')
    getTopicsMock.mockResolvedValue([{ id: 1, name: 'Tema uno', isActive: true, activeQuestionCount: 1 }])
  })

  it('shows loading and then the empty state', async () => {
    let resolveQuestions: (value: ReturnType<typeof page>) => void = () => undefined
    getQuestionsMock.mockReturnValue(new Promise((resolve) => { resolveQuestions = resolve }))
    render(<QuestionsPage />)
    expect(screen.getByRole('status')).toHaveTextContent('Cargando preguntas')
    resolveQuestions(page([]))
    expect(await screen.findByText('No hay preguntas')).toBeInTheDocument()
  })

  it('combines search, topic and inactive filters and resets the page', async () => {
    getQuestionsMock.mockResolvedValue(page([]))
    render(<QuestionsPage />)
    await screen.findByText('No hay preguntas')
    fireEvent.change(screen.getByLabelText('Texto'), { target: { value: '  ley ' } })
    fireEvent.change(screen.getByLabelText('Tema'), { target: { value: '1' } })
    fireEvent.click(screen.getByLabelText('Mostrar inactivas'))
    fireEvent.click(screen.getByRole('button', { name: 'Buscar' }))
    await waitFor(() => expect(getQuestionsMock).toHaveBeenLastCalledWith(
      expect.objectContaining({ topicId: 1, includeInactive: true, search: 'ley', page: 1 }),
      expect.any(AbortSignal),
    ))
  })

  it('never offers an invalid next page and refreshes after changing status', async () => {
    getQuestionsMock.mockResolvedValue(page()).mockResolvedValueOnce(page())
    setQuestionStatusMock.mockResolvedValue()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    render(<QuestionsPage />)
    await screen.findByRole('heading', { name: question.statement })
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeDisabled()
    fireEvent.click(screen.getByRole('button', { name: `Desactivar ${question.statement}` }))
    await waitFor(() => expect(setQuestionStatusMock).toHaveBeenCalledWith(question.id, false))
  })

  it('shows a load error', async () => {
    getQuestionsMock.mockRejectedValue(new Error('offline'))
    render(<QuestionsPage />)
    expect(await screen.findByRole('alert')).toHaveTextContent('No se han podido cargar las preguntas')
  })
})
