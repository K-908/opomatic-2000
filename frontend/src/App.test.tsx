import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { getAttempts } from './features/attempts/api/attemptsApi'
import { getStatisticsSummary } from './features/statistics/api/statisticsApi'
import { getLoginAccesses, getSession } from './features/auth/api/authApi'

vi.mock('./features/attempts/api/attemptsApi', () => ({ getAttempts: vi.fn() }))
vi.mock('./features/statistics/api/statisticsApi', () => ({
  getStatisticsSummary: vi.fn(),
  getTopicStatistics: vi.fn(),
}))
vi.mock('./features/auth/api/authApi', () => ({
  getSession: vi.fn(),
  getLoginAccesses: vi.fn(),
  logout: vi.fn(),
}))

const getAttemptsMock = vi.mocked(getAttempts)
const getStatisticsSummaryMock = vi.mocked(getStatisticsSummary)
const getSessionMock = vi.mocked(getSession)
const getLoginAccessesMock = vi.mocked(getLoginAccesses)

describe('App navigation and access permissions', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    getSessionMock.mockResolvedValue({ username: 'Gogloglo' })
    getAttemptsMock.mockResolvedValue({ items: [], page: 1, pageSize: 3, totalItems: 0, totalPages: 0 })
    getStatisticsSummaryMock.mockResolvedValue({ completedTests: 0, answeredQuestions: 0, correctCount: 0, incorrectCount: 0, unansweredCount: 0, accuracyPercentage: null })
    window.history.replaceState(null, '', '/')
  })

  it('removes the welcome block and keeps accesses available to Gogloglo', async () => {
    render(<App />)

    expect(await screen.findByRole('heading', { name: 'Resumen' })).toBeInTheDocument()
    expect(screen.queryByText('Tu preparación, pregunta a pregunta.')).not.toBeInTheDocument()
    expect(screen.queryByText(/API disponible/)).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Accesos' })).toHaveAttribute('href', '/accesses')
    expect(screen.getByRole('link', { name: 'Accesos recientes' })).toHaveAttribute('href', '/accesses')
  })

  it('hides accesses from Virginia, including a direct visit', async () => {
    getSessionMock.mockResolvedValue({ username: 'Virginia' })
    window.history.replaceState(null, '', '/accesses')
    render(<App />)

    await waitFor(() => expect(window.location.pathname).toBe('/'))
    expect(await screen.findByRole('heading', { name: 'Resumen' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Accesos' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Accesos recientes' })).not.toBeInTheDocument()
    expect(getLoginAccessesMock).not.toHaveBeenCalled()
  })

  it('shows both account names in the access history for Gogloglo', async () => {
    window.history.replaceState(null, '', '/accesses')
    getLoginAccessesMock.mockResolvedValue([
      { username: 'Gogloglo', accessedAtUtc: '2026-10-13T10:00:00Z', ipAddress: '192.0.2.1' },
      { username: 'Virginia', accessedAtUtc: '2026-10-13T09:00:00Z', ipAddress: '192.0.2.2' },
    ])
    render(<App />)

    expect(await screen.findByRole('heading', { name: 'Accesos recientes' })).toBeInTheDocument()
    expect(await screen.findByText('Virginia')).toBeInTheDocument()
    expect(screen.getByText('192.0.2.1')).toBeInTheDocument()
    expect(screen.getByText('192.0.2.2')).toBeInTheDocument()
  })

  it('redirects a direct runner visit when there is no in-memory session', async () => {
    window.history.replaceState(null, '', '/tests/run')
    render(<App />)

    expect(await screen.findByRole('heading', { name: 'Nuevo test' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Inicio' })).toBeVisible()
    expect(window.location.pathname).toBe('/tests/new')
  })
})
