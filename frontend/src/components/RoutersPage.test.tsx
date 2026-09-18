// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { RoutersPage } from './RoutersPage'
import { apiRequest } from '../lib/api'

vi.mock('../lib/api', () => ({ apiRequest: vi.fn().mockResolvedValue({ data: { data: [], total: 0, current_page: 1, last_page: 1 } }) }))

describe('RoutersPage', () => {
  afterEach(() => cleanup())

  it('renders the empty router inventory state', async () => {
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.create']} />)

    expect(await screen.findByRole('heading', { name: 'Routers' })).toBeTruthy()
    expect(screen.getByText('No routers found.')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'New router' })).toBeTruthy()
  })

  it('uses the supported vendor and SSH choices in the create form', async () => {
    render(<RoutersPage token="token" permissions={['routers.view', 'routers.create']} />)
    await screen.findByRole('button', { name: 'New router' })
    fireEvent.click(screen.getByRole('button', { name: 'New router' }))

    expect((screen.getByRole('combobox', { name: 'Vendor' }) as HTMLSelectElement).value).toBe('mikrotik')
    expect((screen.getByRole('combobox', { name: 'Preferred transport' }) as HTMLSelectElement).value).toBe('ssh')
    expect(screen.getByRole('button', { name: 'Test SSH connection' })).toBeTruthy()
    expect(screen.getByLabelText('SSH username')).toBeTruthy()
    expect(screen.getByLabelText('SSH password')).toBeTruthy()

    vi.mocked(apiRequest).mockClear()
    fireEvent.click(screen.getByRole('button', { name: 'Test SSH connection' }))
    expect(screen.getByText('SSH details required')).toBeTruthy()
    expect(vi.mocked(apiRequest)).not.toHaveBeenCalled()
  })
})
