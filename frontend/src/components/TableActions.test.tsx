// @vitest-environment jsdom
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { TableActions } from './TableActions'

describe('TableActions', () => {
  it('shows operational CRUD actions inline and confirms archive intent', async () => {
    const onArchive = vi.fn()
    const onEdit = vi.fn()
    render(<ConfirmProvider><TableActions label="Acme Subscriber" kind="operational" onEdit={onEdit} onArchive={onArchive} /></ConfirmProvider>)

    expect(screen.getByRole('button', { name: 'View Acme Subscriber' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Edit Acme Subscriber' })).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Archive Acme Subscriber' }))
    expect(screen.getByText('Archive Acme Subscriber?')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Archive' }))
    await waitFor(() => expect(onArchive).toHaveBeenCalledOnce())
  })

  it('renders only actions with handlers and confirms financial void intent', async () => {
    const onVoid = vi.fn()
    render(<ConfirmProvider><TableActions label="INV-000001" kind="financial" onVoid={onVoid} /></ConfirmProvider>)
    expect(screen.getByRole('button', { name: 'View INV-000001' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Void INV-000001' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Delete INV-000001 permanently' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Archive INV-000001' })).toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'Void INV-000001' }))
    expect(screen.getByText('Void INV-000001?')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Void' }))
    await waitFor(() => expect(onVoid).toHaveBeenCalledOnce())
  })

  it('confirms permanent deletion only when a delete handler is supplied', async () => {
    const onDelete = vi.fn()
    render(<ConfirmProvider><TableActions label="Draft plan" kind="operational" onDelete={onDelete} /></ConfirmProvider>)
    fireEvent.click(screen.getByRole('button', { name: 'Delete Draft plan permanently' }))
    expect(screen.getByText('Delete permanently Draft plan?')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Delete permanently' }))
    await waitFor(() => expect(onDelete).toHaveBeenCalledOnce())
  })
})
