const API_BASE = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/$/, '')

type CrudOperationDetail = { id: number; method: string; path: string; title: string; description: string }
let crudOperationId = 0
const inFlightGetRequests = new Map<string, Promise<unknown>>()

function dispatchCrudOperation(type: 'start' | 'end', detail: CrudOperationDetail): void {
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent(`crud-operation:${type}`, { detail }))
}

function operationResource(path: string): string {
  if (path.includes('configuration-export')) return 'ONT configuration'
  if (path.includes('ont-tr069-server-profiles')) return 'TR-069 profile'
  if (path.includes('ont-service-profiles')) return 'ONT service profile'
  if (path.includes('ont-wan-profiles')) return 'ONT WAN profile'
  if (path.includes('terminal-users')) return 'terminal user'
  if (path.includes('dba-profiles')) return 'DBA profile'
  if (path.includes('/qinq')) return 'QinQ record'
  if (path.includes('/vlans')) return 'VLAN record'
  if (path.includes('/onts')) return 'ONT record'
  if (path.includes('/onus')) return 'ONU record'
  if (path.includes('/activations')) return 'activation'
  if (path.includes('/activation-presets')) return 'activation preset'
  if (path.includes('/activation-options')) return 'OLT activation options'
  if (path.includes('/olts')) return 'OLT record'
  if (path.includes('/bngs')) return 'BNG record'
  return 'record'
}

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

async function requestApi<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const method = (options.method || 'GET').toUpperCase()
  const isMutation = !['GET', 'HEAD', 'OPTIONS'].includes(method)
  const id = ++crudOperationId
  const resource = operationResource(path)
  const detail: CrudOperationDetail = {
    id,
    method,
    path,
    title: method === 'DELETE' ? `Removing ${resource}` : `Applying ${resource} changes`,
    description: path.includes('/olts') ? 'Updating the OLT configuration' : 'Saving changes to the system',
  }
  if (isMutation) dispatchCrudOperation('start', detail)
  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), 15000)
  try {
    const response = await fetch(`${API_BASE}${path}`, {
      ...options,
      signal: options.signal || controller.signal,
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
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new ApiRequestError('The request timed out. Check that the API server and database are responding, then retry.', 408, {})
    }
    throw error
  } finally {
    clearTimeout(timeoutId)
    if (isMutation) dispatchCrudOperation('end', detail)
  }
}

export function apiRequest<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const method = (options.method || 'GET').toUpperCase()
  if (method !== 'GET' || options.signal) return requestApi<T>(path, options, token)

  const key = `${token || ''}:${path}`
  const existing = inFlightGetRequests.get(key)
  if (existing) return existing as Promise<T>

  const request = requestApi<T>(path, options, token)
  inFlightGetRequests.set(key, request)
  const clear = () => {
    if (inFlightGetRequests.get(key) === request) inFlightGetRequests.delete(key)
  }
  request.then(clear, clear)
  return request
}

export async function apiDownload(path: string, token?: string): Promise<Blob> {
  const method = 'POST'
  const id = ++crudOperationId
  const resource = operationResource(path)
  const detail: CrudOperationDetail = {
    id,
    method,
    path,
    title: `Exporting ${resource}`,
    description: 'Requesting the current ONT configuration from the ACS',
  }
  dispatchCrudOperation('start', detail)
  try {
    const response = await fetch(`${API_BASE}${path}`, {
      method,
      headers: {
        Accept: 'application/json, application/xml',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    })
    if (!response.ok) {
      const body = await response.json().catch(() => ({}))
      const responseBody = typeof body === 'object' && body !== null ? body as Record<string, unknown> : {}
      throw new ApiRequestError(typeof responseBody.message === 'string' ? responseBody.message : `Request failed (${response.status})`, response.status, body)
    }
    return response.blob()
  } finally {
    dispatchCrudOperation('end', detail)
  }
}
