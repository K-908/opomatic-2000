import { afterEach, describe, expect, it, vi } from 'vitest'
import { apiRequest } from './apiClient'

describe('apiRequest', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('deserializes a successful JSON response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ status: 'ok' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    const result = await apiRequest<{ status: string }>('/health')

    expect(result).toEqual({ status: 'ok' })
  })

  it('preserves the documented Problem Details fields', async () => {
    const problemDetails = {
      type: 'https://opomatic-2000/errors/validation',
      title: 'Los datos enviados no son válidos',
      status: 400,
      detail: 'Corrige los campos indicados.',
      instance: '/api/questions',
      traceId: 'test-trace-id',
      errors: { statement: ['El enunciado es obligatorio.'] },
    }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify(problemDetails), {
          status: 400,
          headers: { 'Content-Type': 'application/problem+json' },
        }),
      ),
    )

    const request = apiRequest('/api/questions')

    await expect(request).rejects.toMatchObject({
      name: 'ApiError',
      status: 400,
      problemDetails,
    })
  })

  it('passes the AbortSignal to fetch', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ status: 'ok' }), { status: 200 }),
    )
    vi.stubGlobal('fetch', fetchMock)
    const controller = new AbortController()

    await apiRequest('/health', { signal: controller.signal })

    expect(fetchMock).toHaveBeenCalledWith(
      '/health',
      expect.objectContaining({ signal: controller.signal }),
    )
  })

  it('handles successful responses without content', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 204 })))

    await expect(apiRequest<void>('/api/topics/1/status')).resolves.toBeUndefined()
  })
})
