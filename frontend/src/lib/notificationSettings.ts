import { apiRequest } from './api'

export type NotificationSettings = { telegram_enabled: boolean; token_configured: boolean; telegram_chat_id: string }
export type NotificationResponse = { data: { settings: NotificationSettings } }
export function getNotificationSettings(token?: string) { return apiRequest<NotificationResponse>('/notifications', {}, token) }
export function updateNotificationSettings(values: Record<string, unknown>, token?: string) { return apiRequest<NotificationResponse>('/notifications', { method: 'PUT', body: JSON.stringify(values) }, token) }
export function sendNotificationTest(token?: string) { return apiRequest<{ data: { message: string } }>('/notifications/test', { method: 'POST' }, token) }
