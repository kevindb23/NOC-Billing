// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { apiRequest } from './lib/api'

vi.mock('./lib/api', () => ({ apiRequest: vi.fn() }))

vi.mock('./components/DashboardPage', () => ({ DashboardPage: () => <h1>Overview page</h1> }))
vi.mock('./components/UsersPage', () => ({ UsersPage: () => <h1>Users page</h1> }))
vi.mock('./components/RolesPage', () => ({ RolesPage: () => <h1>Roles page</h1> }))
vi.mock('./components/NetworkModulePage', () => ({ NetworkModulePage: ({ module, token, permissions, isSuperadmin }: { module: string; token?: string; permissions?: string[]; isSuperadmin?: boolean }) => <div><h1>{module} page</h1><output data-testid="network-module-props">{JSON.stringify({ module, token, permissions, isSuperadmin })}</output></div> }))
vi.mock('./components/OnuPage', () => ({ OnuPage: () => <h1>ONU inventory page</h1> }))

describe('billing application entry point', () => {
  beforeEach(() => {
    cleanup()
    window.history.pushState({}, '', '/overview')
    localStorage.clear()
    vi.mocked(apiRequest).mockResolvedValue({ data: { permissions: [] } })
  })

  it('presents the billing operations sign-in surface', () => {
    render(<App />)

    expect(screen.getByText('1wan')).toBeTruthy()
    expect(screen.getByText('Billing operations')).toBeTruthy()
    expect((screen.getByLabelText('Password') as HTMLInputElement).value).toBe('')
    expect(screen.getByText('Enter your email and password to continue.')).toBeTruthy()
    const signInButton = screen.getByRole('button', { name: /sign in with email/i })
    expect(signInButton).toBeTruthy()
    expect(signInButton.getAttribute('type')).toBe('submit')
  })

  it('mounts one global Sonner toaster', async () => {
    render(<App />)

    await waitFor(() => expect(screen.getByLabelText(/Notifications alt\+T/)).toBeTruthy())
  })

  it('routes between Users and Roles from the existing System navigation', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: ['users.view', 'roles.view'] }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ System: true }))
    render(<App />)

    expect(screen.getByRole('heading', { name: 'Overview page' })).toBeTruthy()
    fireEvent.click(screen.getAllByRole('button', { name: 'Users' })[0])
    expect(screen.getByRole('heading', { name: 'Users page' })).toBeTruthy()
    expect(screen.getAllByRole('button', { name: 'Users' }).every(button => button.getAttribute('aria-current') === 'page')).toBe(true)

    fireEvent.click(screen.getAllByRole('button', { name: 'Roles' })[0])
    expect(screen.getByRole('heading', { name: 'Roles page' })).toBeTruthy()
    expect(screen.getAllByRole('button', { name: 'Roles' }).every(button => button.getAttribute('aria-current') === 'page')).toBe(true)
  })

  it('keeps every module available to the superadmin across all navigation sections', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: [], is_superadmin: true }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ System: true }))
    render(<App />)

    expect(screen.getAllByRole('button', { name: 'Overview' })).not.toHaveLength(0)
    expect(screen.getAllByRole('button', { name: 'Billing accounts' })).not.toHaveLength(0)
    expect(screen.getAllByRole('button', { name: 'BNG' })).not.toHaveLength(0)
    expect(screen.getAllByRole('button', { name: 'Users' })).not.toHaveLength(0)
    expect(screen.getAllByRole('button', { name: 'Branding' })).not.toHaveLength(0)
  })

  it('passes the authenticated session to the router module page', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'router-token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: ['routers.view'], is_superadmin: false }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ Network: true }))
    render(<App />)

    fireEvent.click(screen.getAllByRole('button', { name: 'Routers' })[0])

    expect(screen.getByRole('heading', { name: 'Routers page' })).toBeTruthy()
    expect(screen.getByTestId('network-module-props').textContent).toContain('router-token')
  })

  it('does not redirect a deep ONT link while refreshing stored permissions', async () => {
    window.history.pushState({}, '', '/ont/ont-1/manage')
    vi.mocked(apiRequest).mockResolvedValue({ data: { permissions: ['onts.view'], is_superadmin: false } })
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Viewer', email: 'viewer@example.com' }, is_superadmin: false }))

    render(<App />)

    expect(await screen.findByRole('heading', { name: 'ONT page' })).toBeTruthy()
  })

  it('opens the encoded ACS URL instead of falling back to the previously visited page', async () => {
    window.history.pushState({}, '', '/ACS%20Server')
    localStorage.setItem('isp-view', 'overview')
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, is_superadmin: true }))

    render(<App />)

    expect(await screen.findByRole('heading', { name: 'ACS Server page' })).toBeTruthy()
    expect(screen.queryByRole('heading', { name: 'Overview page' })).toBeNull()
  })

  it('places ONU under Inventory and routes to the stock page', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: [], is_superadmin: true }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ Inventory: true }))
    render(<App />)

    fireEvent.click(screen.getAllByRole('button', { name: 'ONU' })[0])

    expect(screen.getByRole('heading', { name: 'ONU inventory page' })).toBeTruthy()
    expect(screen.getByText('ISP billing / Inventory')).toBeTruthy()
  })

  it('lets a superadmin open branding without explicit permission rows', async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { permissions: [], is_superadmin: true } })
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: [], is_superadmin: true }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ Dashboard: true, Billing: true, Network: true, System: true }))
    render(<App />)

    fireEvent.click(screen.getAllByRole('button', { name: 'Branding' })[0])
    expect(await screen.findByRole('heading', { name: 'Branding' })).toBeTruthy()
    expect(screen.queryByText('Access denied')).toBeNull()
  })

  it('redirects an unauthorized stored administration view and hides its navigation', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Viewer', email: 'viewer@example.com' }, permissions: [], is_superadmin: false }))
    localStorage.setItem('isp-view', 'Roles')
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ System: true }))
    render(<App />)

    expect(screen.getByRole('heading', { name: 'Overview page' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Users' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Roles' })).toBeNull()
    expect(screen.getByText('ISP billing / Dashboard')).toBeTruthy()
  })

  it('refreshes stale stored permissions after the administrator role is provisioned', async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { permissions: ['users.view', 'roles.view'] } })
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Admin', email: 'admin@example.com' }, permissions: [] }))
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ System: true }))
    render(<App />)

    expect(await screen.findAllByRole('button', { name: 'Users' })).not.toHaveLength(0)
    expect(await screen.findAllByRole('button', { name: 'Roles' })).not.toHaveLength(0)
  })

})
