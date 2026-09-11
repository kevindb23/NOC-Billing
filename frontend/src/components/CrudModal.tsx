import { useState } from 'react'
import type { FormEvent } from 'react'
import { XIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

export type CrudField = {
  name: string
  label: string
  type?: 'text' | 'date' | 'number' | 'textarea'
  required?: boolean
  readOnly?: boolean
}

export function CrudModal({ open, mode, title, description, fields, initialValues, error, loading, onClose, onSubmit }: {
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
}) {
  const [values, setValues] = useState(initialValues)
  if (!open) return null
  const update = (name: string, value: string) => setValues(current => ({ ...current, [name]: value }))
  const submit = async (event: FormEvent) => { event.preventDefault(); await onSubmit(values) }
  const readOnly = mode === 'view'
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="crud-modal-title">
    <Card className="billing-modal-card relative w-full max-w-xl shadow-2xl">
      <CardHeader className="billing-modal-header border-b pr-14">
        <p className="billing-modal-eyebrow">{readOnly ? 'Record details' : mode === 'create' ? 'Create record' : 'Update record'}</p>
        <CardTitle id="crud-modal-title">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
        <Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={onClose} aria-label="Close modal"><XIcon /></Button>
      </CardHeader>
      <CardContent className="billing-modal-content pt-5">
        {readOnly ? <dl className="billing-modal-details grid gap-2 sm:grid-cols-2">{fields.map(field => <div className="billing-modal-detail" key={field.name}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{field.label}</dt><dd className="mt-1 text-sm">{values[field.name] || '—'}</dd></div>)}</dl> : <form onSubmit={submit}><FieldGroup className="grid gap-4 sm:grid-cols-2">{fields.map(field => <Field className={field.type === 'textarea' ? 'sm:col-span-2' : undefined} key={field.name}><FieldLabel htmlFor={`crud-${field.name}`}>{field.label}</FieldLabel>{field.type === 'textarea' ? <textarea id={`crud-${field.name}`} className="min-h-24 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values[field.name] || ''} onChange={event => update(field.name, event.target.value)} required={field.required} readOnly={field.readOnly} /> : <Input id={`crud-${field.name}`} type={field.type || 'text'} value={values[field.name] || ''} onChange={event => update(field.name, event.target.value)} required={field.required} readOnly={field.readOnly} />}</Field>)}</FieldGroup>{error && <p className="mt-4 text-xs text-destructive">{error}</p>}<div className="billing-modal-footer mt-6 flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={loading}>{loading ? 'Saving…' : mode === 'create' ? 'Create' : 'Save changes'}</Button></div></form>}
      </CardContent>
    </Card>
  </div>
}
