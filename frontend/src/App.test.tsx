// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('./components/DashboardPage', () => ({ DashboardPage: () => <h1>Overview page</h1> }))
vi.mock('./components/UsersPage', () => ({ UsersPage: () => <h1>Users page</h1> }))
vi.mock('./components/RolesPage', () => ({ RolesPage: () => <h1>Roles page</h1> }))

describe('billing application entry point', () => {
  beforeEach(() => {
    cleanup()
    localStorage.clear()
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

  it('redirects an unauthorized stored administration view and hides its navigation', () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Viewer', email: 'viewer@example.com' }, permissions: [] }))
    localStorage.setItem('isp-view', 'Roles')
    localStorage.setItem('isp-expanded-sections', JSON.stringify({ System: true }))
    render(<App />)

    expect(screen.getByRole('heading', { name: 'Overview page' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Users' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Roles' })).toBeNull()
    expect(screen.getByText('ISP billing / Dashboard')).toBeTruthy()
  })
})
