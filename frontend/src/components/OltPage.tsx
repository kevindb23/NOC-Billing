import { useEffect, useMemo, useRef, useState } from 'react'
import { ArrowClockwiseIcon, MagnifyingGlassIcon, PlusIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { hasPermission } from '../lib/usersRoles'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'
import { RowCheckbox, SelectAllCheckbox, useTableSelection } from './TableSelection'
import { useConfirm } from './ConfirmProvider'

type OltRecord = { id: number; public_id: string; name: string; vendor: string; model?: string | null; management_endpoint?: string | null; preferred_transport?: string | null; ssh_username?: string | null; status: string; notes?: string | null }
type Props = { token: string; permissions?: string[]; isSuperadmin?: boolean; onManage?: (row: OltRecord) => void }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: OltRecord } | null

const vendors = [{ value: 'huawei', label: 'Huawei' }, { value: 'zte', label: 'ZTE' }, { value: 'hsgq', label: 'HSGQ' }]
const fields: CrudField[] = [
  { name: 'name', label: 'OLT name', required: true },
  { name: 'vendor', label: 'Vendor', required: true, options: vendors, searchable: false },
  { name: 'model', label: 'Model' },
  { name: 'management_endpoint', label: 'Management endpoint' },
  { name: 'preferred_transport', label: 'Preferred transport', required: true, options: [{ value: 'ssh', label: 'SSH' }, { value: 'telnet', label: 'Telnet' }, { value: 'api', label: 'API' }, { value: 'netconf', label: 'NETCONF' }], searchable: false },
  { name: 'username', label: 'SSH username' },
  { name: 'password', label: 'SSH password', type: 'password' },
  { name: 'status', label: 'Status', required: true, options: [{ value: 'unknown', label: 'Unknown' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], searchable: false },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const valuesFor = (row: OltRecord): Record<string, string> => ({ name: row.name || '', vendor: row.vendor || '', model: row.model || '', management_endpoint: row.management_endpoint || '', preferred_transport: row.preferred_transport || 'ssh', username: row.ssh_username || '', password: '', status: row.status || 'unknown', notes: row.notes || '' })
const vendorLabel = (vendor: string) => vendors.find(item => item.value === vendor)?.label || vendor

export function OltPage({ token, permissions, isSuperadmin, onManage }: Props) {
  const [rows, setRows] = useState<OltRecord[]>([])
  const [modal, setModal] = useState<ModalState>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; title: string; message: string } | null>(null)
  const [error, setError] = useState('')
  const [sessionStates, setSessionStates] = useState<Record<string, string>>({})
  const pendingConnectionNotices = useRef(new Set<string>())
  const [query, setQuery] = useState('')
  const [vendorFilter, setVendorFilter] = useState('all')
  const canCreate = isSuperadmin !== false || hasPermission(permissions, 'olts.create')
  const canUpdate = isSuperadmin !== false || hasPermission(permissions, 'olts.update')
  const canDelete = isSuperadmin !== false || hasPermission(permissions, 'olts.delete')
  const confirm = useConfirm()

  const load = async () => {
    setLoading(true)
    try {
      const response = await apiRequest<{ data: { data: OltRecord[] } }>('/olts?per_page=100', {}, token)
      const nextRows = response.data.data || []
      setRows(nextRows)
      const statuses = await Promise.all(nextRows.map(async row => {
        try {
          const session = await apiRequest<{ data: { status: string } }>(`/olts/${row.public_id}/session-status`, {}, token)
          return [row.public_id, session.data.status] as const
        } catch { return [row.public_id, 'stopped'] as const }
      }))
      setSessionStates(Object.fromEntries(statuses))
      setError('')
    } catch (exception) { setError(getErrorMessage(exception, 'Unable to load OLTs.')) } finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [token])
  useEffect(() => { setTestResult(null); setTesting(false) }, [modal?.mode, modal?.row?.public_id])

  const filteredRows = useMemo(() => {
    const text = query.trim().toLowerCase()
    return rows.filter(row => (!text || [row.name, row.vendor, row.model, row.management_endpoint, row.preferred_transport].some(value => value?.toLowerCase().includes(text))) && (vendorFilter === 'all' || row.vendor === vendorFilter))
  }, [query, rows, vendorFilter])
  const { selected, toggle, toggleAll, clear, allSelected } = useTableSelection(filteredRows, row => row.public_id)
  const removeSelected = async () => {
    if (!canDelete || selected.size < 2) return
    const confirmed = await confirm({ title: `Delete ${selected.size} OLTs?`, description: 'This permanently removes the selected OLT records.', confirmLabel: 'Delete selected', destructive: true })
    if (!confirmed) return
    try { await notify.promise(Promise.all([...selected].map(id => apiRequest(`/olts/${id}?permanent=1`, { method: 'DELETE' }, token))), { loading: 'Deleting selected OLTs…', success: 'Selected OLTs deleted.', error: 'Unable to delete selected OLTs.' }); clear(); await load() }
    catch (exception) { setError(getErrorMessage(exception, 'Unable to delete selected OLTs.')) }
  }

  const submit = async (values: Record<string, string>) => {
    setSaving(true)
    try {
      const path = modal?.mode === 'edit' && modal.row ? `/olts/${modal.row.public_id}` : '/olts'
      await apiRequest(path, { method: modal?.mode === 'edit' ? 'PUT' : 'POST', body: JSON.stringify(values) }, token)
      notify.success(modal?.mode === 'edit' ? 'OLT updated.' : 'OLT created.')
      setModal(null)
      await load()
    } catch (exception) { setError(getErrorMessage(exception, 'Unable to save OLT.')) } finally { setSaving(false) }
  }

  const remove = async (row: OltRecord, permanent = false) => {
    try {
      await apiRequest(`/olts/${row.public_id}${permanent ? '?permanent=1' : ''}`, { method: 'DELETE' }, token)
      notify.success(permanent ? 'OLT permanently deleted.' : 'OLT archived.')
      await load()
    } catch (exception) { setError(getErrorMessage(exception, permanent ? 'Unable to permanently delete OLT.' : 'Unable to archive OLT.')) }
  }

  const toggleConnection = async (row: OltRecord) => {
    const connected = ['connecting', 'connected'].includes(sessionStates[row.public_id])
    try {
      const action = connected ? 'disconnect' : 'connect'
      const response = await apiRequest<{ data: { status: string } }>(`/olts/${row.public_id}/${action}`, { method: 'POST' }, token)
      setSessionStates(states => ({ ...states, [row.public_id]: response.data.status }))
      if (connected) {
        pendingConnectionNotices.current.delete(row.public_id)
        notify.warning(`${row.name} SSH connection stopped.`)
      }
      if (!connected) {
        pendingConnectionNotices.current.add(row.public_id)
        if (response.data.status === 'connected') {
          pendingConnectionNotices.current.delete(row.public_id)
          notify.success(`${row.name} SSH connection established.`)
        }
        window.setTimeout(() => { void refreshSession(row) }, 1500)
      }
    } catch (exception) { setError(getErrorMessage(exception, connected ? 'Unable to stop the OLT session.' : 'Unable to connect to the OLT.')) }
  }

  const refreshSession = async (row: OltRecord) => {
    try {
      const response = await apiRequest<{ data: { status: string } }>(`/olts/${row.public_id}/session-status`, {}, token)
      setSessionStates(states => ({ ...states, [row.public_id]: response.data.status }))
      if (response.data.status === 'connected' && pendingConnectionNotices.current.has(row.public_id)) {
        pendingConnectionNotices.current.delete(row.public_id)
        notify.success(`${row.name} SSH connection established.`)
      }
      if (['connecting', 'connected'].includes(response.data.status)) window.setTimeout(() => { void refreshSession(row) }, 5000)
    } catch { /* the table can still be used if the status service is unavailable */ }
  }

  const testConnection = async (values: Record<string, string>) => {
    if (!values.management_endpoint?.trim()) {
      setTestResult({ success: false, title: 'Endpoint required', message: 'Enter a management endpoint before testing the connection.' })
      return
    }
    if (values.preferred_transport === 'ssh' && (!values.username?.trim() || !values.password?.trim())) {
      setTestResult({ success: false, title: 'SSH details required', message: 'Enter an SSH username and password before testing the connection.' })
      return
    }
    setTesting(true)
    setTestResult(null)
    try {
      const response = await apiRequest<{ data: { message: string } }>('/olts/test-connection', { method: 'POST', body: JSON.stringify({ vendor: values.vendor, management_endpoint: values.management_endpoint, preferred_transport: values.preferred_transport || 'ssh', username: values.username, password: values.password }) }, token)
      setTestResult({ success: true, title: 'Connection successful', message: response.data.message })
    } catch (exception) {
      setTestResult({ success: false, title: 'Connection failed', message: getErrorMessage(exception, 'The OLT connection test failed.') })
    } finally { setTesting(false) }
  }

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4"><div><p className="billing-eyebrow">Network / access</p><h1 className="mt-2 text-lg font-semibold tracking-tight">OLTs</h1><p className="mt-1 max-w-2xl text-xs text-muted-foreground">Manage optical line terminal inventory for access network operations.</p></div><div className="flex gap-2"><Button type="button" variant="outline" onClick={() => void load()} disabled={loading}><ArrowClockwiseIcon data-icon="inline-start" />Refresh</Button>{canDelete && selected.size > 1 && <Button type="button" variant="destructive" onClick={() => void removeSelected()}>Delete selected ({selected.size})</Button>}{canCreate && <Button type="button" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New OLT</Button>}</div></div>
    {error && <div className="border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive" role="alert"><div className="font-semibold">OLT inventory unavailable</div><div className="mt-1">{error}</div></div>}
    <Card className="billing-records overflow-hidden"><CardHeader className="gap-4 border-b border-border/60 pb-4"><div className="flex flex-wrap items-start justify-between gap-4"><div><CardTitle className="flex items-center gap-2">OLT inventory <span className="bg-muted px-2 py-1 text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle><CardDescription className="mt-2">Installation-wide OLT records ready for access network operations.</CardDescription></div><div className="text-xs text-muted-foreground">Showing {filteredRows.length} of {rows.length}</div></div><div className="flex flex-wrap gap-2"><div className="relative min-w-[240px] flex-1"><MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search name, vendor, model, endpoint, or transport" aria-label="Search OLTs" className="pl-9" /></div><select value={vendorFilter} onChange={event => setVendorFilter(event.target.value)} aria-label="Filter by vendor" className="h-9 border border-input bg-background px-3 text-xs"><option value="all">All vendors</option>{vendors.map(item => <option value={item.value} key={item.value}>{item.label}</option>)}</select>{(query || vendorFilter !== 'all') && <Button type="button" variant="ghost" size="sm" onClick={() => { setQuery(''); setVendorFilter('all') }}><XIcon data-icon="inline-start" />Clear</Button>}</div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><TableRow><TableHead className="w-10"><SelectAllCheckbox checked={allSelected} onChange={toggleAll} /></TableHead><TableHead>OLT</TableHead><TableHead>Public ID</TableHead><TableHead>Vendor</TableHead><TableHead>Model</TableHead><TableHead>Management endpoint</TableHead><TableHead>Preferred transport</TableHead><TableHead>Status</TableHead><TableHead>Notes</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{loading ? <TableRow><TableCell colSpan={9} className="h-32 text-center text-muted-foreground">Loading OLT inventory…</TableCell></TableRow> : filteredRows.length ? filteredRows.map(row => <TableRow key={row.public_id}><TableCell><RowCheckbox checked={selected.has(row.public_id)} onChange={() => toggle(row.public_id)} label="Select OLT" /></TableCell><TableCell className="font-medium">{row.name}</TableCell><TableCell className="font-mono text-[10px] text-muted-foreground">{row.public_id}</TableCell><TableCell>{vendorLabel(row.vendor)}</TableCell><TableCell>{row.model || 'Model not set'}</TableCell><TableCell className="text-xs">{row.management_endpoint || '—'}</TableCell><TableCell className="text-xs uppercase">{row.preferred_transport || 'SSH'}</TableCell><TableCell><Badge variant={(sessionStates[row.public_id] || row.status) === 'inactive' || (sessionStates[row.public_id] || row.status) === 'stopped' ? 'secondary' : 'outline'}>{sessionStates[row.public_id] || row.status}</Badge></TableCell><TableCell className="max-w-64 truncate text-xs text-muted-foreground">{row.notes || '—'}</TableCell><TableCell className="text-right"><TableActions label={row.name} kind="operational" connected={['connecting', 'connected'].includes(sessionStates[row.public_id])} onConnect={() => void toggleConnection(row)} onManage={() => onManage?.(row)} onView={() => setModal({ mode: 'view', row })} onEdit={canUpdate ? () => setModal({ mode: 'edit', row }) : undefined} onArchive={canDelete ? () => { void remove(row) } : undefined} onDelete={canDelete ? () => { void remove(row, true) } : undefined} /></TableCell></TableRow>) : <TableRow><TableCell colSpan={9} className="h-52 text-center"><p className="text-sm font-medium">{query || vendorFilter !== 'all' ? 'No matching OLTs' : 'No OLTs found.'}</p><p className="mt-1 text-xs text-muted-foreground">{query || vendorFilter !== 'all' ? 'Try changing the filters.' : 'Add an OLT to begin building your access network inventory.'}</p>{!query && vendorFilter === 'all' && canCreate && <Button type="button" size="sm" className="mt-3" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />Add first OLT</Button>}</TableCell></TableRow>}</TableBody></Table></div></CardContent></Card>
    <CrudModal key={modal ? `${modal.mode}-${modal.row?.public_id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? 'New OLT' : modal?.mode === 'edit' ? 'Edit OLT' : 'OLT details'} description={modal?.mode === 'view' ? 'Review this OLT inventory record.' : 'Save OLT inventory details for access network operations. SSH credentials are encrypted and used for device operations.'} fields={fields} initialValues={modal?.row ? valuesFor(modal.row) : { vendor: 'huawei', preferred_transport: 'ssh', status: 'unknown', username: '', password: '' }} loading={saving} onClose={() => setModal(null)} onSubmit={submit} renderExtra={() => testResult && <div className={`mt-4 border p-3 text-xs ${testResult.success ? 'border-primary/30 bg-primary/5' : 'border-destructive/30 bg-destructive/5 text-destructive'}`} role="status"><div className="font-semibold">{testResult.title}</div><div className="mt-1">{testResult.message}</div></div>} renderActions={values => <Button type="button" variant="outline" onClick={() => void testConnection(values)} disabled={testing || saving}>{testing ? 'Testing…' : 'Test connection'}</Button>} />
  </div>
}
