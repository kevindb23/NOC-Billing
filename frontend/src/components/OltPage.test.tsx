// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OltPage } from './OltPage'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn().mockResolvedValue({ data: { data: [], total: 0, current_page: 1, last_page: 1 } }) }))
vi.mock('../lib/api', () => ({ apiRequest }))

describe('OltPage', () => {
  afterEach(() => cleanup())

  it('renders the empty inventory state', async () => {
    render(<OltPage token="token" permissions={['olts.view', 'olts.create']} />)
    expect(await screen.findByRole('heading', { name: 'OLTs' })).toBeTruthy()
    expect(screen.getByText('No OLTs found.')).toBeTruthy()
  })

  it('offers Huawei, ZTE, and HSGQ when creating an OLT', async () => {
    render(<OltPage token="token" permissions={['olts.view', 'olts.create']} />)
    fireEvent.click(await screen.findByRole('button', { name: 'New OLT' }))
    expect(screen.getByRole('combobox', { name: 'Vendor' })).toBeTruthy()
    expect(screen.getAllByText('Huawei').length).toBeGreaterThan(0)
    expect(screen.getAllByText('ZTE').length).toBeGreaterThan(0)
    expect(screen.getAllByText('HSGQ').length).toBeGreaterThan(0)
    expect(screen.getByRole('button', { name: 'Test connection' })).toBeTruthy()
  })
})
