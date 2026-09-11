// @vitest-environment jsdom
import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { TableActions } from './TableActions'

describe('TableActions', () => {
  it('shows operational CRUD actions inline and confirms archive intent', () => {
    const onArchive = vi.fn()
    render(<TableActions label="Acme Subscriber" kind="operational" onArchive={onArchive} />)

    expect(screen.getByRole('button', { name: 'View Acme Subscriber' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Edit Acme Subscriber' })).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Archive Acme Subscriber' }))
    expect(screen.getByText('Archive Acme Subscriber?')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Confirm archive' }))
    expect(onArchive).toHaveBeenCalledOnce()
  })

  it('shows controlled financial actions without destructive delete', () => {
    render(<TableActions label="INV-000001" kind="financial" />)
    expect(screen.getByRole('button', { name: 'View INV-000001' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Void INV-000001' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Delete INV-000001' })).toBeNull()
  })
})
