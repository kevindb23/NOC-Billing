const API_BASE = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/$/, '')

export class ApiRequestError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(message: string, status: number, body: unknown) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.body = body
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    const responseBody = typeof body === 'object' && body !== null ? body as Record<string, unknown> : {}
    const errors = responseBody.errors
    const validation = typeof errors === 'object' && errors !== null
      ? Object.values(errors as Record<string, unknown>).flatMap(value => Array.isArray(value) ? value : [value]).filter(value => typeof value === 'string').join(' ')
      : ''
    const message = validation || (typeof responseBody.message === 'string' ? responseBody.message : `Request failed (${response.status})`)
    throw new ApiRequestError(message, response.status, body)
  }
  return body
}
