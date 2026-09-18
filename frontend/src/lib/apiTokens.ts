import { apiRequest } from './api'

export type ApiToken = { id: number; name: string; abilities: string[]; last_used_at: string | null; expires_at: string | null; created_at: string }
export function listApiTokens(token?: string) { return apiRequest<{ data: ApiToken[] }>('/api-tokens', {}, token) }
export function createApiToken(payload: { name: string; abilities: string[]; expires_at?: string }, token?: string) { return apiRequest<{ data: { token: string; token_record: ApiToken } }>('/api-tokens', { method: 'POST', body: JSON.stringify(payload) }, token) }
export function revokeApiToken(id: number, token?: string) { return apiRequest<{ data: { message: string } }>(`/api-tokens/${id}`, { method: 'DELETE' }, token) }
