import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { getTopics } from '../topics/api/topicsApi'
import { generateTest } from './api/testsApi'
import { TestsNewPage } from './TestsNewPage'

vi.mock('../topics/api/topicsApi', () => ({ getTopics: vi.fn() }))
vi.mock('./api/testsApi', () => ({ generateTest: vi.fn() }))

const getTopicsMock = vi.mocked(getTopics)
const generateTestMock = vi.mocked(generateTest)
const topics = [
  { id: 1, name: 'Tema uno', isActive: true, activeQuestionCount: 12 },
  { id: 2, name: 'Tema dos', isActive: true, activeQuestionCount: 8 },
]
const generatedTest = {
  testToken: 'opaque',
  expiresAtUtc: '2026-08-21T00:00:00Z',
  topics: [{ id: 1, name: 'Tema uno' }],
  questions: [{
    id: 10,
    displayOrder: 1,
    topicId: 1,
    topicName: 'Tema uno',
    statement: 'Pregunta',
    options: [1, 2, 3, 4].map((id) => ({ id, displayOrder: id, text: `Opción ${id}` })),
  }],
}

describe('TestsNewPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    getTopicsMock.mockResolvedValue(topics)
  })

  it('loads topics and updates combined availability', async () => {
    render(<TestsNewPage onStarted={vi.fn()} />)
    expect(screen.getByRole('status')).toHaveTextContent('Cargando temas')
    fireEvent.click(await screen.findByLabelText(/Tema uno/))
    expect(screen.getByText('12 preguntas disponibles')).toBeInTheDocument()
    fireEvent.click(screen.getByLabelText(/Tema dos/))
    expect(screen.getByText('20 preguntas disponibles')).toBeInTheDocument()
  })

  it('does not start without topics or with an invalid quantity', async () => {
    render(<TestsNewPage onStarted={vi.fn()} />)
    await screen.findByLabelText(/Tema uno/)
    fireEvent.click(screen.getByRole('button', { name: 'Comenzar test' }))
    expect(screen.getByRole('alert')).toHaveTextContent('Selecciona al menos un tema')
    fireEvent.click(screen.getByLabelText(/Tema uno/))
    fireEvent.change(screen.getByLabelText('Número de preguntas'), { target: { value: '0' } })
    fireEvent.click(screen.getByRole('button', { name: 'Comenzar test' }))
    expect(screen.getByRole('alert')).toHaveTextContent('entre 1 y 200')
    expect(generateTestMock).not.toHaveBeenCalled()
  })

  it('explains insufficient availability before calling the backend', async () => {
    render(<TestsNewPage onStarted={vi.fn()} />)
    fireEvent.click(await screen.findByLabelText(/Tema dos/))
    fireEvent.change(screen.getByLabelText('Número de preguntas'), { target: { value: '9' } })
    fireEvent.click(screen.getByRole('button', { name: 'Comenzar test' }))
    expect(screen.getByRole('alert')).toHaveTextContent('solo hay 8 disponibles')
    expect(generateTestMock).not.toHaveBeenCalled()
  })

  it('stores the generated test through onStarted', async () => {
    generateTestMock.mockResolvedValue(generatedTest)
    const onStarted = vi.fn()
    render(<TestsNewPage onStarted={onStarted} />)
    fireEvent.click(await screen.findByLabelText(/Tema uno/))
    fireEvent.change(screen.getByLabelText('Número de preguntas'), { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Comenzar test' }))
    await waitFor(() => expect(generateTestMock).toHaveBeenCalledWith([1], 1))
    expect(onStarted).toHaveBeenCalledWith(generatedTest)
  })

  it('translates the backend insufficient error into a useful message', async () => {
    generateTestMock.mockRejectedValue(new ApiError({
      title: 'No hay suficientes preguntas',
      detail: 'Insuficientes.',
      status: 409,
      requestedCount: 10,
      availableCount: 7,
    }))
    render(<TestsNewPage onStarted={vi.fn()} />)
    fireEvent.click(await screen.findByLabelText(/Tema uno/))
    fireEvent.change(screen.getByLabelText('Número de preguntas'), { target: { value: '10' } })
    fireEvent.click(screen.getByRole('button', { name: 'Comenzar test' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('pedido 10 preguntas, pero solo hay 7')
  })
})
