// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AcsServerPage } from './AcsServerPage'

const { apiRequest, notify } = vi.hoisted(() => ({ apiRequest: vi.fn(), notify: { success: vi.fn(), error: vi.fn() } }))
vi.mock('../lib/api', () => ({ apiRequest }))
vi.mock('../lib/notifications', () => ({
  getErrorMessage: (error: unknown, fallback: string) => error instanceof Error ? error.message : fallback,
  notify,
}))

describe('AcsServerPage', () => {
  beforeEach(() => {
    apiRequest.mockReset()
  })
  afterEach(() => cleanup())

  it('shares an in-flight inventory request across a remount', async () => {
    let resolveRequest: ((value: { data: Array<{ public_id: string }> }) => void) | undefined
    apiRequest.mockReturnValue(new Promise(resolve => { resolveRequest = resolve }))
    const first = render(<AcsServerPage token="token" isSuperadmin />)
    first.unmount()
    render(<AcsServerPage token="token" isSuperadmin />)

    expect(apiRequest).toHaveBeenCalledTimes(1)
    resolveRequest?.({ data: [] })
    await waitFor(() => expect(screen.getByText('No ACS servers configured.')).toBeTruthy())
  })

  it('shows an empty ACS table and opens the new server form', async () => {
    apiRequest.mockResolvedValue({ data: [] })
    render(<AcsServerPage token="token" isSuperadmin />)

    await waitFor(() => expect(screen.getByText('No ACS servers configured.')).toBeTruthy())
    fireEvent.click(screen.getByRole('button', { name: 'New ACS server' }))
    expect(screen.getAllByText('New ACS server').length).toBeGreaterThan(1)
    expect(screen.getByLabelText('API URL')).toBeTruthy()
    expect(screen.getByLabelText('API username')).toBeTruthy()
    expect(screen.getByLabelText('API password')).toBeTruthy()
    expect(screen.getByLabelText('SSH username')).toBeTruthy()
    expect(screen.getByLabelText('SSH password')).toBeTruthy()
    expect(screen.getByLabelText('SSH port')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Test SSH connection' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Test ACS API' })).toBeTruthy()
  })

  it('reports ACS test results through Sonner notifications', async () => {
    apiRequest
      .mockResolvedValueOnce({ data: [{ public_id: 'acs-1', name: 'ACS1', api_url: 'http://acs:7557', api_username: 'acs', transport: 'cwmp', status: 'active', ssh_username: 'root', ssh_port: 22 }] })
      .mockResolvedValueOnce({ data: { message: 'ACS API responded successfully.' } })
    render(<AcsServerPage token="token" isSuperadmin />)

    await waitFor(() => expect(screen.getByRole('button', { name: 'Test ACS API for ACS1' })).toBeTruthy())
    fireEvent.click(screen.getByRole('button', { name: 'Test ACS API for ACS1' }))

    await waitFor(() => expect(notify.success).toHaveBeenCalledWith('ACS API responded successfully.'))
    expect(screen.queryByText('ACS API test successful')).toBeNull()
  })

  it('opens ACS settings and saves the password complexity value', async () => {
    apiRequest
      .mockResolvedValueOnce({ data: [{ public_id: 'acs-1', name: 'ACS1', api_url: 'http://acs:7557', api_username: 'acs', transport: 'cwmp', status: 'active', ssh_username: 'root', ssh_port: 22 }] })
      .mockResolvedValueOnce({ data: { minimum_password_length: 8 } })
      .mockResolvedValueOnce({ data: { message: 'Password complexity updated and GenieACS UI restarted.' } })
    render(<AcsServerPage token="token" isSuperadmin />)

    fireEvent.click(await screen.findByRole('button', { name: 'Open settings for ACS1' }))
    expect(await screen.findByRole('heading', { name: 'Password Complexity' })).toBeTruthy()
    expect((screen.getByLabelText('Minimum Password Length') as HTMLInputElement).value).toBe('8')
    fireEvent.change(screen.getByLabelText('Minimum Password Length'), { target: { value: '12' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save password policy' }))

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/acs-servers/acs-1/settings/password-complexity', expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ minimum_password_length: 12 }) }), 'token'))
    expect(notify.success).toHaveBeenCalledWith('Password complexity updated and GenieACS UI restarted.')
  })
})
