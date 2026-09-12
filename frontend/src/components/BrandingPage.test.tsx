// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { BrandingPage } from './BrandingPage'
import { updateBranding, type BrandingValues } from '@/lib/branding'

vi.mock('@/lib/branding', async () => {
  const actual = await vi.importActual<typeof import('@/lib/branding')>('@/lib/branding')
  return { ...actual, updateBranding: vi.fn() }
})

const values: BrandingValues = {
  organization_name: 'Acme Fiber',
  short_name: 'Acme',
  brand_mark: 'AF',
  tagline: 'Connected locally',
  logo_url: null,
  primary_color: '#2f8f46',
  accent_color: '#e8f4e8',
}

describe('branding page', () => {
  beforeEach(() => cleanup())

  it('allows a superadmin to access branding without explicit permission rows', () => {
    render(<BrandingPage token="token" permissions={[]} isSuperadmin branding={values} onSaved={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'Branding' })).toBeTruthy()
    expect(screen.getByRole('button', { name: /save changes/i })).toBeTruthy()
  })

  it('renders the saved identity and updates the live preview', () => {
    render(<BrandingPage token="token" permissions={['branding.view']} branding={values} onSaved={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'Branding' })).toBeTruthy()
    expect(screen.getByDisplayValue('Acme Fiber')).toBeTruthy()
    expect(screen.getByText('Connected locally')).toBeTruthy()
    fireEvent.change(screen.getByLabelText('Short brand name'), { target: { value: 'Acme ISP' } })
    expect(screen.getByText('Acme ISP')).toBeTruthy()
    expect(screen.queryByRole('button', { name: /save changes/i })).toBeNull()
  })

  it('saves changes only when the user has branding.update', async () => {
    vi.mocked(updateBranding).mockResolvedValue({ data: { branding: values, defaults: values } })
    render(<BrandingPage token="token" permissions={['branding.view', 'branding.update']} branding={values} onSaved={vi.fn()} />)

    fireEvent.change(screen.getAllByLabelText('Tagline')[0], { target: { value: 'Fast and local' } })
    fireEvent.click(screen.getByRole('button', { name: /save changes/i }))

    expect(await screen.findByText('Branding saved.')).toBeTruthy()
    expect(updateBranding).toHaveBeenCalled()
  })
})
