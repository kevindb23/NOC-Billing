import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiRequest } from './api'
import { getBranding, updateBranding, type BrandingValues } from './branding'

vi.mock('./api', () => ({ apiRequest: vi.fn() }))

describe('branding api', () => {
  beforeEach(() => vi.clearAllMocks())

  it('loads organization branding from the tenant endpoint', async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: { branding: { organization_name: 'Acme' } } })

    await getBranding('token')

    expect(apiRequest).toHaveBeenCalledWith('/branding', {}, 'token')
  })

  it('saves only branding fields through the tenant endpoint', async () => {
    const values: BrandingValues = { organization_name: 'Acme', short_name: 'AC', brand_mark: 'AC', tagline: 'Fiber', logo_url: null, primary_color: '#123456', accent_color: '#abcdef' }
    vi.mocked(apiRequest).mockResolvedValue({ data: { branding: values } })

    await updateBranding(values, 'token')

    expect(apiRequest).toHaveBeenCalledWith('/branding', { method: 'PUT', body: JSON.stringify(values) }, 'token')
  })
})
