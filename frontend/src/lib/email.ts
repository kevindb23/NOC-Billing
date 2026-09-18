import { apiRequest } from './api'

export type EmailSettings = {
  provider: 'gmail' | 'outlook' | 'mailgun' | 'custom'
  host: string
  port: number
  encryption: 'none' | 'tls' | 'ssl'
  username: string
  password_configured: boolean
  from_name: string
  from_email: string
}

export type EmailResponse = { data: { settings: EmailSettings } }

export function getEmailSettings(token?: string) { return apiRequest<EmailResponse>('/email', {}, token) }
export function updateEmailSettings(values: Record<string, unknown>, token?: string) { return apiRequest<EmailResponse>('/email', { method: 'PUT', body: JSON.stringify(values) }, token) }
export function sendTestEmail(recipient: string, token?: string) { return apiRequest<{ data: { message: string } }>('/email/test', { method: 'POST', body: JSON.stringify({ recipient }) }, token) }
