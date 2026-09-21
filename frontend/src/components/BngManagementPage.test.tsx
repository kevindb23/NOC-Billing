// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { BngManagementPage } from './BngManagementPage'

const { apiRequest, notify } = vi.hoisted(() => ({ apiRequest: vi.fn(), notify: { info: vi.fn(), success: vi.fn(), error: vi.fn(), promise: vi.fn((promise: Promise<unknown>) => promise) } }))
vi.mock('../lib/api', () => ({ apiRequest }))
vi.mock('../lib/notifications', () => ({ getErrorMessage: (_error: unknown, fallback: string) => fallback, notify }))
vi.mock('./SpeedBoostPanel', () => ({ SpeedBoostPanel: () => <div>Speed Boost</div> }))

afterEach(() => cleanup())

describe('BngManagementPage VLAN settings', () => {
  it('replaces PPPoE with BNG Settings and conditionally shows VLAN type', async () => {
    apiRequest.mockImplementation(async (path: string) => {
      if (path === '/bngs/bng-1') return { data: { public_id: 'bng-1', name: 'BNG', vendor: 'linux', status: 'active', parent_interface: 'ens17', egress_interface: 'ens16' } }
      if (path === '/bngs/bng-1/session-status') return { data: { status: 'stopped' } }
      if (path === '/bngs/bng-1/vlan-syncs') return { data: [] }
      if (path.startsWith('/olts')) return { data: { data: [{ public_id: 'olt-1', name: 'Access OLT', vendor: 'huawei' }] } }
      return { data: [] }
    })

    render(<BngManagementPage token="token" publicId="bng-1" onBack={vi.fn()} />)
    await waitFor(() => expect(screen.getByRole('tab', { name: 'BNG Settings' })).toBeTruthy())
    expect(screen.queryByRole('tab', { name: 'PPPoE' })).toBeNull()

    fireEvent.click(screen.getByRole('tab', { name: 'BNG Settings' }))
    await waitFor(() => expect(screen.getByText(/No VLAN sync rules yet/)).toBeTruthy())
    fireEvent.click(screen.getByRole('button', { name: 'Add VLAN sync' }))
    expect(screen.getByRole('switch', { name: 'Sync VLAN' })).toBeTruthy()
    expect(screen.getByLabelText('VLAN type')).toBeTruthy()

    fireEvent.click(screen.getByRole('switch', { name: 'Sync VLAN' }))
    expect(screen.queryByLabelText('VLAN type')).toBeNull()
  })

  it('saves BNG interfaces as a record without reconciliation', async () => {
    apiRequest.mockImplementation(async (path: string, options?: RequestInit) => {
      if (path === '/bngs/bng-1') return { data: { public_id: 'bng-1', name: 'BNG', vendor: 'linux', status: 'active', parent_interface: null, egress_interface: null } }
      if (path === '/bngs/bng-1/session-status') return { data: { status: 'stopped' } }
      if (path === '/bngs/bng-1/interfaces' && options?.method === 'PATCH') return { data: { public_id: 'bng-1', name: 'BNG', vendor: 'linux', status: 'active', parent_interface: 'ens17', egress_interface: 'ens16' } }
      return { data: [] }
    })

    render(<BngManagementPage token="token" publicId="bng-1" onBack={vi.fn()} />)
    await waitFor(() => expect(screen.getAllByRole('tab', { name: 'BNG Interfaces' }).at(-1)).toBeTruthy())
    fireEvent.click(screen.getAllByRole('tab', { name: 'BNG Interfaces' }).at(-1)!)
    await waitFor(() => expect((screen.getByRole('button', { name: 'Save interfaces' }) as HTMLButtonElement).disabled).toBe(false))
    ;(screen.getByRole('button', { name: 'Save interfaces' }) as HTMLButtonElement).click()

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/bngs/bng-1/interfaces', expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ parent_interface: 'ens17', egress_interface: 'ens16' }) }), 'token'))
    expect(notify.success).toHaveBeenCalledWith('BNG interfaces saved.')
    expect(notify.error).not.toHaveBeenCalled()
  })
})
