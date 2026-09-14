// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutersPage } from './RoutersPage'
import { ConfirmProvider } from './ConfirmProvider'
import { apiRequest } from '@/lib/api'
import { notify } from '@/lib/notifications'

vi.mock('@/lib/api', () => ({ apiRequest: vi.fn() }))

const router = {
  public_id: '01router', name: 'Edge 01', hostname: 'edge.example.net', management_ip: '192.0.2.1',
  vendor: 'mikrotik', model: 'CCR2004', software_version: '7.15', serial_number: 'SN-1',
  driver: 'mikrotik_router', preferred_transport: 'api', status: 'active', last_contact_at: null,
  last_synchronized_at: '2026-09-13T10:00:00Z', capabilities: ['connection_test', 'system_info'], notes: 'Core edge',
}

const detail = { data: router }

function apiError(status: number, body: unknown) {
  return Object.assign(new Error(typeof body === 'object' && body !== null && 'message' in body ? String(body.message) : 'Request failed'), { status, body })
}

describe('RoutersPage', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks(); vi.mocked(apiRequest).mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } }) })

  it('renders loading, empty, error, and populated inventory states', async () => {
    let resolve!: (value: unknown) => void
    vi.mocked(apiRequest).mockReturnValueOnce(new Promise<unknown>(value => { resolve = value }))
    render(<RoutersPage token="token" permissions={['routers.view']} />)
    expect(screen.getByText('Loading routers…')).toBeTruthy()
    resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    expect(await screen.findByText('No routers found.')).toBeTruthy()

    cleanup()
    vi.mocked(apiRequest).mockRejectedValueOnce(new Error('Network unavailable'))
    render(<RoutersPage token="token" permissions={['routers.view']} />)
    expect(await screen.findByText('Network unavailable')).toBeTruthy()

    cleanup()
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test', 'routers.create', 'routers.update', 'routers.delete']} />)
    expect(await screen.findByText('Edge 01')).toBeTruthy()
    expect(screen.getByText('Vendor / model')).toBeTruthy()
    expect(screen.getByText('Management endpoint')).toBeTruthy()
    expect(screen.getByText('Driver / transport')).toBeTruthy()
    expect(screen.getByText('Last contact')).toBeTruthy()
  })

  it('wires create, view, connection test, and hides test without permission', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    render(<ConfirmProvider><RoutersPage token="token" permissions={['routers.view', 'routers.create']} /></ConfirmProvider>)
    await screen.findByText('Edge 01')
    expect(screen.queryByRole('button', { name: /test connection for edge 01/i })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Edit Edge 01' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Archive Edge 01' })).toBeNull()
    fireEvent.click(screen.getByRole('button', { name: /new router/i }))
    expect(screen.getByLabelText('Vendor')).toBeTruthy()
    expect(screen.getByLabelText('API token')).toBeTruthy()
  })

  it('submits the router create payload through the API', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: router })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.create']} />)
    await screen.findByText('No routers found.')
    fireEvent.click(screen.getByRole('button', { name: /new router/i }))
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New edge' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create' }))
    await vi.waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/routers', expect.objectContaining({ method: 'POST', body: expect.stringContaining('New edge') }), 'token'))
  })

  it('posts a connection test and renders the normalized result', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { status: 'not_configured', message: 'No router transport is configured.', driver: 'mikrotik_router' } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: /test connection for edge 01/i }))
    expect((await screen.findAllByText('Not Configured')).length).toBeGreaterThan(0)
    expect(apiRequest).toHaveBeenCalledWith('/routers/01router/connection-test', { method: 'POST' }, 'token')
  })

  it('polls queued connection tests until the operation completes', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { public_id: '01operation', operation: 'test_connection', status: 'queued' } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { public_id: '01operation', operation: 'test_connection', status: 'succeeded', result: { status: 'connected', driver: 'mikrotik_router', message: 'Connected' } } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: /test connection for edge 01/i }))
    expect(await screen.findByText('Connected')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/routers/01router/operations/01operation', {}, 'token')
  })

  it('loads system info from the view modal and renders a safe normalized result', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce(detail)
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { status: 'connected', vendor: 'mikrotik', model: 'CCR2004', serial_number: 'SN-1', software_version: '7.15', uptime_seconds: 3600, details: { password: 'do-not-render' } } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'View Edge 01' }))
    expect(await screen.findByText('RECORD DETAILS')).toBeTruthy()
    expect(screen.getByText('Connection Test')).toBeTruthy()
    expect(screen.getByText('System Information')).toBeTruthy()
    expect(screen.getAllByText('Supported').length).toBe(2)
    fireEvent.click(screen.getByRole('button', { name: /load system information/i }))
    expect(await screen.findByText('Connected')).toBeTruthy()
    expect(screen.getAllByText('CCR2004').length).toBeGreaterThan(0)
    expect(screen.queryByText('do-not-render')).toBeNull()
    expect(apiRequest).toHaveBeenCalledWith('/routers/01router/system-info', {}, 'token')
  })

  it('shows an action-specific alert when system info is rejected', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce(detail)
    vi.mocked(apiRequest).mockRejectedValueOnce(apiError(500, { message: 'system info details leaked' }))
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'View Edge 01' }))
    fireEvent.click(await screen.findByRole('button', { name: /load system information/i }))
    expect(await screen.findByText('Unable to load system information')).toBeTruthy()
    expect(screen.getByText('The system information request failed.')).toBeTruthy()
    expect(screen.queryByText('The router connection test failed.')).toBeNull()
    expect(screen.queryByText('system info details leaked')).toBeNull()
  })

  it('preserves an unsupported connection response as Unsupported without raw payload text', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockRejectedValueOnce(apiError(422, { message: 'Connection testing is not supported by this driver.', data: { status: 'unsupported', message: 'secret raw payload' } }))
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: /test connection for edge 01/i }))
    expect(await screen.findByText('Unsupported')).toBeTruthy()
    expect(screen.queryByText('secret raw payload')).toBeNull()
  })

  it('shows a loading state while a connection test is pending', async () => {
    let resolve!: (value: unknown) => void
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockReturnValueOnce(new Promise<unknown>(value => { resolve = value }) as Promise<unknown>)
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: /test connection for edge 01/i }))
    expect(await screen.findByRole('button', { name: 'Testing…' })).toBeTruthy()
    resolve({ data: { status: 'connected', message: 'ignored' } })
    expect(await screen.findByText('Connected')).toBeTruthy()
  })

  it('renders failed connection tests with normalized copy', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockRejectedValueOnce(apiError(422, { message: 'driver secret leaked', data: { status: 'failed', message: 'driver secret leaked' } }))
    const notifyError = vi.spyOn(notify, 'error')
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.test']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: /test connection for edge 01/i }))
    expect(await screen.findByText('Failed')).toBeTruthy()
    expect(screen.getByText('The router connection test failed.')).toBeTruthy()
    expect(notifyError).toHaveBeenCalledWith('Unable to test router connection.')
    expect(screen.queryByText('driver secret leaked')).toBeNull()
  })

  it('shows view and archive errors with action-specific alerts', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockRejectedValueOnce(new Error('detail unavailable'))
    render(<ConfirmProvider><RoutersPage token="token" permissions={['routers.view', 'routers.delete']} /></ConfirmProvider>)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'View Edge 01' }))
    expect(await screen.findByText('Unable to view router')).toBeTruthy()

    cleanup()
    vi.clearAllMocks()
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockRejectedValueOnce(new Error('archive unavailable'))
    render(<ConfirmProvider><RoutersPage token="token" permissions={['routers.view', 'routers.delete']} /></ConfirmProvider>)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'Archive Edge 01' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Archive' }))
    expect(await screen.findByText('Unable to archive router')).toBeTruthy()
  })

  it('permanently deletes a router after confirmation', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    render(<ConfirmProvider><RoutersPage token="token" permissions={['routers.view', 'routers.delete']} /></ConfirmProvider>)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'Delete Edge 01 permanently' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Delete permanently' }))
    await vi.waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/routers/01router?permanent=1', { method: 'DELETE' }, 'token'))
  })

  it('resets edit values when switching rows but preserves typed values after save errors', async () => {
    const secondRouter = { ...router, public_id: '02router', name: 'Edge 02' }
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [router, secondRouter], current_page: 1, last_page: 1, total: 2 } })
    vi.mocked(apiRequest).mockRejectedValueOnce(new Error('validation failed'))
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.update']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'Edit Edge 01' }))
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Typed value' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))
    expect(await screen.findByText('validation failed')).toBeTruthy()
    expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe('Typed value')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    fireEvent.click(screen.getByRole('button', { name: 'Edit Edge 02' }))
    expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe('Edge 02')
  })

  it('renders transport-specific credential fields without MOCK and clears fields when transport changes', async () => {
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.create']} />)
    await screen.findByText('No routers found.')
    fireEvent.click(screen.getByRole('button', { name: /new router/i }))
    const transport = screen.getByLabelText('Preferred transport')
    expect(screen.queryByRole('option', { name: 'MOCK' })).toBeNull()
    fireEvent.change(transport, { target: { value: 'ssh' } })
    expect(screen.getByLabelText('Username')).toBeTruthy()
    expect(screen.getByLabelText('Password')).toBeTruthy()
    expect((screen.getByLabelText('SSH port') as HTMLInputElement).value).toBe('22')
    fireEvent.change(screen.getByLabelText('Username'), { target: { value: 'netadmin' } })
    fireEvent.change(transport, { target: { value: 'api' } })
    expect(screen.queryByLabelText('Username')).toBeNull()
    expect(screen.getByLabelText('API token')).toBeTruthy()
  })

  it('submits credentials in a nested profile and never displays a returned secret', async () => {
    const configuredRouter = { ...router, credential_configured: true, credential_profile: { name: 'primary', auth_type: 'password', version: 2, connection_metadata: { port: 22 } } }
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [configuredRouter], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: configuredRouter })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.update']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'Edit Edge 01' }))
    expect(screen.getByText('Configured')).toBeTruthy()
    expect(screen.queryByText('secret-password')).toBeNull()
    fireEvent.change(screen.getByLabelText('Preferred transport'), { target: { value: 'ssh' } })
    fireEvent.change(screen.getByLabelText('Username'), { target: { value: 'netadmin' } })
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret-password' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))
    await vi.waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/routers/01router', expect.objectContaining({ method: 'PUT', body: expect.stringContaining('credential_profile') }), 'token'))
    const request = vi.mocked(apiRequest).mock.calls.find(([path, options]) => path === '/routers/01router' && options?.method === 'PUT')
    expect(String(request?.[1]?.body)).toContain('netadmin')
  })

  it('shows generic operation buttons only for supported capabilities and permissions', async () => {
    const operationRouter = { ...router, capabilities: ['get_interfaces', 'preview_configuration'] }
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { data: [operationRouter], current_page: 1, last_page: 1, total: 1 } })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: operationRouter })
    vi.mocked(apiRequest).mockResolvedValueOnce({ data: { operation: 'get_interfaces', status: 'queued', correlation_id: 'corr-1' } })
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.monitor']} />)
    await screen.findByText('Edge 01')
    fireEvent.click(screen.getByRole('button', { name: 'View Edge 01' }))
    expect(await screen.findByText('Router operations')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Interfaces' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Preview' })).toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'Interfaces' }))
    expect(await screen.findByText('corr-1')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/routers/01router/operations', expect.objectContaining({ method: 'POST', body: expect.stringContaining('get_interfaces') }), 'token')
  })
})
