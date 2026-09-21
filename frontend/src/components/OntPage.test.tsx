// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { OntPage } from './OntPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

const renderPage = (rogueProtection = false) => {
  apiRequest.mockImplementation((path: string) => {
    if (path === '/onts/settings') return Promise.resolve({ data: { do_not_allow_rogue_onus: rogueProtection, ont_id_capacity_per_port: 64 } })
    if (path === '/onts/acs-servers') return Promise.resolve({ data: [{ public_id: 'acs-1', name: 'Primary ACS', status: 'active' }] })
    if (path.startsWith('/onts')) return Promise.resolve({ data: { data: [], total: 0, current_page: 1, last_page: 1 } })
    if (path.startsWith('/onus')) return Promise.resolve({ data: { data: [{ public_id: 'onu-1', vendor: 'Huawei', model: 'HG8245H', serial_number: 'HWTC-INVENTORY-1', quantity: 1, status: 'in_stock' }], total: 1 } })
    return Promise.resolve({ data: { data: [{ public_id: 'olt-1', name: 'OLT 01', vendor: 'huawei' }], total: 1 } })
  })
  return render(<ConfirmProvider><OntPage token="token" permissions={['onts.view', 'onts.create', 'onts.update', 'onts.delete', 'onts.discover']} /></ConfirmProvider>)
}

describe('OntPage', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders the empty inventory with add and discover actions', async () => {
    renderPage()
    expect(await screen.findByRole('heading', { name: 'ONTs' })).toBeTruthy()
    expect(screen.getAllByText('No ONTs configured yet.').length).toBeGreaterThan(0)
    expect(screen.getAllByRole('button', { name: 'Add ONT' }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('button', { name: 'Discover ONTs' }).length).toBeGreaterThan(0)
  })

  it('opens the add and discovery forms with the required OLT selector', async () => {
    renderPage()
    fireEvent.click((await screen.findAllByRole('button', { name: 'Add ONT' }))[0])
    expect(screen.getByRole('dialog', { name: 'Add ONT' })).toBeTruthy()
    expect(screen.getByRole('combobox', { name: 'OLT' })).toBeTruthy()
    expect(screen.queryByRole('combobox', { name: /ACS server/ })).toBeNull()
    expect(apiRequest).not.toHaveBeenCalledWith('/onts/acs-servers', expect.anything(), 'token')
    fireEvent.click(screen.getByRole('button', { name: 'Close ONT form' }))
    fireEvent.click((await screen.findAllByRole('button', { name: 'Discover ONTs' }))[0])
    expect(screen.getByRole('dialog', { name: 'Discover ONTs' })).toBeTruthy()
    expect(screen.getByRole('combobox', { name: 'OLT to scan' })).toBeTruthy()
  })

  it('opens settings and uses the ONU inventory serial selector when rogue protection is enabled', async () => {
    renderPage()
    fireEvent.click(await screen.findByRole('button', { name: 'Settings' }))
    expect(screen.getByRole('dialog', { name: 'ONT settings' })).toBeTruthy()
    expect((screen.getByRole('spinbutton', { name: 'Max ONTs per PON / port' }) as HTMLInputElement).value).toBe('64')
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Max ONTs per PON / port' }), { target: { value: '128' } })
    fireEvent.click(screen.getByRole('switch', { name: 'Do not allow rogue ONUs' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save settings' }))
    expect(apiRequest).toHaveBeenCalledWith('/onts/settings', expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ do_not_allow_rogue_onus: true, ont_id_capacity_per_port: 128 }) }), 'token')
  })

  it('replaces the serial textbox with an ONU inventory selector when protection is enabled', async () => {
    renderPage(true)
    fireEvent.click((await screen.findAllByRole('button', { name: 'Add ONT' }))[0])
    expect(screen.getByRole('combobox', { name: 'Serial number' })).toBeTruthy()
    expect(screen.getByRole('option', { name: /HWTC-INVENTORY-1/ })).toBeTruthy()
  })
})
