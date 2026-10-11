import { fireEvent, render, screen, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getTopicStatistics, type TopicStatistics } from './api/statisticsApi'
import { StatisticsPage } from './StatisticsPage'

vi.mock('./api/statisticsApi', () => ({ getTopicStatistics: vi.fn() }))
const getTopicStatisticsMock = vi.mocked(getTopicStatistics)

const topics: TopicStatistics[] = [
  { topicId: 1, topicName: 'Tema fuerte', answeredQuestions: 20, correctCount: 18, incorrectCount: 2, unansweredCount: 1, accuracyPercentage: 90, rankingPosition: 1 },
  { topicId: 2, topicName: 'Tema débil', answeredQuestions: 4, correctCount: 1, incorrectCount: 3, unansweredCount: 2, accuracyPercentage: 25, rankingPosition: 2 },
  { topicId: 3, topicName: 'Solo blancos', answeredQuestions: 0, correctCount: 0, incorrectCount: 0, unansweredCount: 3, accuracyPercentage: null, rankingPosition: null },
]

describe('StatisticsPage', () => {
  beforeEach(() => vi.resetAllMocks())

  it('shows loading, error and empty states', async () => {
    getTopicStatisticsMock.mockRejectedValueOnce(new Error('offline'))
    const { unmount } = render(<StatisticsPage />)
    expect(screen.getByRole('status')).toHaveTextContent('Calculando estadísticas')
    expect(await screen.findByRole('alert')).toHaveTextContent('No se han podido cargar')
    unmount()

    getTopicStatisticsMock.mockResolvedValueOnce([])
    render(<StatisticsPage />)
    expect(await screen.findByRole('heading', { name: 'Todavía no hay resultados por tema' })).toBeInTheDocument()
  })

  it('renders all counters, nulls and small-sample context without relying on color', async () => {
    getTopicStatisticsMock.mockResolvedValue(topics)
    render(<StatisticsPage />)

    const weakRow = (await screen.findByText('Tema débil')).closest('tr')!
    expect(within(weakRow).getByText('Muestra pequeña')).toBeInTheDocument()
    expect(within(weakRow).getByText('25.00 %')).toBeInTheDocument()
    const unansweredRow = screen.getByText('Solo blancos').closest('tr')!
    expect(within(unansweredRow).getByText('Sin datos')).toBeInTheDocument()
    expect(within(unansweredRow).getByText('3')).toBeInTheDocument()
  })

  it('can reverse ranked topics while leaving topics without answers last', async () => {
    getTopicStatisticsMock.mockResolvedValue(topics)
    render(<StatisticsPage />)
    await screen.findByText('Tema fuerte')

    fireEvent.change(screen.getByLabelText('Orden'), { target: { value: 'worst' } })

    const rows = screen.getAllByRole('row').slice(1)
    expect(within(rows[0]).getByText('Tema débil')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Tema fuerte')).toBeInTheDocument()
    expect(within(rows[2]).getByText('Solo blancos')).toBeInTheDocument()
  })
})
