// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { OntManagementPage } from './OntManagementPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

describe('OntManagementPage', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('loads the ACS device and exposes provisioning actions', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.endsWith('/management')) return Promise.resolve({
        data: {
          name: 'Living room ONT',
          serial_number: '48575443FAB6E248',
          status: 'online',
          acs_server: { name: 'Primary ACS', status: 'active' },
          device_id: 'device-123',
          manufacturer: { _value: 'Huawei' },
          model: { _value: 'HG8245H' },
          last_inform: '2026-09-19T10:00:00.000Z',
          parameters: {
            wifi_ssid: { _value: 'Home WiFi' },
            lan_status: { _value: 'Up' },
            wlan: {
              band_24: { enabled: { _value: true }, ssid: { _value: 'Home WiFi' }, hide_ssid: { _value: false }, auto_channel: { _value: true } },
              band_5: { enabled: { _value: false }, ssid: { _value: 'Home WiFi 5' }, hide_ssid: { _value: false }, auto_channel: { _value: false }, channel: { _value: 44 } },
            },
            wan: {
              internet: [{ ip_address: '203.0.113.10', connection_status: 'Connected' }],
              tr069: [{ ip_address: '192.0.2.20', connection_status: 'Connected' }],
              connection_request_url: 'http://192.0.2.20:7547/',
            },
          },
        },
      })
      return Promise.resolve({ data: { action: 'refresh_wifi', task: { name: 'refreshObject' } } })
    })

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-1" canManage /></ConfirmProvider>)

    expect(await screen.findByRole('heading', { name: 'Living room ONT' })).toBeTruthy()
    expect(screen.getByText('Primary ACS')).toBeTruthy()
    expect(screen.getByText('Home WiFi')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Reboot ONT' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Refresh Wi-Fi' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Refresh LAN' })).toBeTruthy()
  })

  it('shows WAN and TR-069 IP details in the WAN tab', async () => {
    apiRequest.mockResolvedValue({ data: {
      name: 'ONT 1', serial_number: 'SERIAL', status: 'online', acs_server: { name: 'ACS', status: 'active' }, device_id: 'device-1',
      parameters: { wan: {
        internet: [{ type: 'pppoe', pppoe_username: 'subscriber01', ip_address: '203.0.113.10', subnet_mask: '255.255.255.255', gateway: '203.0.113.1', dns_servers: ['1.1.1.1', '8.8.8.8'], connection_status: 'Connected' }],
        tr069: [{ ip_address: '192.0.2.20', subnet_mask: '255.255.255.0', gateway: '192.0.2.1', dns_servers: ['192.0.2.1'], connection_status: 'Connected' }],
        connection_request_url: 'http://192.0.2.20:7547/',
      } },
    } })

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-1" canManage /></ConfirmProvider>)
    fireEvent.click(await screen.findByRole('tab', { name: 'WAN' }))

    expect(screen.getByText('WAN details')).toBeTruthy()
    expect(screen.getByText('PPPoE username')).toBeTruthy()
    expect(screen.getByText('subscriber01')).toBeTruthy()
    expect(screen.getByText('203.0.113.10')).toBeTruthy()
    expect(screen.getByText('192.0.2.20')).toBeTruthy()
    expect(screen.getByText('http://192.0.2.20:7547/')).toBeTruthy()
  })

  it('separates WLAN and firmware into tabs and queues WLAN changes', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.endsWith('/management')) return Promise.resolve({
        data: {
          name: 'ONT 1', serial_number: 'SERIAL', status: 'online', acs_server: { name: 'ACS', status: 'active' }, device_id: 'device-1',
          parameters: { wlan: { band_24: { enabled: true, ssid: 'Home 24', auto_channel: true }, band_5: { enabled: true, ssid: 'Home 5', auto_channel: false, channel: 44 } } },
        },
      })
      return Promise.resolve({ data: { action: 'update_wifi', task: { name: 'setParameterValues' } } })
    })

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-1" canManage /></ConfirmProvider>)
    fireEvent.click(await screen.findByRole('tab', { name: 'WLAN' }))

    expect(screen.getByText('WLAN settings')).toBeTruthy()
    expect(screen.getByDisplayValue('Home 24')).toBeTruthy()
    expect(screen.getByDisplayValue('Home 5')).toBeTruthy()
    fireEvent.change(screen.getAllByLabelText('Wi-Fi password')[0], { target: { value: 'new-password-24' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save WLAN settings' }))

    expect(apiRequest).toHaveBeenCalledWith('/onts/ont-1/management/tasks', expect.objectContaining({
      method: 'POST',
      body: expect.stringContaining('"action":"update_wifi"'),
    }), 'token')
    const taskCall = apiRequest.mock.calls.find((call: unknown[]) => call[0] === '/onts/ont-1/management/tasks')
    const body = JSON.parse((taskCall?.[1] as { body: string }).body)
    expect(body.wlan.band_24.password).toBe('new-password-24')
    expect(body.wlan.band_5.channel).toBe(44)

    fireEvent.click(screen.getByRole('tab', { name: 'Firmware' }))
    expect(screen.getByLabelText('Firmware image URL')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Queue firmware' })).toBeTruthy()
  })

  it('sends a safe GenieACS task through the backend', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.endsWith('/management')) return Promise.resolve({ data: { name: 'ONT 1', serial_number: 'SERIAL', status: 'online', acs_server: { name: 'ACS', status: 'active' }, device_id: 'device-1', parameters: { upstream_port: 4 } } })
      return Promise.resolve({ data: { action: 'refresh_wifi', task: { name: 'refreshObject' } } })
    })

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-1" canManage /></ConfirmProvider>)
    fireEvent.click(await screen.findByRole('button', { name: 'Refresh Wi-Fi' }))

    expect(apiRequest).toHaveBeenCalledWith('/onts/ont-1/management/tasks', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ action: 'refresh_wifi' }),
    }), 'token')
  })

  it('keeps the management page usable when ACS values are empty', async () => {
    apiRequest.mockRejectedValue(new Error('No ACS device was found for this ONT.'))

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-empty" canManage /></ConfirmProvider>)

    expect(await screen.findByRole('heading', { name: 'ONT management' })).toBeTruthy()
    expect(screen.getByText('ACS management unavailable')).toBeTruthy()
    expect(screen.getByText('Not assigned')).toBeTruthy()
    expect((screen.getByRole('button', { name: 'Refresh device' }) as HTMLButtonElement).disabled).toBe(true)
  })

  it('provides maintenance export and queues an upstream port change', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.endsWith('/management')) return Promise.resolve({ data: { name: 'ONT 1', serial_number: 'SERIAL', status: 'online', acs_server: { name: 'ACS', status: 'active' }, device_id: 'device-1', parameters: { upstream_port: 4 } } })
      return Promise.resolve({ data: { action: 'update_upstream_port', task: { name: 'setParameterValues' } } })
    })

    render(<ConfirmProvider><OntManagementPage token="token" publicId="ont-1" canManage /></ConfirmProvider>)
    fireEvent.click(await screen.findByRole('tab', { name: 'Maintenance' }))

    expect(screen.getByRole('button', { name: 'Export current configuration' })).toBeTruthy()
    expect((screen.getByLabelText('Port') as HTMLSelectElement).value).toBe('lan4')
    fireEvent.change(screen.getByLabelText('Port'), { target: { value: 'lan3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply upstream port' }))

    expect(apiRequest).toHaveBeenCalledWith('/onts/ont-1/management/tasks', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ action: 'update_upstream_port', upstream_port: 'lan3' }),
    }), 'token')
  })
})
