import { apiRequest } from '../../../shared/api/apiClient'

export interface HealthResponse {
  status: string
  application: string
}

export function getHealth(signal?: AbortSignal): Promise<HealthResponse> {
  return apiRequest<HealthResponse>('/health', { signal })
}
