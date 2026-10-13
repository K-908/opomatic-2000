import { apiRequest } from '../../../shared/api/apiClient'

export interface AuthSession {
  username: string
}

export interface LoginAccess {
  username: string
  accessedAtUtc: string
  ipAddress: string
}

export function getSession(signal?: AbortSignal): Promise<AuthSession> {
  return apiRequest<AuthSession>('/api/auth/session', { signal })
}

export function login(username: string, password: string): Promise<AuthSession> {
  return apiRequest<AuthSession>('/api/auth/login', {
    method: 'POST',
    body: { username, password },
  })
}

export function logout(): Promise<void> {
  return apiRequest<void>('/api/auth/logout', { method: 'POST' })
}

export function getLoginAccesses(signal?: AbortSignal): Promise<LoginAccess[]> {
  return apiRequest<LoginAccess[]>('/api/auth/accesses', { signal })
}
