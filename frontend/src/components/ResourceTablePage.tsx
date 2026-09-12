import { useCallback, useEffect, useState } from 'react'
import type { ReactNode } from 'react'
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
import { formatDate, formatMoney } from '../lib/formatters'

type View = 'accounts' | 'plans' | 'services' | 'subscriptions' | 'invoices' | 'payments'
type Row = Record<string, any>
type Session = { token: string }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: Row } | null

const configs: Record<View, { endpoint: string; title: string; eyebrow: string; description: string; columns: string[]; createFields: CrudField[]; editFields: CrudField[] }> = {
  accounts: { endpoint: 'billing-accounts', title: 'Billing accounts', eyebrow: 'Account control', description: 'Customer billing ledgers and account status.', columns: ['Account', 'Subscriber', 'Currency', 'Status'], createFields: [{ name: 'subscriber_id', label: 'Subscriber public ID', required: true }], editFields: [{ name: 'currency', label: 'Currency', required: true }, { name: 'credit_limit_minor', label: 'Credit limit (minor units)', type: 'number' }, { name: 'status', label: 'Status', required: true }] },
  plans: { endpoint: 'plans', title: 'Plans', eyebrow: 'Commercial catalog', description: 'Commercial service plans and published speeds.', columns: ['Plan', 'Code', 'Speed', 'Status'], createFields: [{ name: 'billing_cycle_id', label: 'Billing cycle ID', type: 'number', required: true }, { name: 'code', label: 'Plan code', required: true }, { name: 'name', label: 'Plan name', required: true }, { name: 'service_type', label: 'Service type', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'status', label: 'Status', required: true }], editFields: [{ name: 'code', label: 'Plan code', required: true }, { name: 'name', label: 'Plan name', required: true }, { name: 'service_type', label: 'Service type', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'status', label: 'Status', required: true }] },
  services: { endpoint: 'subscriber-services', title: 'Subscriber services', eyebrow: 'Service registry', description: 'Provisioning-ready subscriber connections.', columns: ['Service', 'Subscriber', 'Type', 'Status'], createFields: [{ name: 'subscriber_id', label: 'Subscriber public ID', required: true }, { name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'service_type', label: 'Service type', required: true }], editFields: [{ name: 'service_type', label: 'Service type', required: true }, { name: 'status', label: 'Status', required: true }, { name: 'notes', label: 'Notes', type: 'textarea' }] },
  subscriptions: { endpoint: 'subscriptions', title: 'Subscriptions', eyebrow: 'Service commitments', description: 'Active plan assignments and billing cadence.', columns: ['Plan', 'Service', 'Price', 'Status'], createFields: [{ name: 'subscriber_service_id', label: 'Service public ID', required: true }, { name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'plan_version_id', label: 'Plan version ID', type: 'number', required: true }, { name: 'starts_on', label: 'Starts on', type: 'date', required: true }, { name: 'next_billing_date', label: 'Next billing date', type: 'date', required: true }], editFields: [{ name: 'status', label: 'Status', required: true }, { name: 'ends_on', label: 'Ends on', type: 'date' }, { name: 'next_billing_date', label: 'Next billing date', type: 'date', required: true }] },
  invoices: { endpoint: 'invoices', title: 'Invoices', eyebrow: 'Revenue cycle', description: 'Issued charges and outstanding balances.', columns: ['Invoice', 'Due date', 'Total', 'Status'], createFields: [{ name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'subscription_id', label: 'Subscription ID', type: 'number', required: true }, { name: 'issue_date', label: 'Issue date', type: 'date', required: true }, { name: 'due_date', label: 'Due date', type: 'date', required: true }], editFields: [] },
  payments: { endpoint: 'payments', title: 'Payments', eyebrow: 'Cash application', description: 'Recorded receipts and payment allocation.', columns: ['Payment', 'Reference', 'Amount', 'Status'], createFields: [{ name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'invoice_id', label: 'Invoice public ID', required: true }, { name: 'amount_minor', label: 'Amount (minor units)', type: 'number', required: true }, { name: 'payment_method', label: 'Payment method', required: true }, { name: 'reference', label: 'Reference' }], editFields: [],
  },
}

