import { useCallback, useEffect, useState } from 'react'
import { CaretDownIcon, DownloadSimpleIcon, MagnifyingGlassIcon, PlusIcon, UploadSimpleIcon } from '@phosphor-icons/react'
import * as XLSX from 'xlsx'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage, notify } from '@/lib/notifications'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'
import { RowCheckbox, SelectAllCheckbox, useTableSelection } from './TableSelection'
import { useConfirm } from './ConfirmProvider'
import { apiRequest } from '../lib/api'
import { formatDate } from '../lib/formatters'

type Row = Record<string, any>
type Session = { token: string }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: Row } | null
type ImportRow = Record<string, string>

const importHeaders = ['SUBSCRIBER TYPE', 'LEGAL NAME', 'EMAIL', 'PHONE NUMBER', 'PPP-USER', 'PPP-PASS', 'PLAN', 'NOTES']

const subscriberFields: CrudField[] = [
  { name: 'customer_type', label: 'Subscriber type', required: true, searchable: false, options: [{ value: 'residential', label: 'Residential' }, { value: 'business', label: 'Business' }, { value: 'corporate', label: 'Corporate' }] },
  { name: 'legal_name', label: 'Legal name', required: true },
  { name: 'email', label: 'Email address', type: 'text' },
  { name: 'phone', label: 'Phone number' },
  { name: 'portal_username', label: 'Username', required: true, placeholder: 'Customer portal username' },
  { name: 'portal_password', label: 'Password', required: true, type: 'password', placeholder: 'Customer portal password' },
  { name: 'ppp_username', label: 'PPP Username', required: true, placeholder: 'PPPoE username' },
  { name: 'ppp_password', label: 'PPP Password', required: true, type: 'password', placeholder: 'PPPoE password' },
  { name: 'status', label: 'Status', required: true, searchable: false, options: [{ value: 'inactive', label: 'Inactive' }, { value: 'active', label: 'Active' }] },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const blankSubscriber = { customer_type: 'residential', legal_name: '', email: '', phone: '', portal_username: '', portal_password: '', ppp_username: '', ppp_password: '', status: 'active', notes: '' }

function statusBadge(status: string) {
  const destructive = ['cancelled', 'overdue', 'failed', 'suspended'].includes(status)
  const pending = ['pending', 'draft', 'inactive'].includes(status)
  return <Badge className="billing-status-badge" variant={destructive ? 'destructive' : pending ? 'secondary' : 'outline'}>{status}</Badge>
}

function SubscriberImportModal({ fileName, rows, importing, onFile, onClose, onImport }: { fileName: string; rows: ImportRow[]; importing: boolean; onFile: (file: File) => void; onClose: () => void; onImport: () => void }) {
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="subscriber-import-title"><div className="billing-modal-card w-full max-w-5xl bg-background p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="billing-modal-eyebrow">Bulk import</p><h2 id="subscriber-import-title" className="text-lg font-semibold">Import subscribers</h2><p className="mt-1 text-xs text-muted-foreground">Upload CSV, TXT, or XLSX using the subscriber template columns.</p></div><button type="button" className="text-muted-foreground hover:text-foreground" onClick={onClose} aria-label="Close import modal">×</button></div><label className="mt-5 grid min-h-28 cursor-pointer place-items-center border border-dashed border-border/80 bg-muted/20 p-5 text-center text-xs hover:bg-muted/40"><input type="file" className="sr-only" accept=".csv,.txt,.xlsx" onChange={event => { const file = event.target.files?.[0]; if (file) onFile(file) }} /><span>{fileName ? <><span className="font-medium">{fileName}</span><br /><span className="text-muted-foreground">{rows.length} data row{rows.length === 1 ? '' : 's'} detected</span></> : <>Choose a CSV, TXT, or XLSX file<br /><span className="text-muted-foreground">Blank portal credentials will be generated automatically.</span></>}</span></label>{rows.length > 0 && <div className="mt-4 overflow-x-auto border border-border/60"><table className="w-full min-w-[980px] text-left text-xs"><thead className="bg-muted/30 text-muted-foreground"><tr>{importHeaders.map(header => <th className="px-3 py-2 font-normal" key={header}>{header}</th>)}</tr></thead><tbody>{rows.slice(0, 10).map((row, index) => <tr className="border-t border-border/60" key={index}>{importHeaders.map(header => <td className="max-w-40 truncate px-3 py-2" key={header}>{row[header] || '-'}</td>)}</tr>)}{rows.length > 10 && <tr><td colSpan={importHeaders.length} className="px-3 py-2 text-muted-foreground">Showing first 10 rows of {rows.length}.</td></tr>}</tbody></table></div>}<div className="mt-5 flex flex-wrap justify-end gap-2 border-t border-border/60 pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="button" disabled={!rows.length || importing} onClick={onImport}>{importing ? 'Importing…' : `Import ${rows.length || ''} subscribers`}</Button></div></div></div>
}

export function SubscribersPage({ session }: { session: Session }) {
  const [rows, setRows] = useState<Row[]>([])
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const [importOpen, setImportOpen] = useState(false)
  const [importRows, setImportRows] = useState<ImportRow[]>([])
  const [importFileName, setImportFileName] = useState('')
  const [importing, setImporting] = useState(false)
  const [templateOpen, setTemplateOpen] = useState(false)
  const { selected, toggle, toggleAll, clear, allSelected } = useTableSelection(rows, row => row.public_id)
  const confirm = useConfirm()
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
  const remove = async (row: Row) => { try { await notify.promise(apiRequest(`/customers/${row.public_id}?permanent=1`, { method: 'DELETE' }, session.token), { loading: 'Deleting subscriber…', success: 'Subscriber permanently deleted.', error: 'Unable to permanently delete subscriber.' }); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to permanently delete subscriber.')) } }
  const removeSelected = async () => {
    if (selected.size < 2) return
    const confirmed = await confirm({ title: `Delete ${selected.size} subscribers?`, description: 'This permanently removes the selected subscriber records and cannot be undone.', confirmLabel: 'Delete selected', destructive: true })
    if (!confirmed) return
    try {
      await notify.promise(Promise.all([...selected].map(id => apiRequest(`/customers/${id}?permanent=1`, { method: 'DELETE' }, session.token))), { loading: 'Deleting selected subscribers…', success: 'Selected subscribers deleted.', error: 'Unable to delete selected subscribers.' })
      clear(); await load()
    } catch (exception) { setError(getErrorMessage(exception, 'Unable to delete selected subscribers.')) }
  }
  const generatedCredential = (prefix: string) => `${prefix}-${Math.random().toString(36).slice(2, 10)}`
  const downloadTemplate = (format: 'csv' | 'txt' | 'xlsx') => {
    const rows = [importHeaders, ['residential', '', '', '', '', '', '', '', '', '']]
    const worksheet = XLSX.utils.aoa_to_sheet(rows)
    if (format === 'xlsx') {
      const workbook = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(workbook, worksheet, 'Subscribers'); XLSX.writeFile(workbook, 'subscriber-import-template.xlsx')
    } else {
      const content = rows.map(row => row.map(value => `"${value.replaceAll('"', '""')}"`).join(format === 'txt' ? '\t' : ',')).join('\n')
      const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([content], { type: format === 'txt' ? 'text/plain' : 'text/csv' })); link.download = `subscriber-import-template.${format}`; link.click(); URL.revokeObjectURL(link.href)
    }
    setTemplateOpen(false)
  }
  const parseImportFile = async (file: File) => {
    const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array' })
    const sheet = workbook.Sheets[workbook.SheetNames[0]]
    const values = XLSX.utils.sheet_to_json<string[]>(sheet, { header: 1, defval: '' })
    const headers = (values[0] || []).map(value => String(value).trim().toUpperCase())
    const parsed = values.slice(1).filter(row => row.some(value => String(value).trim())).map(row => {
      const item = Object.fromEntries(importHeaders.map(header => [header, String(row[headers.indexOf(header)] ?? '').trim()]))
      item['SUBSCRIBER TYPE'] ||= 'residential'
      item['LEGAL NAME'] ||= item['PPP-USER']
      item['PPP-PASS'] ||= generatedCredential('ppp')
      return item
    })
    setImportFileName(file.name); setImportRows(parsed)
  }
  const importSubscribers = async () => {
    if (!importRows.length) return
    setImporting(true)
    try {
      let created = 0
      for (const row of importRows) {
        const pppUser = row['PPP-USER'] || ''
        const notes = [row.NOTES, row.PLAN ? `Imported plan: ${row.PLAN}` : ''].filter(Boolean).join('\n')
        await apiRequest('/customers', { method: 'POST', body: JSON.stringify({ customer_type: row['SUBSCRIBER TYPE'] || 'residential', legal_name: row['LEGAL NAME'] || pppUser, email: row.EMAIL || '', phone: row['PHONE NUMBER'] || '', portal_username: generatedCredential('user'), portal_password: generatedCredential('pass'), ppp_username: pppUser, ppp_password: row['PPP-PASS'] || generatedCredential('ppp'), status: 'active', notes }) }, session.token)
        created += 1
      }
      notify.success(`${created} subscriber${created === 1 ? '' : 's'} imported.`); setImportOpen(false); setImportRows([]); setImportFileName(''); await load()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to import subscribers.')) }
    finally { setImporting(false) }
  }
  const modalValues = modal?.row ? { customer_type: modal.row.customer_type || '', legal_name: modal.row.legal_name || '', email: modal.row.email || '', phone: modal.row.phone || '', portal_username: modal.row.portal_username || '', portal_password: '', ppp_username: modal.row.ppp_username || '', ppp_password: '', status: modal.row.status || '', notes: modal.row.notes || '', subscriber_number: modal.row.customer_number || modal.row.subscriber_number || '', created_at: formatDate(modal.row.created_at) } : blankSubscriber
  const editableFields = subscriberFields.map(field => modal?.mode === 'edit' && ['portal_password', 'ppp_password'].includes(field.name) ? { ...field, required: false, placeholder: 'Leave blank to keep current' } : field)
  const viewFields = modal?.mode === 'view' ? [...subscriberFields, { name: 'subscriber_number', label: 'Subscriber number' }, { name: 'created_at', label: 'Created' }] : editableFields
  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex flex-wrap items-center justify-between gap-4"><h1 className="text-sm font-semibold">Subscribers</h1><div className="flex flex-wrap items-center justify-end gap-2">{selected.size > 1 && <Button variant="destructive" onClick={() => void removeSelected()}>Delete selected ({selected.size})</Button>}<Button variant="outline" onClick={() => setImportOpen(true)}><UploadSimpleIcon data-icon="inline-start" />Import</Button><div className="relative"><Button variant="outline" onClick={() => setTemplateOpen(current => !current)}>Download template<CaretDownIcon data-icon="inline-end" /></Button>{templateOpen && <div className="absolute right-0 z-20 mt-1 w-36 border border-border bg-background p-1 shadow-lg"><button type="button" className="block w-full px-3 py-2 text-left text-xs hover:bg-muted" onClick={() => downloadTemplate('csv')}><DownloadSimpleIcon className="mr-2 inline" />CSV</button><button type="button" className="block w-full px-3 py-2 text-left text-xs hover:bg-muted" onClick={() => downloadTemplate('txt')}><DownloadSimpleIcon className="mr-2 inline" />TXT</button><button type="button" className="block w-full px-3 py-2 text-left text-xs hover:bg-muted" onClick={() => downloadTemplate('xlsx')}><DownloadSimpleIcon className="mr-2 inline" />XLSX</button></div>}</div><Button onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New subscriber</Button></div></div>{error && <Alert variant="destructive"><AlertTitle>Could not load subscribers</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<div className="billing-records flex flex-col gap-4"><div className="billing-record-toolbar flex w-full flex-col gap-4 border-b border-border/60 pb-4 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex items-center gap-2"><p className="text-sm font-semibold">Subscriber records</p><span className="bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground">{total} records</span></div><p className="mt-1 text-xs text-muted-foreground">Search and manage account holders in this installation.</p></div><div className="flex w-full gap-2 sm:w-auto"><div className="relative min-w-0 flex-1 sm:w-80"><MagnifyingGlassIcon size={14} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input className="h-9 min-w-0 pl-9 shadow-[0_8px_20px_-16px_rgb(24_35_54_/_55%)] sm:w-80" placeholder="Search name, number, or email" value={search} onChange={event => setSearch(event.target.value)} onKeyDown={event => event.key === "Enter" && load()} /></div><Button variant="outline" className="h-9 shrink-0" onClick={() => void load()}>Search</Button></div></div><Table className="min-w-[860px]"><TableHeader><TableRow><TableHead className="w-10"><SelectAllCheckbox checked={allSelected} onChange={toggleAll} /></TableHead><TableHead>Subscriber</TableHead><TableHead>Public ID</TableHead><TableHead>Contact</TableHead><TableHead>Plan</TableHead><TableHead>Status</TableHead><TableHead>Created</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{rows.length ? rows.map(row => { const subscription = row.subscriber_services?.flatMap((service: Row) => service.subscriptions || []).find((item: Row) => item.status === 'active') || row.subscriber_services?.flatMap((service: Row) => service.subscriptions || [])[0]; return <TableRow key={row.id}><TableCell><RowCheckbox checked={selected.has(row.public_id)} onChange={() => toggle(row.public_id)} label={`Select `} /></TableCell><TableCell className="font-medium">{row.legal_name}</TableCell><TableCell className="font-mono text-[10px] text-muted-foreground">{row.public_id}</TableCell><TableCell>{row.email || 'No email'}</TableCell><TableCell>{subscription?.plan_name_snapshot || '—'}</TableCell><TableCell>{statusBadge(row.status)}</TableCell><TableCell>{formatDate(row.created_at)}</TableCell><TableCell className="text-right"><TableActions label={row.legal_name} kind="operational" onView={() => setModal({ mode: 'view', row })} onEdit={() => setModal({ mode: 'edit', row })} onArchive={() => { void archive(row) }} onDelete={() => { void remove(row) }} /></TableCell></TableRow> }) : <TableRow><TableCell colSpan={8} className="h-48 text-center">No subscriber records found.</TableCell></TableRow>}</TableBody></Table></div><CrudModal key={modal ? `${modal.mode}-${modal.row?.id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? 'New subscriber' : modal?.mode === 'edit' ? 'Edit subscriber' : 'Subscriber details'} description={modal?.mode === 'view' ? 'Review the subscriber record.' : 'Keep subscriber details current for billing operations.'} fields={viewFields} initialValues={modalValues} error={modalError} loading={loading} onClose={close} onSubmit={submit} />{importOpen && <SubscriberImportModal fileName={importFileName} rows={importRows} importing={importing} onFile={file => void parseImportFile(file)} onClose={() => { setImportOpen(false); setImportRows([]); setImportFileName('') }} onImport={() => void importSubscribers()} />}</div>
}
