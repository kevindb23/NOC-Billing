// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { OltManagementPage } from './OltManagementPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

const management = {
  name: 'Access OLT', vendor: 'huawei', model: 'MA5800', management_endpoint: '10.0.10.157',
  preferred_transport: 'ssh', status: 'connected', vlan_provisions: [], qinq_provisions: [],
  dba_profiles: [], ont_service_profiles: [], ont_wan_profiles: [], ont_tr069_server_profiles: [], terminal_users: [],
}

describe('OltManagementPage Users tab', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders an empty Users state and opens the terminal-user form', async () => {
    apiRequest.mockResolvedValue({ data: management })
    render(<ConfirmProvider><OltManagementPage token="token" publicId="olt-1" isSuperadmin onBack={() => undefined} /></ConfirmProvider>)

    fireEvent.click(await screen.findByRole('tab', { name: /Users/ }))
    expect(screen.getByText('No terminal users configured.')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'New terminal user' }))
    expect(screen.getByRole('dialog', { name: 'New terminal user' })).toBeTruthy()
    expect(screen.getByRole('textbox', { name: 'Username' })).toBeTruthy()
    expect(screen.getByLabelText('Password')).toBeTruthy()
    expect(screen.getByRole('combobox', { name: 'User profile' })).toBeTruthy()
    expect(screen.getByRole('spinbutton', { name: 'Privilege level' })).toBeTruthy()
  })

  it('renders user metadata without exposing a password', async () => {
    apiRequest.mockResolvedValue({ data: { ...management, terminal_users: [{ id: 1, username: 'testuser', profile_name: 'root', privilege_level: 3, reenter_limit: 1, status: 'applied', appended_info: 'Test account' }] } })
    render(<ConfirmProvider><OltManagementPage token="token" publicId="olt-1" isSuperadmin onBack={() => undefined} /></ConfirmProvider>)

    fireEvent.click(await screen.findByRole('tab', { name: /Users/ }))
    expect(screen.getAllByText('testuser').length).toBeGreaterThan(0)
    expect(screen.getByText('Administrator')).toBeTruthy()
    expect(screen.queryByText('TestUser#2026!')).toBeNull()
  })
})

describe('OltManagementPage ONT line profile form', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('switches the VLAN selector between normal VLANs and QinQ C-VLANs', async () => {
    apiRequest.mockResolvedValue({ data: {
      ...management,
      vlan_provisions: [{ id: 1, vlan_id: 100, name: 'Internet VLAN', service_mode: 'internet', status: 'applied' }],
      qinq_provisions: [{ id: 2, qinq_type: 'c_vlan', inner_vlan: 200, name: 'Customer VLAN', status: 'applied' }],
      dba_profiles: [{ id: 3, profile_id: 10, bandwidth_mbps: 100, profile_name: 'DBA_100', status: 'applied' }],
    } })
    render(<ConfirmProvider><OltManagementPage token="token" publicId="olt-1" isSuperadmin onBack={() => undefined} /></ConfirmProvider>)

    fireEvent.click(await screen.findByRole('tab', { name: /Profiles/ }))
    fireEvent.click(screen.getByRole('tab', { name: /ONT line profile/ }))
    fireEvent.click(screen.getByRole('button', { name: 'New line profile' }))

    const vlanType = screen.getByRole('combobox', { name: 'VLAN type for ONT line profile' })
    expect(screen.getByRole('option', { name: /C-VLAN 200/ })).toBeTruthy()
    fireEvent.change(vlanType, { target: { value: 'vlan' } })
    expect(screen.getByRole('option', { name: /VLAN 100/ })).toBeTruthy()
    expect(screen.queryByRole('option', { name: /C-VLAN 200/ })).toBeNull()
  })

  it('shows ONT line profile management controls with the Huawei IP index meanings', async () => {
    apiRequest.mockResolvedValue({ data: {
      ...management,
      vlan_provisions: [
        { id: 1, vlan_id: 25, name: 'TR069', service_mode: 'tr069', status: 'applied' },
      ],
      qinq_provisions: [{ id: 2, qinq_type: 'c_vlan', inner_vlan: 200, name: 'Customer VLAN', status: 'applied' }],
      dba_profiles: [{ id: 3, profile_id: 15, bandwidth_mbps: 100, profile_name: 'DBA_100', status: 'applied' }],
    } })
    render(<ConfirmProvider><OltManagementPage token="token" publicId="olt-1" isSuperadmin onBack={() => undefined} /></ConfirmProvider>)

    fireEvent.click(await screen.findByRole('tab', { name: /Profiles/ }))
    fireEvent.click(screen.getByRole('tab', { name: /ONT line profile/ }))
    fireEvent.click(screen.getByRole('button', { name: 'New line profile' }))

    expect(screen.getByRole('switch', { name: 'Enable TR-069 management' })).toBeTruthy()
    expect(screen.getByRole('switch', { name: 'Enable OMCC encryption' })).toBeTruthy()
    expect(screen.getByRole('option', { name: '1 - TR-069 Management' })).toBeTruthy()
    expect(screen.getByRole('option', { name: '3 - Other services (IPTV, TR-369)' })).toBeTruthy()
  })

  it('previews ONT line profile commands from the modal without submitting the record', async () => {
    const data = {
      ...management,
      vlan_provisions: [{ id: 1, vlan_id: 25, name: 'TR069', service_mode: 'tr069', status: 'applied' }],
      qinq_provisions: [{ id: 2, qinq_type: 'c_vlan', inner_vlan: 200, name: 'Customer VLAN', status: 'applied' }],
      dba_profiles: [{ id: 3, profile_id: 15, bandwidth_mbps: 100, profile_name: 'DBA_100', status: 'applied' }],
    }
    apiRequest.mockImplementation((path: string) => path.includes('/qinq/preview')
      ? Promise.resolve({ data: { commands: ['config', 'ont-lineprofile gpon profile-id 17 profile-name "LP_CVLAN_200"', 'save'] } })
      : Promise.resolve({ data }))
    render(<ConfirmProvider><OltManagementPage token="token" publicId="olt-1" isSuperadmin onBack={() => undefined} /></ConfirmProvider>)

    fireEvent.click(await screen.findByRole('tab', { name: /Profiles/ }))
    fireEvent.click(screen.getByRole('tab', { name: /ONT line profile/ }))
    fireEvent.click(screen.getByRole('button', { name: 'New line profile' }))
    fireEvent.change(screen.getByRole('combobox', { name: 'C-VLAN for ONT line profile' }), { target: { value: '200' } })
    fireEvent.change(screen.getByRole('combobox', { name: 'TR-069 VLAN' }), { target: { value: '25' } })
    fireEvent.change(screen.getByRole('combobox', { name: 'DBA profile' }), { target: { value: '15' } })
    fireEvent.click(screen.getByRole('button', { name: 'Preview OLT commands' }))

    expect(await screen.findByText(/ont-lineprofile gpon profile-id 17 profile-name "LP_CVLAN_200"/)).toBeTruthy()
    expect(document.querySelector('.ont-line-profile-modal')).toBeTruthy()
    expect(document.querySelector('.line-profile-preview')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/olts/olt-1/qinq/preview', expect.objectContaining({ method: 'POST' }), 'token')
  })
})