const statusBadge = (status: string) => <Badge className="billing-status-badge" variant={['cancelled', 'overdue', 'failed', 'suspended', 'void'].includes(status) ? 'destructive' : ['pending', 'draft', 'inactive'].includes(status) ? 'secondary' : 'outline'}>{status}</Badge>
const actionLabel = (row: Row) => row.legal_name || row.account_number || row.name || row.service_number || row.invoice_number || row.payment_number || row.plan_name_snapshot || 'record'
const rowId = (view: View, row: Row) => view === 'subscriptions' ? row.id : row.public_id

function rowValues(view: View, row: Row): Record<string, string> {
  if (view === 'accounts') return { currency: row.currency || '', credit_limit_minor: String(row.credit_limit_minor || 0), status: row.status || '' }
  if (view === 'plans') return { code: row.code || '', name: row.name || '', service_type: row.service_type || '', description: row.description || '', status: row.status || '' }
  if (view === 'services') return { service_type: row.service_type || '', status: row.status || '', notes: row.notes || '' }
  if (view === 'subscriptions') return { status: row.status || '', ends_on: row.ends_on || '', next_billing_date: row.next_billing_date || '' }
  if (view === 'invoices') return { invoice_number: row.invoice_number || '', due_date: formatDate(row.due_date), total: formatMoney(row.total_minor, row.currency), status: row.status || '' }
  return { payment_number: row.payment_number || '', amount: formatMoney(row.amount_minor, row.currency), payment_method: row.payment_method || '', reference: row.reference || '', status: row.status || '' }
}

function rowCells(view: View, row: Row, actions: ReactNode) {
  if (view === 'accounts') return <><TableCell><div className="font-medium">{row.account_number}</div><div className="text-[10px] text-muted-foreground">{row.public_id}</div></TableCell><TableCell>{row.subscriber?.legal_name || '—'}</TableCell><TableCell>{row.currency}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'plans') return <><TableCell><div className="font-medium">{row.name}</div><div className="text-[10px] text-muted-foreground">{row.code}</div></TableCell><TableCell>{row.service_type}</TableCell><TableCell>{row.versions?.[0] ? `${row.versions[0].download_kbps / 1000} / ${row.versions[0].upload_kbps / 1000} Mbps` : '—'}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'services') return <><TableCell><div className="font-medium">{row.service_number}</div><div className="text-[10px] text-muted-foreground">{row.public_id}</div></TableCell><TableCell>{row.subscriber?.legal_name || '—'}</TableCell><TableCell>{row.service_type}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'subscriptions') return <><TableCell><div className="font-medium">{row.plan_name_snapshot}</div><div className="text-[10px] text-muted-foreground">Service #{row.subscriber_service_id}</div></TableCell><TableCell>{row.service?.service_number || '—'}</TableCell><TableCell>{formatMoney(row.price_snapshot_minor, row.currency_snapshot)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'invoices') return <><TableCell><div className="font-medium">{row.invoice_number}</div><div className="text-[10px] text-muted-foreground">{row.billing_account?.subscriber?.legal_name || '—'}</div></TableCell><TableCell>{formatDate(row.due_date)}</TableCell><TableCell>{formatMoney(row.total_minor, row.currency)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  return <><TableCell><div className="font-medium">{row.payment_number}</div><div className="text-[10px] text-muted-foreground">{row.payment_method}</div></TableCell><TableCell>{row.reference || '—'}</TableCell><TableCell>{formatMoney(row.amount_minor, row.currency)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
}

