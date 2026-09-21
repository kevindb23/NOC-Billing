// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { GlobalCrudLoader } from './GlobalCrudLoader'

describe('GlobalCrudLoader', () => {
  afterEach(() => cleanup())

  it('shows the shared OLT operation animation for every CRUD request', async () => {
    render(<GlobalCrudLoader />)
    await new Promise(resolve => setTimeout(resolve, 0))
    window.dispatchEvent(new CustomEvent('crud-operation:start', { detail: { id: 1, method: 'DELETE', path: '/olts/olt-1/ont-tr069-server-profiles/1', title: 'Removing TR-069 profile', description: 'Updating the OLT configuration' } }))

    await waitFor(() => expect(screen.getByRole('status')).toBeTruthy())
    expect(screen.getByText('Removing TR-069 profile')).toBeTruthy()
    expect(screen.getByText('Updating the OLT configuration')).toBeTruthy()

    window.dispatchEvent(new CustomEvent('crud-operation:end', { detail: { id: 1 } }))
    await waitFor(() => expect(screen.queryByRole('status')).toBeNull())
  })

  it('keeps the loader visible while concurrent operations are active', () => {
    render(<GlobalCrudLoader />)
    fireEvent(window, new CustomEvent('crud-operation:start', { detail: { id: 1, method: 'POST', path: '/olts/olt-1/terminal-users', title: 'Applying terminal user changes', description: 'Updating the OLT configuration' } }))
    fireEvent(window, new CustomEvent('crud-operation:start', { detail: { id: 2, method: 'DELETE', path: '/olts/olt-1/terminal-users/1', title: 'Removing terminal user', description: 'Updating the OLT configuration' } }))
    window.dispatchEvent(new CustomEvent('crud-operation:end', { detail: { id: 1 } }))

    expect(screen.getByRole('status')).toBeTruthy()
    expect(screen.getByText('Removing terminal user')).toBeTruthy()
  })
})
