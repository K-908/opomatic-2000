import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getAttempt, type AttemptDetails } from './api/attemptsApi'
import { AttemptDetailPage } from './AttemptDetailPage'

vi.mock('./api/attemptsApi', () => ({ getAttempt: vi.fn() }))
const getAttemptMock = vi.mocked(getAttempt)

const detail: AttemptDetails = {
  id: 500,
  completedAtUtc: '2026-08-25T12:00:00Z',
  topics: [{ id: 1, name: 'Tema histórico' }],
  totalQuestions: 3,
  correctCount: 1,
  incorrectCount: 1,
  unansweredCount: 1,
  score: 2.5,
  questions: [
    question(1, 'Acertada histórica', 'correct', 1),
    question(2, 'Fallada histórica', 'incorrect', 2),
    question(3, 'En blanco histórica', 'unanswered', null),
  ],
}

function question(
  displayOrder: number,
  statement: string,
  result: 'correct' | 'incorrect' | 'unanswered',
  selectedOrder: number | null,
) {
  return {
    displayOrder,
    originalQuestionId: displayOrder * 10,
    topicName: 'Tema histórico',
    statement,
    result,
    options: [1, 2, 3, 4].map((order) => ({
      displayOrder: order,
      text: `Opción histórica ${displayOrder}.${order}`,
      isCorrect: order === 1,
      isSelected: order === selectedOrder,
    })),
  }
}

describe('AttemptDetailPage', () => {
  beforeEach(() => vi.resetAllMocks())

  it('shows loading and API errors', async () => {
    getAttemptMock.mockRejectedValue(new Error('missing'))
    render(<AttemptDetailPage attemptId={999} />)
    expect(screen.getByRole('status')).toHaveTextContent('Cargando revisión')
    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar este intento')
  })

  it('shows historical correction in read-only mode with text and icon labels', async () => {
    getAttemptMock.mockResolvedValue(detail)
    render(<AttemptDetailPage attemptId={500} />)
    expect(await screen.findByRole('heading', { name: 'Nota 2.50' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Acertada histórica' })).toBeInTheDocument()
    expect(screen.getByText('Acertada')).toBeInTheDocument()
    expect(screen.getAllByText('Tu respuesta')).toHaveLength(1)
    expect(screen.getByText('Respuesta correcta')).toBeInTheDocument()
    expect(screen.queryByRole('radio')).not.toBeInTheDocument()
  })

  it('navigates and clearly distinguishes incorrect and unanswered questions', async () => {
    getAttemptMock.mockResolvedValue(detail)
    render(<AttemptDetailPage attemptId={500} />)
    await screen.findByRole('heading', { name: 'Acertada histórica' })
    fireEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(screen.getByRole('heading', { name: 'Fallada histórica' })).toBeInTheDocument()
    expect(screen.getByText('Fallada')).toBeInTheDocument()
    expect(screen.getByText('Opción histórica 2.2').closest('li')).toHaveTextContent('Tu respuesta')
    expect(screen.getByText('Opción histórica 2.1').closest('li')).toHaveTextContent('Respuesta correcta')
    fireEvent.click(screen.getByRole('button', { name: 'Pregunta 3: Sin contestar' }))
    expect(screen.getByRole('heading', { name: 'En blanco histórica' })).toBeInTheDocument()
    expect(screen.getByText('No seleccionaste ninguna respuesta.')).toBeInTheDocument()
    expect(screen.getAllByText('Sin contestar')).toHaveLength(2)
  })
})
