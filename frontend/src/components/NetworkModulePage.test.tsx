// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { NetworkModulePage } from './NetworkModulePage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

describe('NetworkModulePage ONT deep links', () => {
  afterEach(() => {
    cleanup()
    window.history.pushState({}, '', '/ont')
    vi.clearAllMocks()
  })

  it('renders the management page for a direct ONT management URL', async () => {
    window.history.pushState({}, '', '/ont/ont-1/manage')
    apiRequest.mockResolvedValue({
      data: {
        name: 'Living room ONT',
        serial_number: 'SERIAL',
        status: 'online',
        device_id: 'device-1',
        acs_server: { name: 'Primary ACS', status: 'active' },
        parameters: {},
      },
    })

    render(<ConfirmProvider><NetworkModulePage module="ONT" token="token" permissions={['onts.view', 'onts.update']} /></ConfirmProvider>)

    expect(await screen.findByRole('heading', { name: 'Living room ONT' })).toBeTruthy()
  })

  it('keeps a direct management URL visible when the ACS has no values', async () => {
    window.history.pushState({}, '', '/ont/ont-empty/manage')
    apiRequest.mockRejectedValue(new Error('The ONT has not informed the ACS yet.'))

    render(<ConfirmProvider><NetworkModulePage module="ONT" token="token" permissions={['onts.view', 'onts.update']} /></ConfirmProvider>)

    expect(await screen.findByRole('heading', { name: 'ONT management' })).toBeTruthy()
    expect(screen.getByText('The ONT has not informed the ACS yet.')).toBeTruthy()
  })
})
