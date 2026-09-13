// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutersPage } from './RoutersPage'
import { ConfirmProvider } from './ConfirmProvider'
import { apiRequest } from '@/lib/api'

vi.mock('@/lib/api', () => ({ apiRequest: vi.fn() }))

const router = {
  public_id: '01router', name: 'Edge 01', hostname: 'edge.example.net', management_ip: '192.0.2.1',
  vendor: 'mikrotik', model: 'CCR2004', software_version: '7.15', serial_number: 'SN-1',
  driver: 'mikrotik_router', preferred_transport: 'api', status: 'active', last_contact_at: null,
  last_synchronized_at: '2026-09-13T10:00:00Z', capabilities: { connection_test: 'supported', system_info: 'supported' }, notes: 'Core edge',
}

describe('RoutersPage', () => {
  beforeEach(() => { cleanup(); vi.clearAllMocks(); vi.mocked(apiRequest).mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } }) })

  it('renders loading, empty, error, and populated inventory states', async () => {
    let resolve!: (value: unknown) => void
    vi.mocked(apiRequest).mockReturnValueOnce(new Promise<unknown>(value => { resolve = value }) as Promise<any>)
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
    fireEvent.click(screen.getByRole('button', { name: /new router/i }))
    expect(screen.getByLabelText('Vendor')).toBeTruthy()
    expect(screen.queryByLabelText(/password|token|community|private key/i)).toBeNull()
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
})
