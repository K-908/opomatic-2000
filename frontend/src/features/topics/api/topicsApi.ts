import { apiRequest } from '../../../shared/api/apiClient'

export interface Topic {
  id: number
  name: string
  isActive: boolean
  activeQuestionCount: number
}

export function getTopics(
  includeInactive: boolean,
  signal?: AbortSignal,
): Promise<Topic[]> {
  return apiRequest<Topic[]>(
    `/api/topics?includeInactive=${includeInactive}`,
    { signal },
  )
}

export function createTopic(name: string): Promise<Topic> {
  return apiRequest<Topic>('/api/topics', {
    method: 'POST',
    body: { name },
  })
}

export function updateTopic(id: number, name: string): Promise<Topic> {
  return apiRequest<Topic>(`/api/topics/${id}`, {
    method: 'PUT',
    body: { name },
  })
}

export function setTopicStatus(id: number, isActive: boolean): Promise<void> {
  return apiRequest<void>(`/api/topics/${id}/status`, {
    method: 'PATCH',
    body: { isActive },
  })
}
