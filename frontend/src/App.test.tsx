import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { getHealth } from './features/health/api/getHealth'
import { getAttempts } from './features/attempts/api/attemptsApi'
import { getStatisticsSummary } from './features/statistics/api/statisticsApi'
import { getSession } from './features/auth/api/authApi'

vi.mock('./features/health/api/getHealth', () => ({
  getHealth: vi.fn(),
}))
vi.mock('./features/attempts/api/attemptsApi', () => ({ getAttempts: vi.fn() }))
vi.mock('./features/statistics/api/statisticsApi', () => ({
  getStatisticsSummary: vi.fn(),
  getTopicStatistics: vi.fn(),
}))
vi.mock('./features/auth/api/authApi', () => ({
  getSession: vi.fn(),
  logout: vi.fn(),
}))

const getHealthMock = vi.mocked(getHealth)
const getAttemptsMock = vi.mocked(getAttempts)
const getStatisticsSummaryMock = vi.mocked(getStatisticsSummary)
const getSessionMock = vi.mocked(getSession)

describe('App health status', () => {
  beforeEach(() => {
    getHealthMock.mockReset()
    getSessionMock.mockResolvedValue({ username: 'Gogloglo' })
    getAttemptsMock.mockResolvedValue({ items: [], page: 1, pageSize: 3, totalItems: 0, totalPages: 0 })
    getStatisticsSummaryMock.mockResolvedValue({ completedTests: 0, answeredQuestions: 0, correctCount: 0, incorrectCount: 0, unansweredCount: 0, accuracyPercentage: null })
    window.history.replaceState(null, '', '/')
  })

  it('shows the loading state while the API request is pending', async () => {
    getHealthMock.mockReturnValue(new Promise(() => undefined))

    render(<App />)

    expect(
      await screen.findByRole('heading', {
        name: 'Tu preparación, pregunta a pregunta.',
      }),
    ).toBeInTheDocument()
    expect(screen.getByText(/Comprobando la conexión con la API/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Inicio' })).toHaveAttribute('href', '/')
  })

  it('shows the application name when the API is available', async () => {
    getHealthMock.mockResolvedValue({
      status: 'ok',
      application: 'OpoMatic-2000',
    })

    render(<App />)

    expect(await screen.findByRole('status')).toHaveTextContent(
      'API disponible: OpoMatic-2000',
    )
  })

  it('shows an understandable message when the API is unavailable', async () => {
    getHealthMock.mockRejectedValue(new TypeError('Failed to fetch'))

    render(<App />)

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se puede conectar con la API',
    )
  })

  it('aborts the request when the component is unmounted', async () => {
    getHealthMock.mockReturnValue(new Promise(() => undefined))
    const { unmount } = render(<App />)
    await screen.findByRole('heading', { name: 'Tu preparación, pregunta a pregunta.' })
    const signal = getHealthMock.mock.calls[0][0]

    unmount()

    expect(signal?.aborted).toBe(true)
  })

  it('redirects a direct runner visit when there is no in-memory session', async () => {
    window.history.replaceState(null, '', '/tests/run')
    render(<App />)

    expect(await screen.findByRole('heading', { name: 'Nuevo test' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Inicio' })).toBeVisible()
    expect(window.location.pathname).toBe('/tests/new')
  })
})
