// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SpeedBoostPanel } from './SpeedBoostPanel'

const { apiRequest } = vi.hoisted(() => ({ apiRequest: vi.fn() }))
vi.mock('@/lib/api', () => ({ apiRequest }))

describe('SpeedBoostPanel', () => {
  afterEach(() => { cleanup(); vi.clearAllMocks() })

  it('loads the empty state and opens the native Accel-PPP form', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.includes('/radius-servers')) return Promise.resolve({ data: [{ public_id: 'rad-1', name: 'Primary RADIUS', server_address: '10.0.0.2' }] })
      if (path === '/plans?per_page=100') return Promise.resolve({ data: { data: [{ id: 4, name: 'Home 200' }] } })
      return Promise.resolve({ data: [] })
    })

    render(<SpeedBoostPanel token="token" publicId="bng-1" />)

    expect(await screen.findByText(/No Speed Boost profiles yet/)).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Add Speed Boost' }))

    expect(screen.getByLabelText('RADIUS server')).toBeTruthy()
    expect(screen.getByLabelText('Plan')).toBeTruthy()
    expect(screen.getByLabelText('Download speed (Mbps)')).toBeTruthy()
    expect(screen.getByLabelText('Upload speed (Mbps)')).toBeTruthy()
    expect(screen.queryByLabelText('Burst maximum')).toBeNull()
    expect(screen.getByRole('button', { name: 'Save & Apply' })).toBeTruthy()
  })

  it('allows a partial preview without submitting the required save fields', async () => {
    apiRequest.mockImplementation((path: string) => {
      if (path.includes('/radius-servers')) return Promise.resolve({ data: [] })
      if (path === '/plans?per_page=100') return Promise.resolve({ data: { data: [] } })
      if (path.includes('/speed-boosts/preview')) return Promise.resolve({ data: { rate_value: null, usernames: [], user_count: 0, missing: ['download_mbps', 'upload_mbps'], sql: [] } })
      return Promise.resolve({ data: [] })
    })

    render(<SpeedBoostPanel token="token" publicId="bng-1" />)
    fireEvent.click(await screen.findByRole('button', { name: 'Add Speed Boost' }))
    fireEvent.click(screen.getByRole('button', { name: 'Preview' }))

    expect(await screen.findByText('Speed Boost preview')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith(expect.stringContaining('/speed-boosts/preview'), expect.objectContaining({ method: 'POST' }), 'token')
  })
})
