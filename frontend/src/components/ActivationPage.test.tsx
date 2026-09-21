// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ActivationPage } from './ActivationPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

const emptyResponses = {
  '/customers?per_page=100': { data: { data: [] } },
  '/plans?per_page=100': { data: { data: [] } },
  '/onts?per_page=100': { data: { data: [] } },
  '/olts?per_page=100': { data: { data: [] } },
  '/activations?per_page=100': { data: { data: [] } },
}

describe('ActivationPage', () => {
  beforeEach(() => {
    apiRequest.mockImplementation((path: string) => Promise.resolve(emptyResponses[path as keyof typeof emptyResponses] || { data: { data: [] } }))
  })
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders a useful empty state when no activation records exist', async () => {
    render(<ActivationPage token="token" isSuperadmin />)

    expect(await screen.findByRole('heading', { name: /No activations yet/ })).toBeTruthy()
    expect(screen.getByText('Choose an OLT once, then bind its service setup and profile package to a subscriber.')).toBeTruthy()
    expect(screen.getAllByRole('button', { name: 'New activation' }).every(button => (button as HTMLButtonElement).disabled)).toBe(true)
  })

  it('opens the single activation form when the required inventories are available', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path === '/customers?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'customer-1', customer_number: 'CUS-1', legal_name: 'Test subscriber' }] } })
      if (path === '/plans?per_page=100') return Promise.resolve({ data: { data: [{ id: 1, name: 'Home 100', code: 'HOME-100', status: 'active', versions: [{ id: 2, version: 1, status: 'active', recurring_price_minor: 10000, currency: 'PHP', download_kbps: 100000, upload_kbps: 50000 }] }] } })
      if (path === '/onts?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'ont-1', name: 'ONT-1', serial_number: 'HWTC0001', status: 'discovered', olt: { public_id: 'olt-1', name: 'OLT-1' } }] } })
      if (path === '/olts?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'olt-1', name: 'OLT-1', vendor: 'Huawei' }] } })
      if (path === '/activation-options?olt_public_id=olt-1') return Promise.resolve({ data: { olt: { public_id: 'olt-1', name: 'OLT-1' }, setup: { type: 'vlan', vlan_provisions: [{ id: 10, name: 'Internet', vlan_id: 120 }], qinq_provisions: [] }, profiles: { dba: [], ont_line: [], ont_service: [], ont_wan: [], tr069: [] }, presets: [{ public_id: 'preset-1', name: 'Home standard' }] } })
      return Promise.resolve({ data: { data: [] } })
    })

    render(<ActivationPage token="token" isSuperadmin />)
    fireEvent.click(await screen.findByRole('button', { name: 'New activation' }))

    expect(screen.getByRole('heading', { name: 'New activation' })).toBeTruthy()
    expect(screen.getAllByLabelText(/\bOLT\b/)[0]).toBeTruthy()
    expect(screen.getByLabelText(/Subscriber/)).toBeTruthy()
    expect(screen.queryByLabelText(/C-VLAN/)).toBeNull()
    expect(screen.queryByLabelText(/S-VLAN/)).toBeNull()
  })

  it('shows the C-VLAN dropdown automatically for a QinQ OLT', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path === '/customers?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'customer-1', customer_number: 'CUS-1', legal_name: 'Test subscriber' }] } })
      if (path === '/plans?per_page=100') return Promise.resolve({ data: { data: [{ id: 1, name: 'Home 100', code: 'HOME-100', status: 'active', versions: [{ id: 2, version: 1, status: 'active', recurring_price_minor: 10000, currency: 'PHP', download_kbps: 100000, upload_kbps: 50000 }] }] } })
      if (path === '/onts?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'ont-1', name: 'ONT-1', serial_number: 'HWTC0001', status: 'discovered', olt: { public_id: 'olt-1', name: 'OLT-1' } }] } })
      if (path === '/olts?per_page=100') return Promise.resolve({ data: { data: [{ public_id: 'olt-1', name: 'OLT-1', vendor: 'Huawei' }] } })
      if (path === '/activation-options?olt_public_id=olt-1') return Promise.resolve({ data: { olt: { public_id: 'olt-1', name: 'OLT-1' }, setup: { type: 'qinq', vlan_provisions: [], qinq_provisions: [{ id: 10, name: 'Home 3001', outer_vlan: 50, inner_vlan: 3001 }] }, profiles: { dba: [], ont_line: [], ont_service: [], ont_wan: [], tr069: [] }, presets: [] } })
      if (path === '/activation-preview') return Promise.resolve({ data: { commands: [], message: 'Preview', supported: false } })
      return Promise.resolve({ data: { data: [] } })
    })

    render(<ActivationPage token="token" isSuperadmin />)
    fireEvent.click(await screen.findByRole('button', { name: 'New activation' }))
    fireEvent.change(screen.getAllByLabelText(/\bOLT\b/)[0], { target: { value: 'olt-1' } })

    expect(await screen.findByLabelText(/C-VLAN/)).toBeTruthy()
    expect(screen.getByRole('option', { name: /C-VLAN 3001 · S-VLAN 50/ })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Preview OLT commands' })).toBeTruthy()
    expect(apiRequest).not.toHaveBeenCalledWith('/activation-preview', expect.anything(), 'token')

    fireEvent.change(screen.getByLabelText(/Subscriber/), { target: { value: 'customer-1' } })
    fireEvent.change(screen.getByLabelText(/Plan/), { target: { value: '2' } })
    fireEvent.change(screen.getByLabelText(/ONT device/), { target: { value: 'ont-1' } })
    fireEvent.change(screen.getByLabelText(/C-VLAN/), { target: { value: '10' } })
    fireEvent.click(screen.getByRole('button', { name: 'Preview OLT commands' }))

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/activation-preview', expect.objectContaining({ method: 'POST' }), 'token'))
  })
})
