import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { TopicsPage } from './TopicsPage'
import {
  createTopic,
  getTopics,
  setTopicStatus,
  updateTopic,
} from './api/topicsApi'

vi.mock('./api/topicsApi', () => ({
  createTopic: vi.fn(),
  getTopics: vi.fn(),
  setTopicStatus: vi.fn(),
  updateTopic: vi.fn(),
}))

const getTopicsMock = vi.mocked(getTopics)
const createTopicMock = vi.mocked(createTopic)
const updateTopicMock = vi.mocked(updateTopic)
const setTopicStatusMock = vi.mocked(setTopicStatus)

const activeTopic = {
  id: 1,
  name: 'Derecho constitucional',
  isActive: true,
  activeQuestionCount: 12,
}

describe('TopicsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows loading and then the empty state', async () => {
    let resolveTopics: (topics: never[]) => void = () => undefined
    getTopicsMock.mockReturnValue(
      new Promise((resolve) => {
        resolveTopics = resolve
      }),
    )

    render(<TopicsPage />)
    expect(screen.getByRole('status')).toHaveTextContent('Cargando temas')

    resolveTopics([])
    expect(await screen.findByText('Todavía no hay temas')).toBeInTheDocument()
  })

  it('creates a topic and refreshes the list without reloading', async () => {
    getTopicsMock.mockResolvedValueOnce([]).mockResolvedValueOnce([activeTopic])
    createTopicMock.mockResolvedValue(activeTopic)
    render(<TopicsPage />)
    await screen.findByText('Todavía no hay temas')

    fireEvent.change(screen.getByLabelText('Nombre'), {
      target: { value: activeTopic.name },
    })
    fireEvent.submit(screen.getByRole('button', { name: 'Crear tema' }).closest('form')!)

    expect(
      await screen.findByRole('heading', { name: activeTopic.name }),
    ).toBeInTheDocument()
    expect(createTopicMock).toHaveBeenCalledWith(activeTopic.name)
    expect(getTopicsMock).toHaveBeenCalledTimes(2)
  })

  it('edits and deactivates a topic after confirmation', async () => {
    const updated = { ...activeTopic, name: 'Derecho administrativo' }
    getTopicsMock
      .mockResolvedValueOnce([activeTopic])
      .mockResolvedValueOnce([updated])
      .mockResolvedValueOnce([])
    updateTopicMock.mockResolvedValue(updated)
    setTopicStatusMock.mockResolvedValue()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    render(<TopicsPage />)
    await screen.findByRole('heading', { name: activeTopic.name })

    fireEvent.click(screen.getByRole('button', { name: `Editar ${activeTopic.name}` }))
    const input = screen.getByLabelText('Nombre')
    await waitFor(() => expect(input).toHaveFocus())
    fireEvent.change(input, { target: { value: updated.name } })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))
    expect(
      await screen.findByRole('heading', { name: updated.name }),
    ).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: `Desactivar ${updated.name}` }))
    await waitFor(() => expect(setTopicStatusMock).toHaveBeenCalledWith(1, false))
    expect(window.confirm).toHaveBeenCalled()
    expect(await screen.findByText('Todavía no hay temas')).toBeInTheDocument()
  })

  it('requests inactive topics when the filter changes', async () => {
    getTopicsMock.mockResolvedValue([])
    render(<TopicsPage />)
    await screen.findByText('Todavía no hay temas')

    fireEvent.click(screen.getByLabelText('Mostrar inactivos'))

    await waitFor(() => expect(getTopicsMock).toHaveBeenLastCalledWith(true, expect.any(AbortSignal)))
  })

  it('shows backend field errors next to the name input and restores focus', async () => {
    getTopicsMock.mockResolvedValue([])
    createTopicMock.mockRejectedValue(
      new ApiError({
        title: 'Datos no válidos',
        status: 400,
        errors: { name: ['El nombre ya no es válido.'] },
      }),
    )
    render(<TopicsPage />)
    await screen.findByText('Todavía no hay temas')

    const input = screen.getByLabelText('Nombre')
    fireEvent.change(input, { target: { value: 'Tema' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear tema' }))

    expect(await screen.findByText('El nombre ya no es válido.')).toBeInTheDocument()
    await waitFor(() => expect(input).toHaveFocus())
  })
})
