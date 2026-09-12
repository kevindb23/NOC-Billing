import { useCallback, useEffect, useState } from 'react'
import { MagnifyingGlassIcon, PlusIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage, notify } from '@/lib/notifications'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'
import { apiRequest } from '../lib/api'
import { formatDate } from '../lib/formatters'

type Row = Record<string, any>
type Session = { token: string }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: Row } | null

const subscriberFields: CrudField[] = [
  { name: 'customer_type', label: 'Subscriber type', required: true },
  { name: 'legal_name', label: 'Legal name', required: true },
  { name: 'email', label: 'Email address', type: 'text' },
  { name: 'phone', label: 'Phone number' },
  { name: 'status', label: 'Status', required: true },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const blankSubscriber = { customer_type: 'residential', legal_name: '', email: '', phone: '', status: 'active', notes: '' }

function statusBadge(status: string) {
  const destructive = ['cancelled', 'overdue', 'failed', 'suspended'].includes(status)
  const pending = ['pending', 'draft', 'inactive'].includes(status)
  return <Badge className="billing-status-badge" variant={destructive ? 'destructive' : pending ? 'secondary' : 'outline'}>{status}</Badge>
}

export function SubscribersPage({ session }: { session: Session }) {
  const [rows, setRows] = useState<Row[]>([])
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const load = useCallback(() => apiRequest<any>(`/customers?search=${encodeURIComponent(search)}`, {}, session.token).then(response => { setRows(response.data.data); setTotal(response.data.total) }).catch(exception => setError(getErrorMessage(exception, 'Unable to load subscribers.'))), [search, session.token])
  useEffect(() => { void load() }, [load])
  const close = () => { setModal(null); setModalError('') }
  const submit = async (values: Record<string, string>) => {
    if (!modal || modal.mode === 'view') return
    setLoading(true); setModalError('')
    try { const operation = apiRequest(modal.mode === 'create' ? '/customers' : `/customers/${modal.row?.public_id}`, { method: modal.mode === 'create' ? 'POST' : 'PUT', body: JSON.stringify(values) }, session.token); await notify.promise(operation, { loading: modal.mode === 'create' ? 'Creating subscriber…' : 'Saving subscriber…', success: modal.mode === 'create' ? 'Subscriber created.' : 'Subscriber updated.', error: 'Unable to save subscriber.' }); close(); await load() }
    catch (exception) { setModalError(getErrorMessage(exception, 'Unable to save subscriber.')) }
    finally { setLoading(false) }
  }
  const archive = async (row: Row) => { try { await notify.promise(apiRequest(`/customers/${row.public_id}`, { method: 'DELETE' }, session.token), { loading: 'Archiving subscriber…', success: 'Subscriber archived.', error: 'Unable to archive subscriber.' }); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to archive subscriber.')) } }
  const modalValues = modal?.row ? { customer_type: modal.row.customer_type || '', legal_name: modal.row.legal_name || '', email: modal.row.email || '', phone: modal.row.phone || '', status: modal.row.status || '', notes: modal.row.notes || '', subscriber_number: modal.row.customer_number || modal.row.subscriber_number || '', created_at: formatDate(modal.row.created_at) } : blankSubscriber
  const viewFields = modal?.mode === 'view' ? [...subscriberFields, { name: 'subscriber_number', label: 'Subscriber number' }, { name: 'created_at', label: 'Created' }] : subscriberFields
  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex items-center justify-between gap-4"><h1 className="text-sm font-semibold">Subscribers</h1><Button onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New subscriber</Button></div>{error && <Alert variant="destructive"><AlertTitle>Could not load subscribers</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<div className="billing-records flex flex-col gap-4"><div className="billing-record-toolbar flex w-full flex-col gap-4 border-b border-border/60 pb-4 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex items-center gap-2"><p className="text-sm font-semibold">Subscriber records</p><span className="bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground">{total} records</span></div><p className="mt-1 text-xs text-muted-foreground">Search and manage account holders in this installation.</p></div><div className="flex w-full gap-2 sm:w-auto"><div className="relative min-w-0 flex-1 sm:w-80"><MagnifyingGlassIcon size={14} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input className="h-9 min-w-0 pl-9 shadow-[0_8px_20px_-16px_rgb(24_35_54_/_55%)] sm:w-80" placeholder="Search name, number, or email" value={search} onChange={event => setSearch(event.target.value)} onKeyDown={event => event.key === "Enter" && load()} /></div><Button variant="outline" className="h-9 shrink-0" onClick={() => void load()}>Search</Button></div></div><Table className="min-w-[760px]"><TableHeader><TableRow><TableHead>Subscriber</TableHead><TableHead>Contact</TableHead><TableHead>Status</TableHead><TableHead>Created</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{rows.length ? rows.map(row => <TableRow key={row.id}><TableCell><div className="font-medium">{row.legal_name}</div><div className="text-[10px] text-muted-foreground">{row.customer_number || row.subscriber_number}</div></TableCell><TableCell>{row.email || 'No email'}</TableCell><TableCell>{statusBadge(row.status)}</TableCell><TableCell>{formatDate(row.created_at)}</TableCell><TableCell className="text-right"><TableActions label={row.legal_name} kind="operational" onView={() => setModal({ mode: 'view', row })} onEdit={() => setModal({ mode: 'edit', row })} onArchive={() => { void archive(row) }} /></TableCell></TableRow>) : <TableRow><TableCell colSpan={5} className="h-48 text-center">No subscriber records found.</TableCell></TableRow>}</TableBody></Table></div><CrudModal key={modal ? `${modal.mode}-${modal.row?.id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? 'New subscriber' : modal?.mode === 'edit' ? 'Edit subscriber' : 'Subscriber details'} description={modal?.mode === 'view' ? 'Review the subscriber record.' : 'Keep subscriber details current for billing operations.'} fields={viewFields} initialValues={modalValues} error={modalError} loading={loading} onClose={close} onSubmit={submit} /></div>
}
