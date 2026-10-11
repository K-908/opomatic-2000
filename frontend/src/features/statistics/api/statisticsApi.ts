import { apiRequest } from '../../../shared/api/apiClient'

export interface StatisticsSummary {
  completedTests: number
  answeredQuestions: number
  correctCount: number
  incorrectCount: number
  unansweredCount: number
  accuracyPercentage: number | null
}

export interface TopicStatistics {
  topicId: number
  topicName: string
  answeredQuestions: number
  correctCount: number
  incorrectCount: number
  unansweredCount: number
  accuracyPercentage: number | null
  rankingPosition: number | null
}

export function getStatisticsSummary(signal?: AbortSignal) {
  return apiRequest<StatisticsSummary>('/api/statistics/summary', { signal })
}

export function getTopicStatistics(signal?: AbortSignal) {
  return apiRequest<TopicStatistics[]>('/api/statistics/topics', { signal })
}
