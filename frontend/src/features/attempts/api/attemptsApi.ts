import { apiRequest } from '../../../shared/api/apiClient'

export interface AttemptTopic {
  id: number
  name: string
}

export interface AttemptListItem {
  id: number
  completedAtUtc: string
  topics: AttemptTopic[]
  totalQuestions: number
  correctCount: number
  incorrectCount: number
  unansweredCount: number
  score: number
}

export interface PagedAttempts {
  items: AttemptListItem[]
  page: number
  pageSize: number
  totalItems: number
  totalPages: number
}

export type AttemptQuestionResult = 'correct' | 'incorrect' | 'unanswered'

export interface AttemptOption {
  displayOrder: number
  text: string
  isCorrect: boolean
  isSelected: boolean
}

export interface AttemptQuestion {
  displayOrder: number
  originalQuestionId: number
  topicName: string
  statement: string
  result: AttemptQuestionResult
  options: AttemptOption[]
}

export interface AttemptDetails extends AttemptListItem {
  questions: AttemptQuestion[]
}

export function getAttempts(page: number, pageSize: number, signal?: AbortSignal) {
  const query = new URLSearchParams({ page: String(page), pageSize: String(pageSize) })
  return apiRequest<PagedAttempts>(`/api/attempts?${query}`, { signal })
}

export function getAttempt(id: number, signal?: AbortSignal) {
  return apiRequest<AttemptDetails>(`/api/attempts/${id}`, { signal })
}
