import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useEffect } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/apiClient'
import { finishTest, type FinishedTest, type GeneratedTest } from './api/testsApi'
import { TestRunPage } from './TestRunPage'
import { TestSessionProvider } from './TestSessionProvider'
import { useTestSession } from './useTestSession'

vi.mock('./api/testsApi', async (importOriginal) => {
  const original = await importOriginal<typeof import('./api/testsApi')>()
  return { ...original, finishTest: vi.fn() }
})

const finishTestMock = vi.mocked(finishTest)
const generatedTest: GeneratedTest = {
  testToken: 'opaque',
  expiresAtUtc: '2026-08-21T00:00:00Z',
  topics: [{ id: 1, name: 'Tema uno' }],
  questions: [1, 2].map((number) => ({
    id: number * 10,
    displayOrder: number,
    topicId: 1,
    topicName: 'Tema uno',
    statement: `Pregunta ${number}`,
    options: [1, 2, 3, 4].map((option) => ({
      id: number * 100 + option,
      displayOrder: option,
      text: `Opción ${option}`,
    })),
  })),
}

function Harness({
  onFinished = vi.fn(),
  onAbandon = vi.fn(),
}: {
  onFinished?: (result: FinishedTest) => void
  onAbandon?: () => void
}) {
  const session = useTestSession()
  useEffect(() => {
    if (!session.generatedTest) session.startTest(generatedTest)
  }, [session])
  return session.generatedTest
    ? <TestRunPage onFinished={onFinished} onAbandon={onAbandon} />
    : null
}

function renderRunner(props: {
  onFinished?: (result: FinishedTest) => void
  onAbandon?: () => void
} = {}) {
  return render(<TestSessionProvider><Harness {...props} /></TestSessionProvider>)
}

describe('TestRunPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
  })

  it('selects with accessible radios, navigates and preserves the answer', async () => {
    renderRunner()
    const firstOption = await screen.findByRole('radio', { name: 'Opción 1' })
    fireEvent.click(firstOption)
    fireEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(screen.getByRole('heading', { name: 'Pregunta 2 de 2' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Anterior' }))
    expect(screen.getByRole('radio', { name: 'Opción 1' })).toBeChecked()
    expect(screen.getByLabelText('Ir a la pregunta 1, contestada')).toBeInTheDocument()
  })

  it('warns before unloading and only abandons after confirmation', async () => {
    const onAbandon = vi.fn()
    renderRunner({ onAbandon })
    await screen.findByRole('heading', { name: 'Pregunta 1 de 2' })
    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
    vi.mocked(window.confirm).mockReturnValueOnce(false)
    fireEvent.click(screen.getByRole('button', { name: 'Abandonar' }))
    expect(onAbandon).not.toHaveBeenCalled()
    vi.mocked(window.confirm).mockReturnValueOnce(true)
    fireEvent.click(screen.getByRole('button', { name: 'Abandonar' }))
    expect(onAbandon).toHaveBeenCalledOnce()
  })

  it('confirms blanks, sends null and blocks repeated submissions', async () => {
    let resolveRequest!: (value: Awaited<ReturnType<typeof finishTest>>) => void
    finishTestMock.mockReturnValue(new Promise((resolve) => { resolveRequest = resolve }))
    const onFinished = vi.fn()
    renderRunner({ onFinished })
    fireEvent.click(await screen.findByRole('radio', { name: 'Opción 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Finalizar test' }))
    expect(screen.getByRole('dialog')).toHaveTextContent('Dejarás 1 pregunta sin contestar')
    const confirm = screen.getByRole('button', { name: 'Confirmar entrega' })
    fireEvent.click(confirm)
    expect(screen.getByRole('button', { name: 'Entregando…' })).toBeDisabled()
    expect(finishTestMock).toHaveBeenCalledOnce()
    expect(finishTestMock.mock.calls[0][2]).toEqual([
      { questionId: 10, selectedOptionId: 101 },
      { questionId: 20, selectedOptionId: null },
    ])
    resolveRequest({
      attemptId: 500,
      completedAtUtc: '2026-08-20T13:00:00Z',
      totalQuestions: 2,
      correctCount: 1,
      incorrectCount: 0,
      unansweredCount: 1,
      score: 5,
    })
    await waitFor(() => expect(onFinished).toHaveBeenCalledOnce())
  })

  it('moves focus into the dialog, traps it and restores it when Escape closes', async () => {
    renderRunner()
    const openButton = await screen.findByRole('button', { name: 'Finalizar test' })
    openButton.focus()
    fireEvent.click(openButton)

    const cancelButton = screen.getByRole('button', { name: 'Seguir revisando' })
    const confirmButton = screen.getByRole('button', { name: 'Confirmar entrega' })
    expect(cancelButton).toHaveFocus()

    cancelButton.focus()
    fireEvent.keyDown(document, { key: 'Tab', shiftKey: true })
    expect(confirmButton).toHaveFocus()

    fireEvent.keyDown(document, { key: 'Escape' })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(openButton).toHaveFocus()
  })

  it('represents an expired token without losing the current answers', async () => {
    finishTestMock.mockRejectedValue(new ApiError({ title: 'Caducado', status: 410 }))
    renderRunner()
    fireEvent.click(await screen.findByRole('radio', { name: 'Opción 2' }))
    fireEvent.click(screen.getByRole('button', { name: 'Finalizar test' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirmar entrega' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('ha caducado')
    expect(screen.getByRole('radio', { name: 'Opción 2' })).toBeChecked()
  })
})
