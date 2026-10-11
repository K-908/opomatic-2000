import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getAttempts, type PagedAttempts } from './api/attemptsApi'
import { AttemptsPage } from './AttemptsPage'

vi.mock('./api/attemptsApi', () => ({ getAttempts: vi.fn() }))
const getAttemptsMock = vi.mocked(getAttempts)

const page: PagedAttempts = {
  items: [
    { id: 2, completedAtUtc: '2026-08-25T12:00:00Z', topics: [{ id: 1, name: 'Tema reciente' }], totalQuestions: 20, correctCount: 14, incorrectCount: 4, unansweredCount: 2, score: 6.5 },
    { id: 1, completedAtUtc: '2026-08-24T12:00:00Z', topics: [{ id: 2, name: 'Tema anterior' }], totalQuestions: 10, correctCount: 0, incorrectCount: 10, unansweredCount: 0, score: -2.5 },
  ],
  page: 1,
  pageSize: 20,
  totalItems: 21,
  totalPages: 2,
}

describe('AttemptsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    window.history.replaceState(null, '', '/attempts')
  })

  it('shows loading and an accessible empty state', async () => {
    let resolve!: (value: PagedAttempts) => void
    getAttemptsMock.mockReturnValue(new Promise((done) => { resolve = done }))
    render(<AttemptsPage />)
    expect(screen.getByRole('status')).toHaveTextContent('Cargando historial')
    resolve({ items: [], page: 1, pageSize: 20, totalItems: 0, totalPages: 0 })
    expect(await screen.findByRole('heading', { name: 'Todavía no has terminado ningún test' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Crear un test' })).toHaveAttribute('href', '/tests/new')
  })

  it('keeps API order and formats score, topics and counters', async () => {
    getAttemptsMock.mockResolvedValue(page)
    render(<AttemptsPage />)
    const links = await screen.findAllByRole('link', { name: /Tema (reciente|anterior)/ })
    expect(links[0]).toHaveAttribute('href', '/attempts/2')
    expect(links[1]).toHaveAttribute('href', '/attempts/1')
    expect(screen.getByText('6.50')).toBeInTheDocument()
    expect(screen.getByText('-2.50')).toBeInTheDocument()
    expect(screen.getByText(/20 preguntas · 14 aciertos · 4 fallos · 2 en blanco/)).toBeInTheDocument()
    expect(document.querySelector('time')).toHaveAttribute('datetime', '2026-08-25T12:00:00Z')
  })

  it('changes pages through labelled accessible pagination', async () => {
    getAttemptsMock.mockResolvedValue(page)
    render(<AttemptsPage />)
    await screen.findByText('Página 1 de 2')
    const nav = screen.getByRole('navigation', { name: 'Paginación del historial' })
    expect(nav).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    await waitFor(() => expect(getAttemptsMock).toHaveBeenLastCalledWith(2, 20, expect.any(AbortSignal)))
    expect(window.location.search).toBe('?page=2')
  })

  it('shows a recoverable loading error', async () => {
    getAttemptsMock.mockRejectedValue(new Error('offline'))
    render(<AttemptsPage />)
    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido cargar el historial')
  })
})
