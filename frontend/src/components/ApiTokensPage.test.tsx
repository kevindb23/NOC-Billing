// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiTokensPage } from './ApiTokensPage'
import { ConfirmProvider } from './ConfirmProvider'
import { createApiToken, listApiTokens } from '@/lib/apiTokens'

vi.mock('@/lib/apiTokens', () => ({ listApiTokens: vi.fn(), createApiToken: vi.fn(), revokeApiToken: vi.fn() }))

describe('api tokens page', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks(); vi.mocked(listApiTokens).mockResolvedValue({ data: [] }) })

  it('shows token generation and API documentation access for superadmins', async () => {
    render(<ConfirmProvider><ApiTokensPage token="token" permissions={[]} isSuperadmin /></ConfirmProvider>)
    expect(await screen.findByRole('heading', { name: 'API Tokens' })).toBeTruthy()
    expect(screen.getByRole('link', { name: /API docs/i }).getAttribute('href')).toBe('/api/v1/docs')
    expect(screen.getByLabelText('Token name')).toBeTruthy()
  })

  it('generates a token and displays the secret once', async () => {
    vi.mocked(createApiToken).mockResolvedValue({ data: { token: 'plain-secret', token_record: { id: 1, name: 'Integration', abilities: ['customers.view'], last_used_at: null, expires_at: null, created_at: '2026-09-12' } } })
    render(<ConfirmProvider><ApiTokensPage token="token" permissions={['api-tokens.view', 'api-tokens.create']} /></ConfirmProvider>)
    await screen.findByRole('heading', { name: 'API Tokens' })
    fireEvent.change(screen.getByLabelText('Token name'), { target: { value: 'Integration' } })
    fireEvent.click(screen.getByRole('button', { name: /generate token/i }))
    expect(await screen.findByDisplayValue('plain-secret')).toBeTruthy()
    expect(createApiToken).toHaveBeenCalled()
  })

  it('supports tokens with no expiration', async () => {
    render(<ConfirmProvider><ApiTokensPage token="token" permissions={['api-tokens.view', 'api-tokens.create']} /></ConfirmProvider>)
    await screen.findByRole('heading', { name: 'API Tokens' })
    const expiry = screen.getByLabelText('Expires on')
    fireEvent.click(screen.getByLabelText('No expiration'))
    expect(expiry).toHaveProperty('disabled', true)
  })
})
