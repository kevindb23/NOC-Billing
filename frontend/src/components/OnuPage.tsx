import { useEffect, useState, type FormEvent } from 'react'
import { CircleNotchIcon, PackageIcon, PencilSimpleIcon, PlusIcon, TrashIcon, XIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { hasPermission } from '../lib/usersRoles'
import { useConfirm } from './ConfirmProvider'

type OnuStatus = 'in_stock' | 'reserved' | 'assigned' | 'faulty' | 'retired'
type OnuRecord = { id: number; public_id: string; vendor: string; model: string; serial_number?: string | null; quantity: number; status: OnuStatus; purchase_date?: string | null; notes?: string | null }
type OnuForm = { vendor: string; model: string; serial_number: string; quantity: string; status: OnuStatus; purchase_date: string; notes: string }
type Props = { token: string; permissions?: string[]; isSuperadmin?: boolean }

const emptyForm = (): OnuForm => ({ vendor: '', model: '', serial_number: '', quantity: '1', status: 'in_stock', purchase_date: '', notes: '' })
const statusLabel = (status: OnuStatus) => status.replace('_', ' ')

export function OnuPage({ token, permissions, isSuperadmin }: Props) {
  const [rows, setRows] = useState<OnuRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<OnuRecord | null>(null)
  const [form, setForm] = useState<OnuForm>(emptyForm())
  const confirm = useConfirm()

  const canCreate = isSuperadmin !== false || hasPermission(permissions, 'onus.create')
  const canUpdate = isSuperadmin !== false || hasPermission(permissions, 'onus.update')
  const canDelete = isSuperadmin !== false || hasPermission(permissions, 'onus.delete')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const response = await apiRequest<{ data: { data: OnuRecord[] } }>('/onus?per_page=100', {}, token)
      setRows(response.data.data || [])
    } catch (exception) {
      setError(getErrorMessage(exception, 'Unable to load ONU inventory.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { void load() }, [token])

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm())
    setFormOpen(true)
  }

  const openEdit = (row: OnuRecord) => {
    setEditing(row)
    setForm({
      vendor: row.vendor,
      model: row.model,
      serial_number: row.serial_number || '',
      quantity: String(row.quantity),
      status: row.status,
      purchase_date: row.purchase_date || '',
      notes: row.notes || '',
    })
    setFormOpen(true)
  }

  const save = async (event: FormEvent) => {
    event.preventDefault()
    setSaving(true)
    try {
      const payload = { ...form, quantity: Number(form.quantity), serial_number: form.serial_number || null, purchase_date: form.purchase_date || null }
      const path = editing ? `/onus/${editing.public_id}` : '/onus'
      await apiRequest(path, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) }, token)
      notify.success(editing ? 'ONU stock updated.' : 'ONU stock added.')
      setFormOpen(false)
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, editing ? 'Unable to update ONU stock.' : 'Unable to add ONU stock.'))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (row: OnuRecord) => {
    const accepted = await confirm({ title: `Delete ${row.vendor} ${row.model}?`, description: 'This permanently removes the ONU stock record.', confirmLabel: 'Delete permanently', destructive: true })
    if (!accepted) return
    setDeletingId(row.id)
    try {
      await apiRequest(`/onus/${row.public_id}`, { method: 'DELETE' }, token)
      notify.success('ONU stock deleted.')
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to delete ONU stock.'))
    } finally {
      setDeletingId(null)
    }
  }

  return <div className="flex min-w-0 flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4">
      <div>
        <p className="billing-eyebrow">Inventory / optical network</p>
        <h1 className="mt-2 text-lg font-semibold tracking-tight">ONU inventory</h1>
        <p className="mt-1 max-w-2xl text-xs text-muted-foreground">Track ONU hardware held in stock before it is assigned to an installation.</p>
      </div>
      {canCreate && <Button type="button" className="min-h-11 sm:min-h-8" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add ONU</Button>}
    </div>
    {error && <Alert variant="destructive"><AlertTitle>ONU inventory unavailable</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    <Card className="billing-records min-w-0 overflow-hidden">
      <CardHeader className="gap-4 border-b border-border/60 pb-4">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div><CardTitle className="flex items-center gap-2"><PackageIcon size={18} />ONU stock <span className="bg-muted px-2 py-1 text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle><CardDescription className="mt-2">Add stock records manually; this inventory does not provision an OLT.</CardDescription></div>
          <div className="text-xs text-muted-foreground">{loading ? 'Loading…' : `${rows.length} ${rows.length === 1 ? 'record' : 'records'}`}</div>
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <div className="hidden overflow-x-auto md:block">
          <Table className="min-w-[900px]"><TableHeader><TableRow><TableHead>Vendor</TableHead><TableHead>Model</TableHead><TableHead>Serial number</TableHead><TableHead>Quantity</TableHead><TableHead>Status</TableHead><TableHead>Purchase date</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>{loading ? <TableRow><TableCell colSpan={7} className="h-40 text-center text-muted-foreground">Loading ONU inventory…</TableCell></TableRow> : rows.length ? rows.map(row => <TableRow key={row.public_id}><TableCell className="font-medium">{row.vendor}</TableCell><TableCell>{row.model}</TableCell><TableCell className="font-mono text-[11px]">{row.serial_number || 'Bulk stock'}</TableCell><TableCell>{row.quantity}</TableCell><TableCell><Badge variant="outline" className="capitalize">{statusLabel(row.status)}</Badge></TableCell><TableCell>{row.purchase_date || '—'}</TableCell><TableCell className="text-right"><div className="inline-flex items-center justify-end gap-1">{canUpdate && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11" aria-label={`Edit ${row.vendor} ${row.model}`} title="Edit" onClick={() => openEdit(row)}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Delete ${row.vendor} ${row.model}`} title="Delete" disabled={deletingId === row.id} onClick={() => void remove(row)}><TrashIcon /></Button>}</div></TableCell></TableRow>) : <EmptyRows canCreate={canCreate} onCreate={openCreate} />}</TableBody>
          </Table>
        </div>
        <div className="space-y-3 p-3 md:hidden">{loading ? <div className="py-12 text-center text-xs text-muted-foreground">Loading ONU inventory…</div> : rows.length ? rows.map(row => <article className="rounded-md border border-border/70 bg-background/55 p-3" key={row.public_id}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><h2 className="truncate text-sm font-semibold">{row.vendor} {row.model}</h2><p className="mt-1 break-all font-mono text-[11px] text-muted-foreground">{row.serial_number || 'Bulk stock'}</p></div><Badge variant="outline" className="shrink-0 capitalize">{statusLabel(row.status)}</Badge></div><dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs"><div><dt className="text-muted-foreground">Quantity</dt><dd className="mt-0.5">{row.quantity}</dd></div><div><dt className="text-muted-foreground">Purchase date</dt><dd className="mt-0.5">{row.purchase_date || '—'}</dd></div></dl><div className="mt-3 flex justify-end gap-1 border-t border-border/60 pt-2">{canUpdate && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11" aria-label={`Edit ${row.vendor} ${row.model}`} onClick={() => openEdit(row)}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Delete ${row.vendor} ${row.model}`} disabled={deletingId === row.id} onClick={() => void remove(row)}><TrashIcon /></Button>}</div></article>) : <div className="py-12 text-center"><p className="text-sm font-medium">No ONU stock recorded yet.</p><p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">Add a stock record to start tracking available optical network units.</p>{canCreate && <Button type="button" className="mt-4 min-h-11" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add ONU</Button>}</div>}</div>
      </CardContent>
    </Card>
    {formOpen && <OnuFormModal editing={Boolean(editing)} form={form} saving={saving} onChange={setForm} onClose={() => setFormOpen(false)} onSubmit={save} />}
  </div>
}

function EmptyRows({ canCreate, onCreate }: { canCreate: boolean; onCreate: () => void }) {
  return <TableRow><TableCell colSpan={7} className="h-56 text-center"><p className="text-sm font-medium">No ONU stock recorded yet.</p><p className="mx-auto mt-1 max-w-md text-xs text-muted-foreground">Add a stock record to start tracking available optical network units.</p>{canCreate && <Button type="button" size="sm" className="mt-4 min-h-11" onClick={onCreate}><PlusIcon data-icon="inline-start" />Add ONU</Button>}</TableCell></TableRow>
}

function OnuFormModal({ editing, form, saving, onChange, onClose, onSubmit }: { editing: boolean; form: OnuForm; saving: boolean; onChange: (form: OnuForm) => void; onClose: () => void; onSubmit: (event: FormEvent) => void }) {
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="onu-form-title"><Card className="billing-modal-card relative w-full max-w-2xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{editing ? 'Update record' : 'Create record'}</p><CardTitle id="onu-form-title">{editing ? 'Edit ONU stock' : 'Add ONU stock'}</CardTitle><CardDescription>Record hardware held in inventory without changing any OLT configuration.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close ONU form"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form className="grid gap-4 sm:grid-cols-2" onSubmit={onSubmit}><Field><FieldLabel htmlFor="onu-vendor">Vendor</FieldLabel><Input id="onu-vendor" value={form.vendor} onChange={event => onChange({ ...form, vendor: event.target.value })} placeholder="Huawei" required /></Field><Field><FieldLabel htmlFor="onu-model">Model</FieldLabel><Input id="onu-model" value={form.model} onChange={event => onChange({ ...form, model: event.target.value })} placeholder="HG8245H" required /></Field><Field><FieldLabel htmlFor="onu-serial">Serial number <span className="font-normal text-muted-foreground">(optional for bulk stock)</span></FieldLabel><Input id="onu-serial" value={form.serial_number} onChange={event => onChange({ ...form, serial_number: event.target.value })} placeholder="HWTC12345678" /></Field><Field><FieldLabel htmlFor="onu-quantity">Quantity</FieldLabel><Input id="onu-quantity" type="number" min="1" max="1000000" value={form.quantity} onChange={event => onChange({ ...form, quantity: event.target.value })} required /></Field><Field><FieldLabel htmlFor="onu-status">Status</FieldLabel><select id="onu-status" className="h-9 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.status} onChange={event => onChange({ ...form, status: event.target.value as OnuStatus })}><option value="in_stock">In stock</option><option value="reserved">Reserved</option><option value="assigned">Assigned</option><option value="faulty">Faulty</option><option value="retired">Retired</option></select></Field><Field><FieldLabel htmlFor="onu-purchase-date">Purchase date <span className="font-normal text-muted-foreground">(optional)</span></FieldLabel><Input id="onu-purchase-date" type="date" value={form.purchase_date} onChange={event => onChange({ ...form, purchase_date: event.target.value })} /></Field><Field className="sm:col-span-2"><FieldLabel htmlFor="onu-notes">Notes <span className="font-normal text-muted-foreground">(optional)</span></FieldLabel><textarea id="onu-notes" className="min-h-24 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.notes} onChange={event => onChange({ ...form, notes: event.target.value })} placeholder="Warehouse location, supplier, or other context" /></Field><div className="flex flex-wrap justify-end gap-2 border-t pt-4 sm:col-span-2"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : editing ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Add ONU</>}</Button></div></form></CardContent></Card></div>
}
