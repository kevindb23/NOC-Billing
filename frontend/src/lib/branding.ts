import { apiRequest } from './api'

export type BrandingValues = {
  organization_name: string
  short_name: string
  brand_mark: string | null
  tagline: string
  logo_url: string | null
  primary_color: string | null
  accent_color: string | null
}

export type BrandingResponse = { data: { branding: BrandingValues; defaults: BrandingValues } }

export function getBranding(token?: string) {
  return apiRequest<BrandingResponse>('/branding', {}, token)
}

export function updateBranding(values: BrandingValues, token?: string) {
  return apiRequest<BrandingResponse>('/branding', { method: 'PUT', body: JSON.stringify(values) }, token)
}
