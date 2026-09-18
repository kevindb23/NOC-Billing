// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { EmailPage } from './EmailPage'
import { sendTestEmail, updateEmailSettings } from '@/lib/email'

vi.mock('@/lib/email', () => ({
  getEmailSettings: vi.fn(),
  updateEmailSettings: vi.fn(),
  sendTestEmail: vi.fn(),
}))

const settings = { provider: 'gmail' as const, host: 'smtp.gmail.com', port: 587, encryption: 'tls' as const, username: 'billing@example.com', password_configured: true, from_name: 'ISP Billing', from_email: 'billing@example.com' }

describe('email page', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders SMTP and sender identity settings for a superadmin', () => {
    render(<EmailPage token="token" permissions={[]} isSuperadmin settings={settings} onSaved={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'Email' })).toBeTruthy()
    expect((screen.getByLabelText('SMTP host') as HTMLInputElement).value).toBe('smtp.gmail.com')
    expect((screen.getByLabelText('From email') as HTMLInputElement).value).toBe('billing@example.com')
  })

  it('saves settings and sends a test email', async () => {
    vi.mocked(updateEmailSettings).mockResolvedValue({ data: { settings } })
    vi.mocked(sendTestEmail).mockResolvedValue({ data: { message: 'Test email sent.' } })
    render(<EmailPage token="token" permissions={['email.view', 'email.update', 'email.test']} settings={settings} onSaved={vi.fn()} />)

    fireEvent.change(screen.getByLabelText('From name'), { target: { value: 'Acme Billing' } })
    fireEvent.click(screen.getByRole('button', { name: /save changes/i }))
    await vi.waitFor(() => expect(updateEmailSettings).toHaveBeenCalled())
    fireEvent.change(screen.getByLabelText('Test email recipient'), { target: { value: 'test@example.com' } })
    fireEvent.click(screen.getByRole('button', { name: /send test email/i }))
    await vi.waitFor(() => expect(sendTestEmail).toHaveBeenCalled())
    expect(sendTestEmail).toHaveBeenCalledWith('test@example.com', 'token')
  })
})
