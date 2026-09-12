import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ArrowCounterClockwiseIcon, FloppyDiskIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { type BrandingValues, updateBranding } from '@/lib/branding'
import { hasPermission } from '@/lib/usersRoles'

const emptyBranding: BrandingValues = {
  organization_name: '',
  short_name: '',
  brand_mark: null,
  tagline: '',
  logo_url: null,
  primary_color: null,
  accent_color: null,
}

export function BrandingPage({ token, permissions, branding, onSaved }: { token?: string; permissions?: string[]; branding?: BrandingValues; onSaved: (branding: BrandingValues) => void }) {
  const [values, setValues] = useState<BrandingValues>(branding || emptyBranding)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)
  const canView = hasPermission(permissions, 'branding.view')
  const canUpdate = hasPermission(permissions, 'branding.update')

  useEffect(() => { if (branding) setValues(branding) }, [branding])

  if (!canView) return <Alert variant="destructive"><AlertTitle>Access denied</AlertTitle><AlertDescription>You do not have permission to view branding settings.</AlertDescription></Alert>

  const change = (field: keyof BrandingValues, value: string) => { setSaved(false); setValues(current => ({ ...current, [field]: value || null })) }
  const reset = () => { if (branding) setValues(branding); setError(''); setSaved(false) }
  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (!canUpdate) return
    setSaving(true); setError(''); setSaved(false)
    try {
      const response = await updateBranding(values, token)
      setValues(response.data.branding); onSaved(response.data.branding); setSaved(true)
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to save branding.') }
    finally { setSaving(false) }
  }

  const primary = values.primary_color || '#2f8f46'
  const accent = values.accent_color || '#e8f4e8'
  const displayName = values.short_name || values.organization_name || 'Your organization'

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex items-center justify-between gap-4"><h1 className="text-sm font-semibold">Branding</h1></div>
    {error && <Alert variant="destructive"><AlertTitle>Could not save branding</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    <Card className="billing-records overflow-hidden">
      <CardHeader className="border-b"><CardTitle>Brand identity</CardTitle><CardDescription>Manage the organization identity shown across billing operations.</CardDescription></CardHeader>
      <CardContent className="p-0"><div className="grid lg:grid-cols-[minmax(0,1fr)_minmax(19rem,0.72fr)]">
        <form className="p-5 sm:p-6" onSubmit={submit}><FieldGroup className="grid gap-4 sm:grid-cols-2">
          <Field><FieldLabel htmlFor="branding-organization-name">Organization name</FieldLabel><Input id="branding-organization-name" aria-label="Organization name" value={values.organization_name} onChange={event => change('organization_name', event.target.value)} disabled={!canUpdate} /></Field>
          <Field><FieldLabel htmlFor="branding-short-name">Short brand name</FieldLabel><Input id="branding-short-name" aria-label="Short brand name" value={values.short_name} onChange={event => change('short_name', event.target.value)} disabled={!canUpdate} /></Field>
          <Field><FieldLabel htmlFor="branding-brand-mark">Brand mark</FieldLabel><Input id="branding-brand-mark" aria-label="Brand mark" maxLength={12} value={values.brand_mark || ''} onChange={event => change('brand_mark', event.target.value)} disabled={!canUpdate} /></Field>
          <Field><FieldLabel htmlFor="branding-tagline">Tagline</FieldLabel><Input id="branding-tagline" aria-label="Tagline" value={values.tagline} onChange={event => change('tagline', event.target.value)} disabled={!canUpdate} /></Field>
          <Field className="sm:col-span-2"><FieldLabel htmlFor="branding-logo-url">Logo URL</FieldLabel><Input id="branding-logo-url" aria-label="Logo URL" type="url" placeholder="https://example.com/logo.svg" value={values.logo_url || ''} onChange={event => change('logo_url', event.target.value)} disabled={!canUpdate} /></Field>
          <Field><FieldLabel htmlFor="branding-primary-color">Primary color</FieldLabel><div className="flex gap-2"><Input id="branding-primary-color" aria-label="Primary color" type="color" className="h-8 w-10 p-1" value={values.primary_color || primary} onChange={event => change('primary_color', event.target.value)} disabled={!canUpdate} /><Input aria-label="Primary color hex" value={values.primary_color || ''} placeholder="#2f8f46" onChange={event => change('primary_color', event.target.value)} disabled={!canUpdate} /></div></Field>
          <Field><FieldLabel htmlFor="branding-accent-color">Accent color</FieldLabel><div className="flex gap-2"><Input id="branding-accent-color" aria-label="Accent color" type="color" className="h-8 w-10 p-1" value={values.accent_color || accent} onChange={event => change('accent_color', event.target.value)} disabled={!canUpdate} /><Input aria-label="Accent color hex" value={values.accent_color || ''} placeholder="#e8f4e8" onChange={event => change('accent_color', event.target.value)} disabled={!canUpdate} /></div></Field>
        </FieldGroup><div className="mt-6 flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={reset} disabled={!canUpdate}><ArrowCounterClockwiseIcon data-icon="inline-start" />Reset</Button>{canUpdate && <Button type="submit" disabled={saving}><FloppyDiskIcon data-icon="inline-start" />{saving ? 'Saving…' : 'Save changes'}</Button>}</div></form>
        <div className="border-t bg-muted/25 p-5 lg:border-l lg:border-t-0 sm:p-6"><div className="mb-4 flex items-center justify-between"><div><p className="billing-modal-eyebrow">Live preview</p><p className="text-xs text-muted-foreground">Preview changes before saving.</p></div><Badge variant="outline">Organization</Badge></div><div className="overflow-hidden rounded-lg border bg-card shadow-sm"><div className="flex items-center gap-3 border-b px-4 py-3"><div className="grid size-9 place-items-center rounded-md text-xs font-semibold text-white" style={{ backgroundColor: primary }}>{values.logo_url ? <img src={values.logo_url} alt="" className="size-7 object-contain" /> : values.brand_mark || 'AB'}</div><div className="min-w-0"><p className="truncate text-sm font-semibold">{displayName}</p><p className="truncate text-[10px] text-muted-foreground">{values.tagline || 'Billing operations'}</p></div></div><div className="space-y-4 p-4"><p className="text-xs text-muted-foreground">This identity will appear in the billing workspace.</p><Button type="button" className="w-full" style={{ backgroundColor: primary, borderColor: primary }}>Sample action</Button><div className="h-1.5 rounded-full" style={{ backgroundColor: accent }} /></div></div></div>
      </div></CardContent>
    </Card>
    {saved && <p className="text-xs text-emerald-700 dark:text-emerald-300">Branding saved.</p>}
  </div>
}
