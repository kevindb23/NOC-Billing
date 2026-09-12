// @vitest-environment jsdom
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { TableActions } from './TableActions'

describe('TableActions', () => {
  it('shows operational CRUD actions inline and confirms archive intent', async () => {
    const onArchive = vi.fn()
    render(<ConfirmProvider><TableActions label="Acme Subscriber" kind="operational" onArchive={onArchive} /></ConfirmProvider>)

    expect(screen.getByRole('button', { name: 'View Acme Subscriber' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Edit Acme Subscriber' })).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Archive Acme Subscriber' }))
    expect(screen.getByText('Archive Acme Subscriber?')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Archive' }))
    await waitFor(() => expect(onArchive).toHaveBeenCalledOnce())
  })

  it('shows controlled financial actions without destructive delete', () => {
    render(<ConfirmProvider><TableActions label="INV-000001" kind="financial" /></ConfirmProvider>)
    expect(screen.getByRole('button', { name: 'View INV-000001' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Void INV-000001' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Delete INV-000001' })).toBeNull()
  })
})
