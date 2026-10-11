import { apiRequest } from '../../../shared/api/apiClient'

export interface QuestionListItem {
  id: number
  topicId: number
  topicName: string
  statement: string
  isActive: boolean
  updatedAtUtc: string
}

export interface QuestionOption {
  id: number
  position: number
  text: string
  isCorrect: boolean
}

export interface Question {
  id: number
  topicId: number
  statement: string
  isActive: boolean
  options: QuestionOption[]
}

export interface PagedQuestions {
  items: QuestionListItem[]
  page: number
  pageSize: number
  totalItems: number
  totalPages: number
}

export interface QuestionFilters {
  topicId?: number
  includeInactive: boolean
  search: string
  page: number
  pageSize: number
}

export interface SaveQuestionInput {
  topicId: number
  statement: string
  options: Array<{ position: number; text: string; isCorrect: boolean }>
}

export interface ImportQuestionsDocument {
  topics: Array<{
    name: string
    questions: Array<{
      statement: string
      options: string[]
      correctOption: number
    }>
  }>
}

export interface ImportQuestionsResult {
  createdTopics: number
  reusedTopics: number
  importedQuestions: number
}

export function getQuestions(filters: QuestionFilters, signal?: AbortSignal) {
  const query = new URLSearchParams({
    includeInactive: String(filters.includeInactive),
    search: filters.search,
    page: String(filters.page),
    pageSize: String(filters.pageSize),
  })
  if (filters.topicId) query.set('topicId', String(filters.topicId))
  return apiRequest<PagedQuestions>(`/api/questions?${query}`, { signal })
}

export function getQuestion(id: number, signal?: AbortSignal) {
  return apiRequest<Question>(`/api/questions/${id}`, { signal })
}

export function createQuestion(input: SaveQuestionInput) {
  return apiRequest<Question>('/api/questions', { method: 'POST', body: input })
}

export function updateQuestion(id: number, input: SaveQuestionInput) {
  return apiRequest<Question>(`/api/questions/${id}`, { method: 'PUT', body: input })
}

export function setQuestionStatus(id: number, isActive: boolean) {
  return apiRequest<void>(`/api/questions/${id}/status`, {
    method: 'PATCH',
    body: { isActive },
  })
}

export function importQuestions(document: ImportQuestionsDocument) {
  return apiRequest<ImportQuestionsResult>('/api/questions/import', {
    method: 'POST',
    body: document,
  })
}