export function ResourceTablePage({ session, view }: { session: Session; view: View }) {
  const config = configs[view]
  const [rows, setRows] = useState<Row[]>([])
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [modalError, setModalError] = useState('')
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const load = useCallback(() => apiRequest<any>(`/${config.endpoint}`, {}, session.token).then(response => setRows(response.data.data)).catch(exception => setError(getErrorMessage(exception, 'Unable to load records.'))), [config.endpoint, session.token])
  useEffect(() => { void load() }, [load])
  const close = () => { setModal(null); setModalError('') }
  const submit = async (values: Record<string, string>) => {
    if (!modal || modal.mode === 'view') return
    setLoading(true); setModalError('')
    try { const operation = apiRequest(modal.mode === 'create' ? `/${config.endpoint}` : `/${config.endpoint}/${rowId(view, modal.row!)}`, { method: modal.mode === 'create' ? 'POST' : 'PUT', body: JSON.stringify(values) }, session.token); await notify.promise(operation, { loading: modal.mode === 'create' ? `Creating ${config.title.toLowerCase().replace(/s$/, '')}…` : `Saving ${config.title.toLowerCase().replace(/s$/, '')}…`, success: modal.mode === 'create' ? 'Record created.' : 'Record updated.', error: 'Unable to save this record.' }); close(); await load() }
    catch (exception) { setModalError(getErrorMessage(exception, 'Unable to save this record.')) }
    finally { setLoading(false) }
  }
  const archive = async (row: Row) => { try { await notify.promise(apiRequest(`/${config.endpoint}/${rowId(view, row)}`, { method: 'DELETE' }, session.token), { loading: 'Archiving record…', success: 'Record archived.', error: 'Unable to archive this record.' }); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to archive this record.')) } }
  const voidRecord = async (row: Row) => { try { await notify.promise(apiRequest(`/${config.endpoint}/${rowId(view, row)}/void`, { method: 'POST' }, session.token), { loading: 'Voiding record…', success: 'Record voided.', error: 'Unable to void this record.' }); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to void this record.')) } }
  const modalFields = modal?.mode === 'view' ? [...config.editFields, ...(view === 'invoices' ? [{ name: 'invoice_number', label: 'Invoice number' }, { name: 'total', label: 'Total' }] : view === 'payments' ? [{ name: 'payment_number', label: 'Payment number' }, { name: 'amount', label: 'Amount' }] : [])] : modal?.mode === 'create' ? config.createFields : config.editFields
  const modalValues = modal?.row ? rowValues(view, modal.row) : {}
  const isFinancial = view === 'invoices' || view === 'payments'
  const visibleRows = rows.filter(row => JSON.stringify(row).toLowerCase().includes(search.toLowerCase()))
  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex items-center justify-between gap-4"><h1 className="text-sm font-semibold">{config.title}</h1><Button onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New {config.title.replace(/s$/, '').toLowerCase()}</Button></div>{error && <Alert variant="destructive"><AlertTitle>Could not load records</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<div className="billing-records flex flex-col gap-4"><div className="billing-record-toolbar flex w-full flex-col gap-4 border-b border-border/60 pb-4 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex items-center gap-2"><p className="text-sm font-semibold">{config.title}</p><span className="bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground">{rows.length} records</span></div><p className="mt-1 text-xs text-muted-foreground">Installation-wide records ready for billing operations.</p></div><div className="relative w-full sm:w-80"><MagnifyingGlassIcon size={14} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input className="h-9 w-full pl-9 shadow-[0_8px_20px_-16px_rgb(24_35_54_/_55%)]" placeholder="Filter records" value={search} onChange={event => setSearch(event.target.value)} /></div></div><Table className="min-w-[860px]"><TableHeader><TableRow>{config.columns.map(column => <TableHead key={column}>{column}</TableHead>)}<TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{visibleRows.length ? visibleRows.map(row => { const label = actionLabel(row); const actions = <TableCell className="text-right"><TableActions label={label} kind={isFinancial ? 'financial' : 'operational'} onView={() => setModal({ mode: 'view', row })} onEdit={!isFinancial && config.editFields.length ? () => setModal({ mode: 'edit', row }) : undefined} onArchive={!isFinancial ? () => { void archive(row) } : undefined} onVoid={isFinancial ? () => { void voidRecord(row) } : undefined} /></TableCell>; return <TableRow key={row.id}>{rowCells(view, row, actions)}</TableRow> }) : <TableRow><TableCell colSpan={config.columns.length + 1} className="h-48 text-center">No records found.</TableCell></TableRow>}</TableBody></Table></div><CrudModal key={modal ? `${modal.mode}-${modal.row?.id || modal.row?.public_id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? `New ${config.title.replace(/s$/, '').toLowerCase()}` : modal?.mode === 'edit' ? `Edit ${config.title.replace(/s$/, '').toLowerCase()}` : `${config.title} details`} description={modal?.mode === 'view' ? 'Review this record.' : 'Save changes through the billing API.'} fields={modalFields} initialValues={modalValues} error={modalError} loading={loading} onClose={close} onSubmit={submit} /></div>
}
