import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiRequestError, apiRequest } from './api'

describe('apiRequest errors', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('preserves the HTTP status and parsed safe response body for action results', async () => {
    vi.stubGlobal('fetch', vi.fn().mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ message: 'Unsupported operation', data: { status: 'unsupported' } }), { status: 422, headers: { 'Content-Type': 'application/json' } }))))

    await expect(apiRequest('/routers/01router/connection-test', { method: 'POST' }, 'token')).rejects.toBeInstanceOf(ApiRequestError)
    await expect(apiRequest('/routers/01router/connection-test', { method: 'POST' }, 'token')).rejects.toMatchObject({ status: 422, body: { data: { status: 'unsupported' } } })
  })
})
