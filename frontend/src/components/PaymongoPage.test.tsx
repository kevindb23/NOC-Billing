// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PaymongoPage } from './PaymongoPage'
import { createTestPaymongoPayment, getPaymongoSettings, testPaymongo, updatePaymongoSettings } from '@/lib/paymongo'

vi.mock('@/lib/paymongo', () => ({ getPaymongoSettings: vi.fn(), updatePaymongoSettings: vi.fn(), testPaymongo: vi.fn(), createTestPaymongoPayment: vi.fn() }))

describe('paymongo page', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders gateway settings', () => {
    render(<PaymongoPage token="token" permissions={[]} isSuperadmin settings={{ enabled: false, environment: 'test', public_key: '', secret_key_configured: false, webhook_secret_configured: false }} onSaved={vi.fn()} />)
    expect(screen.getByRole('heading', { name: 'Paymongo' })).toBeTruthy()
    expect(screen.getByLabelText('Public key')).toBeTruthy()
  })

  it('saves settings and tests the connection', async () => {
    vi.mocked(updatePaymongoSettings).mockResolvedValue({ data: { settings: { enabled: true, environment: 'test', public_key: 'pk_test', secret_key_configured: true, webhook_secret_configured: true } } })
    vi.mocked(testPaymongo).mockResolvedValue({ data: { message: 'Paymongo configuration is valid.' } })
    vi.mocked(getPaymongoSettings).mockResolvedValue({ data: { settings: { enabled: false, environment: 'test', public_key: '', secret_key_configured: false, webhook_secret_configured: false } } })
    render(<PaymongoPage token="token" permissions={['paymongo.view', 'paymongo.update', 'paymongo.test']} settings={{ enabled: false, environment: 'test', public_key: '', secret_key_configured: false, webhook_secret_configured: false }} onSaved={vi.fn()} />)
    fireEvent.change(screen.getByLabelText('Public key'), { target: { value: 'pk_test' } })
    fireEvent.click(screen.getByRole('button', { name: /save changes/i }))
    await vi.waitFor(() => expect(updatePaymongoSettings).toHaveBeenCalled())
    fireEvent.click(screen.getByRole('button', { name: /test connection/i }))
    await vi.waitFor(() => expect(testPaymongo).toHaveBeenCalledWith('token'))
  })

  it('opens the test payment modal and creates a payment intent', async () => {
    vi.mocked(createTestPaymongoPayment).mockResolvedValue({ data: { id: 'cs_test_123', status: 'active', checkout_url: 'https://checkout.paymongo.com/cs_test_123', message: 'Test checkout session created.' } })
    render(<PaymongoPage token="token" permissions={['paymongo.view', 'paymongo.test']} settings={{ enabled: true, environment: 'test', public_key: 'pk_test', secret_key_configured: true, webhook_secret_configured: false }} />)
    fireEvent.click(screen.getByRole('button', { name: /test payment/i }))
    expect(screen.getByRole('dialog', { name: /create test payment/i })).toBeTruthy()
    fireEvent.change(screen.getByLabelText('Customer email'), { target: { value: 'customer@example.com' } })
    fireEvent.click(screen.getByRole('button', { name: /create test payment/i }))
    await vi.waitFor(() => expect(createTestPaymongoPayment).toHaveBeenCalledWith({ amount: 10000, currency: 'PHP', customer_email: 'customer@example.com', description: 'ISP Billing test payment' }, 'token'))
  })
})
