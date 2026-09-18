import { useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { CaretDownIcon, XIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

export type CrudField = {
  name: string
  label: string
  type?: 'text' | 'date' | 'number' | 'password' | 'textarea'
  required?: boolean
  placeholder?: string
  readOnly?: boolean
  options?: { value: string; label: string }[]
  searchable?: boolean
  computedFrom?: string
  currency?: boolean
}

function SearchableSelect({ field, value, onChange }: { field: CrudField; value: string; onChange: (value: string) => void }) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const selected = field.options?.find(option => option.value === value)
  const filtered = (field.options || []).filter(option => option.label.toLowerCase().includes(query.toLowerCase()))
  return <div className="relative">
    <button type="button" className="flex h-9 w-full items-center justify-between border border-input bg-background px-2.5 text-left text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" onClick={() => setOpen(current => !current)} aria-haspopup="listbox" aria-expanded={open}>{selected?.label || `Select ${field.label.toLowerCase()}`}<CaretDownIcon size={13} weight="bold" className={`shrink-0 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" /></button>
    {open && <div className="absolute z-20 mt-1 max-h-64 w-full overflow-hidden rounded-md border border-border bg-popover shadow-lg">
      <div className="border-b border-border p-2"><Input autoFocus aria-label={`Search ${field.label}`} placeholder={`Search ${field.label.toLowerCase()}`} value={query} onChange={event => setQuery(event.target.value)} /></div>
      <div className="max-h-48 overflow-y-auto p-1" role="listbox">{filtered.length ? filtered.map(option => <button type="button" role="option" aria-selected={option.value === value} className="block w-full rounded px-2 py-2 text-left text-xs hover:bg-accent" key={option.value} onClick={() => { onChange(option.value); setOpen(false); setQuery('') }}>{option.label}</button>) : <p className="px-2 py-3 text-xs text-muted-foreground">No matches found.</p>}</div>
    </div>}
  </div>
}

function SimpleSelect({ field, value, onChange }: { field: CrudField; value: string; onChange: (value: string) => void }) {
  return <div className="relative">
    <select id={`crud-${field.name}`} className="h-9 w-full appearance-none border border-input bg-background px-2.5 pr-8 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={value} onChange={event => onChange(event.target.value)} required={field.required}>
      <option value="">Select {field.label.toLowerCase()}</option>
      {field.options?.map(option => <option value={option.value} key={option.value}>{option.label}</option>)}
    </select>
    <CaretDownIcon size={13} weight="bold" className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
  </div>
}

function CurrencyInput({ field, value, currency, onChange, onCurrencyChange }: { field: CrudField; value: string; currency: string; onChange: (value: string) => void; onCurrencyChange: (value: string) => void }) {
  const currencies = [{ value: 'PHP', label: '₱ PHP' }, { value: 'USD', label: '$ USD' }, { value: 'EUR', label: '€ EUR' }]
  return <div className="flex h-9 border border-input bg-background focus-within:border-ring focus-within:ring-1 focus-within:ring-ring/50">
    <Input id={`crud-${field.name}`} className="h-full min-w-0 flex-1 border-0 shadow-none focus-visible:ring-0" type="number" min="0" step="0.01" value={value} onChange={event => onChange(event.target.value)} required={field.required} readOnly={field.readOnly} />
    <select aria-label="Currency" className="h-full w-[76px] appearance-none border-0 border-l border-input bg-transparent px-1 text-[11px] outline-none" value={currency} onChange={event => onCurrencyChange(event.target.value)} disabled={field.readOnly}>
      {currencies.map(item => <option value={item.value} key={item.value}>{item.label}</option>)}
    </select>
  </div>
}

export function CrudModal({ open, mode, title, description, fields, initialValues, error, loading, onClose, onSubmit, renderExtra, renderActions, wide, splitLayout }: {
  open: boolean
  mode: 'view' | 'create' | 'edit'
  title: string
  description: string
  fields: CrudField[]
  initialValues: Record<string, string>
  error?: string
  loading?: boolean
  onClose: () => void
  onSubmit: (values: Record<string, string>) => Promise<void>
  renderExtra?: (values: Record<string, string>) => ReactNode
  renderActions?: (values: Record<string, string>) => ReactNode
  wide?: boolean
  splitLayout?: boolean
}) {
  const [values, setValues] = useState(initialValues)
  if (!open) return null
  const update = (name: string, value: string) => setValues(current => {
    const next = { ...current, [name]: value }
    const field = fields.find(item => item.name === name)
    if (field?.computedFrom) { const date = new Date(`${value}T00:00:00`); if (!Number.isNaN(date.getTime())) { date.setMonth(date.getMonth() + 1); next[field.computedFrom] = date.toISOString().slice(0, 10) } }
    return next
  })
  const submit = async (event: FormEvent) => { event.preventDefault(); await onSubmit(values) }
  const readOnly = mode === 'view'
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="crud-modal-title">
    <Card className={`billing-modal-card relative w-full ${wide ? 'max-w-5xl' : 'max-w-2xl'} shadow-2xl`}>
      <CardHeader className="billing-modal-header border-b pr-14">
        <p className="billing-modal-eyebrow">{readOnly ? 'Record details' : mode === 'create' ? 'Create record' : 'Update record'}</p>
        <CardTitle id="crud-modal-title">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
        <Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={onClose} aria-label="Close modal"><XIcon /></Button>
      </CardHeader>
      <CardContent className="billing-modal-content pt-5">
        {readOnly ? <><dl className="billing-modal-details grid gap-2 sm:grid-cols-2">{fields.map(field => <div className="billing-modal-detail" key={field.name}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{field.label}</dt><dd className="mt-1 text-sm">{values[field.name] || '—'}</dd></div>)}</dl>{renderExtra?.(values)}</> : <form className={splitLayout ? 'billing-subscription-form' : undefined} onSubmit={submit}><FieldGroup className="grid gap-4 sm:grid-cols-2">{fields.map(field => <Field key={field.name} data-field-name={field.name}><FieldLabel htmlFor={`crud-${field.name}`}>{field.label}</FieldLabel>{field.currency ? <CurrencyInput field={field} value={values[field.name] || ''} currency={values.currency || 'PHP'} onChange={value => update(field.name, value)} onCurrencyChange={value => update('currency', value)} /> : field.options ? (field.searchable === false ? <SimpleSelect field={field} value={values[field.name] || ''} onChange={value => update(field.name, value)} /> : <SearchableSelect field={field} value={values[field.name] || ''} onChange={value => update(field.name, value)} />) : field.type === 'textarea' ? <textarea id={`crud-${field.name}`} className="min-h-24 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values[field.name] || ''} onChange={event => update(field.name, event.target.value)} required={field.required} readOnly={field.readOnly} placeholder={field.placeholder} /> : <Input id={`crud-${field.name}`} type={field.type || 'text'} value={values[field.name] || ''} onChange={event => update(field.name, event.target.value)} required={field.required} readOnly={field.readOnly} placeholder={field.placeholder} />}</Field>)}</FieldGroup>{renderExtra?.(values)}{error && <p className="mt-4 text-xs text-destructive">{error}</p>}<div className="billing-modal-footer mt-6 flex justify-end gap-2 border-t pt-4">{renderActions?.(values)}<Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={loading}>{loading ? 'Saving…' : mode === 'create' ? 'Create' : 'Save changes'}</Button></div></form>}
      </CardContent>
    </Card>
  </div>
}
