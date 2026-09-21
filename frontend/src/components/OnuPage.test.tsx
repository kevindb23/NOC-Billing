// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConfirmProvider } from './ConfirmProvider'
import { OnuPage } from './OnuPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('../lib/api', () => ({ apiRequest }))

describe('OnuPage', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('renders an empty ONU stock state with an add action', async () => {
    apiRequest.mockResolvedValue({ data: { data: [], total: 0, current_page: 1, last_page: 1 } })
    render(<ConfirmProvider><OnuPage token="token" permissions={['onus.view', 'onus.create']} /></ConfirmProvider>)

    expect(await screen.findByRole('heading', { name: 'ONU inventory' })).toBeTruthy()
    expect(screen.getAllByText('No ONU stock recorded yet.').length).toBeGreaterThan(0)
    expect(screen.getAllByRole('button', { name: 'Add ONU' }).length).toBeGreaterThan(0)
  })

  it('opens the stock form with inventory fields', async () => {
    apiRequest.mockResolvedValue({ data: { data: [], total: 0, current_page: 1, last_page: 1 } })
    render(<ConfirmProvider><OnuPage token="token" permissions={['onus.view', 'onus.create']} /></ConfirmProvider>)

    fireEvent.click((await screen.findAllByRole('button', { name: 'Add ONU' }))[0])
    expect(screen.getByRole('dialog', { name: 'Add ONU stock' })).toBeTruthy()
    expect(screen.getByRole('textbox', { name: 'Vendor' })).toBeTruthy()
    expect(screen.getByRole('textbox', { name: 'Model' })).toBeTruthy()
    expect(screen.getByRole('spinbutton', { name: 'Quantity' })).toBeTruthy()
  })
})
