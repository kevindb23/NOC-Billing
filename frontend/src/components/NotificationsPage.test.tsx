// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { NotificationsPage } from './NotificationsPage'
import { ConfirmProvider } from './ConfirmProvider'
import { getNotificationSettings, updateNotificationSettings, sendNotificationTest } from '@/lib/notificationSettings'

vi.mock('@/lib/notificationSettings', () => ({ getNotificationSettings: vi.fn(), updateNotificationSettings: vi.fn(), sendNotificationTest: vi.fn() }))

const settings = { telegram_enabled: false, token_configured: true, telegram_chat_id: '-1001234567890' }

describe('notifications page', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders Telegram settings for a superadmin', () => {
    render(<NotificationsPage token="token" permissions={[]} isSuperadmin settings={settings} />)
    expect(screen.getByRole('heading', { name: 'Notifications' })).toBeTruthy()
    expect(screen.getByLabelText('Telegram bot token')).toBeTruthy()
    expect(screen.getByLabelText('Telegram chat ID')).toHaveProperty('value', '-1001234567890')
  })

  it('saves settings and sends a test notification', async () => {
    vi.mocked(updateNotificationSettings).mockResolvedValue({ data: { settings } })
    vi.mocked(sendNotificationTest).mockResolvedValue({ data: { message: 'Test notification sent.' } })
    render(<NotificationsPage token="token" permissions={['notifications.view', 'notifications.update', 'notifications.test']} settings={settings} />)
    fireEvent.click(screen.getByLabelText('Enable Telegram notifications'))
    fireEvent.click(screen.getByRole('button', { name: /save changes/i }))
    await vi.waitFor(() => expect(updateNotificationSettings).toHaveBeenCalled())
    fireEvent.click(screen.getByRole('button', { name: /send test notification/i }))
    await vi.waitFor(() => expect(sendNotificationTest).toHaveBeenCalledWith('token'))
  })

  it('resets asynchronously loaded changes to the last saved settings', async () => {
    vi.mocked(getNotificationSettings).mockResolvedValue({ data: { settings } })
    render(<ConfirmProvider><NotificationsPage token="token" permissions={['notifications.view', 'notifications.update']} /></ConfirmProvider>)

    await vi.waitFor(() => expect(screen.getByLabelText('Telegram chat ID')).toHaveProperty('value', '-1001234567890'))
    fireEvent.change(screen.getByLabelText('Telegram chat ID'), { target: { value: '-1009999999999' } })
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }))
    fireEvent.click(screen.getByRole('button', { name: 'Reset fields' }))

    expect(getNotificationSettings).toHaveBeenCalledTimes(1)
    await vi.waitFor(() => expect(screen.getByLabelText('Telegram chat ID')).toHaveProperty('value', ''))
  })
})
