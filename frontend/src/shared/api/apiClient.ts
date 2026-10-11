export interface ProblemDetails {
  type?: string
  title: string
  status: number
  detail?: string
  instance?: string
  traceId?: string
  errors?: Record<string, string[]>
  requestedCount?: number
  availableCount?: number
}

export class ApiError extends Error {
  readonly problemDetails: ProblemDetails

  constructor(problemDetails: ProblemDetails) {
    super(problemDetails.detail ?? problemDetails.title)
    this.name = 'ApiError'
    this.problemDetails = problemDetails
  }

  get status(): number {
    return this.problemDetails.status
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown
}

const defaultApiBaseUrl = '/'

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { body, headers: customHeaders, ...requestOptions } = options
  const headers = new Headers(customHeaders)
  headers.set('Accept', 'application/json')

  if (body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  const method = (requestOptions.method ?? 'GET').toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && path !== '/api/auth/login') {
    const csrfToken = document.cookie
      .split('; ')
      .find((cookie) => cookie.startsWith('OPOMATIC_CSRF='))
      ?.slice('OPOMATIC_CSRF='.length)
    if (csrfToken) headers.set('X-CSRF-Token', decodeURIComponent(csrfToken))
  }

  const response = await fetch(buildUrl(path), {
    ...requestOptions,
    credentials: 'include',
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (!response.ok) {
    throw new ApiError(await readProblemDetails(response))
  }

  if (response.status === 204) {
    return undefined as T
  }

  return (await response.json()) as T
}

function buildUrl(path: string): string {
  const baseUrl = (import.meta.env.VITE_API_BASE_URL || defaultApiBaseUrl).replace(
    /\/+$/,
    '',
  )
  const normalizedPath = path.startsWith('/') ? path : `/${path}`

  return `${baseUrl}${normalizedPath}`
}

async function readProblemDetails(response: Response): Promise<ProblemDetails> {
  const fallback: ProblemDetails = {
    title: 'No se ha podido completar la solicitud',
    status: response.status,
  }

  try {
    const problemDetails = (await response.json()) as Partial<ProblemDetails>

    return {
      ...fallback,
      ...problemDetails,
      status: response.status,
    }
  } catch {
    return fallback
  }
}
