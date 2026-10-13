import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getAttempts } from '../attempts/api/attemptsApi'
import { getStatisticsSummary } from './api/statisticsApi'
import { HomeDashboard } from './HomeDashboard'

vi.mock('../attempts/api/attemptsApi', () => ({ getAttempts: vi.fn() }))
vi.mock('./api/statisticsApi', () => ({ getStatisticsSummary: vi.fn() }))

const getAttemptsMock = vi.mocked(getAttempts)
const getSummaryMock = vi.mocked(getStatisticsSummary)

describe('HomeDashboard', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('guides an empty account without presenting zero as a real percentage', async () => {
    getSummaryMock.mockResolvedValue({ completedTests: 0, answeredQuestions: 0, correctCount: 0, incorrectCount: 0, unansweredCount: 0, accuracyPercentage: null })
    getAttemptsMock.mockResolvedValue({ items: [], page: 1, pageSize: 3, totalItems: 0, totalPages: 0 })

    render(<HomeDashboard canViewAccesses={false} />)

    expect(screen.getByText('Cargando tu progreso…')).toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'Empieza con tu primer test' })).toBeInTheDocument()
    expect(screen.getByText('Sin datos')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Crear mi primer test' })).toHaveAttribute('href', '/tests/new')
  })

  it('shows summary cards and recent activity', async () => {
    getSummaryMock.mockResolvedValue({ completedTests: 2, answeredQuestions: 10, correctCount: 7, incorrectCount: 3, unansweredCount: 2, accuracyPercentage: 70 })
    getAttemptsMock.mockResolvedValue({
      items: [{ id: 7, completedAtUtc: '2026-08-25T12:00:00Z', topics: [{ id: 1, name: 'Constitución' }], totalQuestions: 6, correctCount: 4, incorrectCount: 2, unansweredCount: 0, score: 5.83 }],
      page: 1, pageSize: 3, totalItems: 2, totalPages: 1,
    })

    render(<HomeDashboard canViewAccesses={false} />)

    expect(await screen.findByText('70.00 %')).toBeInTheDocument()
    expect(screen.getByText('7 aciertos · 3 fallos · 2 en blanco')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Constitución/ })).toHaveAttribute('href', '/attempts/7')
  })

  it('shows an error while keeping the main actions available', async () => {
    getSummaryMock.mockRejectedValue(new Error('offline'))
    getAttemptsMock.mockResolvedValue({ items: [], page: 1, pageSize: 3, totalItems: 0, totalPages: 0 })

    render(<HomeDashboard canViewAccesses={false} />)

    expect(await screen.findByRole('alert')).toHaveTextContent('No se han podido cargar tus estadísticas')
    expect(screen.getByRole('link', { name: 'Crear un test' })).toBeInTheDocument()
  })
})
