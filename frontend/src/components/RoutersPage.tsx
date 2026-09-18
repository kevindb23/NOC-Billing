import { useEffect, useMemo, useRef, useState } from 'react'
import { ArrowClockwiseIcon, MagnifyingGlassIcon, PlusIcon, ShareNetworkIcon, XIcon } from '@phosphor-icons/react'
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

type RouterRecord = {
  id: number
  public_id: string
  name: string
  vendor: string
  model?: string | null
  management_endpoint?: string | null
  preferred_transport?: string | null
  status: string
  last_contact_at?: string | null
  username?: string | null
  password?: string | null
}

type Props = { token: string; permissions?: string[]; isSuperadmin?: boolean }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: RouterRecord } | null

const vendorOptions = [
  { value: 'mikrotik', label: 'MikroTik' },
  { value: 'juniper', label: 'Juniper' },
]

const fields: CrudField[] = [
  { name: 'name', label: 'Router name', required: true },
  { name: 'vendor', label: 'Vendor', required: true, options: vendorOptions, searchable: false },
  { name: 'model', label: 'Model' },
  { name: 'management_endpoint', label: 'Management endpoint', required: true },
  { name: 'preferred_transport', label: 'Preferred transport', options: [{ value: 'ssh', label: 'SSH' }], searchable: false },
  { name: 'username', label: 'SSH username', required: true },
  { name: 'password', label: 'SSH password', type: 'password', required: true },
  { name: 'status', label: 'Status', required: true, options: [{ value: 'unknown', label: 'Unknown' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'offline', label: 'Offline' }], searchable: false },
]

const rowValues = (row: RouterRecord): Record<string, string> => ({
  name: row.name || '',
  vendor: row.vendor || '',
  model: row.model || '',
  management_endpoint: row.management_endpoint || '',
  preferred_transport: row.preferred_transport || '',
  username: row.username || '',
  password: row.password || '',
  status: row.status || 'unknown',
})

const vendorLabel = (vendor: string) => vendorOptions.find(option => option.value === vendor)?.label || vendor
const statusBadge = (status: string) => <Badge variant={['inactive', 'offline'].includes(status) ? 'secondary' : 'outline'}>{status}</Badge>

export function RoutersPage({ token, permissions, isSuperadmin }: Props) {
  const [rows, setRows] = useState<RouterRecord[]>([])
  const [modal, setModal] = useState<ModalState>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<{ success: boolean; title: string; message: string } | null>(null)
  const [error, setError] = useState('')
  const [query, setQuery] = useState('')
  const [vendorFilter, setVendorFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [sessionStates, setSessionStates] = useState<Record<string, string>>({})
  const pendingConnectionNotices = useRef(new Set<string>())
  const canCreate = isSuperadmin !== false || hasPermission(permissions, 'routers.create')
  const canUpdate = isSuperadmin !== false || hasPermission(permissions, 'routers.update')
  const canDelete = isSuperadmin !== false || hasPermission(permissions, 'routers.delete')
  const confirm = useConfirm()

  const load = async () => {
    setLoading(true)
    try {
      const response = await apiRequest<{ data: { data: RouterRecord[] } }>('/routers?per_page=100', {}, token)
      const nextRows = response.data.data || []
      setRows(nextRows)
      const statuses = await Promise.all(nextRows.map(async row => { try { const result = await apiRequest<{ data: { status: string } }>(`/routers/${row.public_id}/session-status`, {}, token); return [row.public_id, result.data.status] as const } catch { return [row.public_id, 'stopped'] as const } }))
      setSessionStates(Object.fromEntries(statuses))
      setError('')
    } catch (exception) {
      setError(getErrorMessage(exception, 'Unable to load routers.'))
    } finally {
      setLoading(false)
    }
  }

  const refreshSession = async (row: RouterRecord) => {
    try {
      const response = await apiRequest<{ data: { status: string; error?: string } }>(`/routers/${row.public_id}/session-status`, {}, token)
      setSessionStates(current => ({ ...current, [row.public_id]: response.data.status }))
      if (response.data.status === 'connected' && pendingConnectionNotices.current.has(row.public_id)) { pendingConnectionNotices.current.delete(row.public_id); notify.success(`${row.name} SSH connection established.`) }
      if (['connecting', 'connected', 'disconnected'].includes(response.data.status) && response.data.status !== 'stopped') window.setTimeout(() => { void refreshSession(row) }, 5000)
    } catch { /* the next inventory refresh will retry */ }
  }

  const toggleConnection = async (row: RouterRecord) => {
    const connected = ['connecting', 'connected', 'disconnected'].includes(sessionStates[row.public_id])
    try {
      if (!connected) pendingConnectionNotices.current.add(row.public_id)
      const response = await apiRequest<{ data: { status: string } }>(`/routers/${row.public_id}/${connected ? 'disconnect' : 'connect'}`, { method: 'POST' }, token)
      setSessionStates(current => ({ ...current, [row.public_id]: response.data.status }))
      notify[connected ? 'info' : 'info'](connected ? `${row.name} SSH connection stopped.` : `${row.name} SSH connection is starting.`)
      if (!connected) void refreshSession(row)
    } catch (exception) { notify.error(getErrorMessage(exception, connected ? 'Unable to stop the router session.' : 'Unable to connect to the router.')) }
  }

  useEffect(() => { void load() }, [token])
  useEffect(() => {
    setTesting(false)
    setTestResult(null)
  }, [modal?.mode, modal?.row?.public_id])

  const filteredRows = useMemo(() => {
    const normalized = query.trim().toLowerCase()
    return rows.filter(row => {
      const matchesQuery = !normalized || [row.name, row.vendor, row.model, row.management_endpoint].some(value => value?.toLowerCase().includes(normalized))
      return matchesQuery && (vendorFilter === 'all' || row.vendor === vendorFilter) && (statusFilter === 'all' || row.status === statusFilter)
    })
  }, [query, rows, statusFilter, vendorFilter])
  const { selected, toggle, toggleAll, clear, allSelected } = useTableSelection(filteredRows, row => row.public_id)
  const removeSelected = async () => {
    if (!canDelete || selected.size < 2) return
    const confirmed = await confirm({ title: `Delete ${selected.size} routers?`, description: 'This permanently removes the selected router records.', confirmLabel: 'Delete selected', destructive: true })
    if (!confirmed) return
    try { await notify.promise(Promise.all([...selected].map(id => apiRequest(`/routers/${id}?permanent=1`, { method: 'DELETE' }, token))), { loading: 'Deleting selected routers…', success: 'Selected routers deleted.', error: 'Unable to delete selected routers.' }); clear(); await load() }
    catch (exception) { setError(getErrorMessage(exception, 'Unable to delete selected routers.')) }
  }

  const clearFilters = () => {
    setQuery('')
    setVendorFilter('all')
    setStatusFilter('all')
  }

  const submit = async (values: Record<string, string>) => {
    setSaving(true)
    try {
      const path = modal?.mode === 'edit' && modal.row ? `/routers/${modal.row.public_id}` : '/routers'
      await apiRequest(path, { method: modal?.mode === 'edit' ? 'PUT' : 'POST', body: JSON.stringify(values) }, token)
      notify.success(modal?.mode === 'edit' ? 'Router updated.' : 'Router created.')
      setModal(null)
      await load()
    } catch (exception) {
      setError(getErrorMessage(exception, 'Unable to save router.'))
    } finally {
      setSaving(false)
    }
  }

  const testConnection = async (values: Record<string, string>) => {
    const missing = [
      ['management_endpoint', 'Management endpoint'],
      ['username', 'SSH username'],
      ['password', 'SSH password'],
    ].filter(([name]) => !values[name]?.trim()).map(([, label]) => label)
    if (missing.length) {
      setTestResult({ success: false, title: 'SSH details required', message: `Enter ${missing.join(' and ').toLowerCase()} before testing the connection.` })
      return
    }

    setTesting(true)
    setTestResult(null)
    try {
      const response = await apiRequest<{ data: { message: string } }>('/routers/test-connection', {
        method: 'POST',
        body: JSON.stringify({
          vendor: values.vendor,
          management_endpoint: values.management_endpoint,
          preferred_transport: values.preferred_transport || 'ssh',
          username: values.username,
          password: values.password,
        }),
      }, token)
      setTestResult({ success: true, title: 'SSH connection successful', message: response.data.message })
    } catch (exception) {
      setTestResult({ success: false, title: 'SSH connection failed', message: getErrorMessage(exception, 'SSH connection test failed.') })
    } finally {
      setTesting(false)
    }
  }

  const remove = async (row: RouterRecord, permanent = false) => {
    try {
      await apiRequest(`/routers/${row.public_id}${permanent ? '?permanent=1' : ''}`, { method: 'DELETE' }, token)
      notify.success(permanent ? 'Router permanently deleted.' : 'Router archived.')
      await load()
    } catch (exception) {
      setError(getErrorMessage(exception, permanent ? 'Unable to permanently delete router.' : 'Unable to archive router.'))
    }
  }

  const openEdit = async (row: RouterRecord) => {
    try {
      const response = await apiRequest<{ data: RouterRecord }>(`/routers/${row.public_id}`, {}, token)
      setModal({ mode: 'edit', row: response.data })
    } catch (exception) {
      setError(getErrorMessage(exception, 'Unable to load router credentials.'))
    }
  }

  const hasFilters = Boolean(query || vendorFilter !== 'all' || statusFilter !== 'all')

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4">
      <div>
        <p className="billing-eyebrow">Network / inventory</p>
        <h1 className="mt-2 text-lg font-semibold tracking-tight">Routers</h1>
        <p className="mt-1 max-w-2xl text-xs text-muted-foreground">Register and manage multi-vendor routers used by network operations.</p>
      </div>
      <div className="flex items-center gap-2">
        <Button type="button" variant="outline" onClick={() => void load()} disabled={loading}><ArrowClockwiseIcon data-icon="inline-start" />Refresh</Button>
        {canDelete && selected.size > 1 && <Button type="button" variant="destructive" onClick={() => void removeSelected()}>Delete selected ({selected.size})</Button>}{canCreate && <Button type="button" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New router</Button>}
      </div>
    </div>

    {error && <div className="border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive" role="alert"><div className="font-semibold">Router inventory unavailable</div><div className="mt-1 text-destructive/80">{error}</div></div>}

    <Card className="billing-records overflow-hidden">
      <CardHeader className="gap-4 border-b border-border/60 pb-4">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <CardTitle className="flex items-center gap-2">Router inventory <span className="bg-muted px-2 py-1 font-mono text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle>
            <CardDescription className="mt-2">SSH is the first supported transport. Credentials and device commands are not stored or executed yet.</CardDescription>
          </div>
          <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-muted-foreground">Showing {filteredRows.length} of {rows.length}</div>
        </div>
        <div className="flex flex-wrap items-center gap-2" aria-label="Router filters">
          <div className="relative min-w-[240px] flex-1">
            <MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <Input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search name, vendor, model, or endpoint" aria-label="Search routers" className="pl-9" />
          </div>
          <select value={vendorFilter} onChange={event => setVendorFilter(event.target.value)} aria-label="Filter by vendor" className="h-9 border border-input bg-background px-3 text-xs">
            <option value="all">All vendors</option>
            {vendorOptions.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
          <select value={statusFilter} onChange={event => setStatusFilter(event.target.value)} aria-label="Filter by status" className="h-9 border border-input bg-background px-3 text-xs">
            <option value="all">All statuses</option>
            {['unknown', 'active', 'inactive', 'offline'].map(status => <option key={status} value={status}>{status}</option>)}
          </select>
          {hasFilters && <Button type="button" variant="ghost" size="sm" onClick={clearFilters}><XIcon data-icon="inline-start" />Clear</Button>}
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <Table className="min-w-[980px]">
            <TableHeader><TableRow><TableHead className="w-10"><SelectAllCheckbox checked={allSelected} onChange={toggleAll} /></TableHead><TableHead>Router</TableHead><TableHead>Vendor / model</TableHead><TableHead>Management endpoint</TableHead><TableHead>Driver / transport</TableHead><TableHead>Status</TableHead><TableHead>Last contact</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>
              {loading ? <TableRow><TableCell colSpan={8} className="h-32 text-center text-muted-foreground">Loading router inventory…</TableCell></TableRow> : filteredRows.length ? filteredRows.map(row => <TableRow key={row.public_id}>
                <TableCell><RowCheckbox checked={selected.has(row.public_id)} onChange={() => toggle(row.public_id)} label="Select router" /></TableCell><TableCell><div className="font-medium">{row.name}</div><div className="font-mono text-[10px] text-muted-foreground">{row.public_id}</div></TableCell>
                <TableCell><div>{vendorLabel(row.vendor)}</div><div className="text-xs text-muted-foreground">{row.model || 'Model not set'}</div></TableCell>
                <TableCell className="font-mono text-xs">{row.management_endpoint || '—'}</TableCell>
                <TableCell><div>Generic router driver</div><div className="text-xs uppercase text-muted-foreground">{row.preferred_transport || 'SSH'}</div></TableCell>
                <TableCell>{statusBadge(row.status)}</TableCell>
                <TableCell className="text-xs text-muted-foreground">{row.last_contact_at || 'Never contacted'}</TableCell>
                <TableCell className="text-right"><TableActions label={row.name} kind="operational" connected={['connecting', 'connected', 'disconnected'].includes(sessionStates[row.public_id])} onConnect={() => void toggleConnection(row)} onView={() => setModal({ mode: 'view', row })} onEdit={canUpdate ? () => { void openEdit(row) } : undefined} onArchive={canDelete ? () => { void remove(row) } : undefined} onDelete={canDelete ? () => { void remove(row, true) } : undefined} /></TableCell>
              </TableRow>) : <TableRow><TableCell colSpan={7} className="h-52"><div className="flex flex-col items-center justify-center gap-2 text-center"><ShareNetworkIcon size={28} className="text-muted-foreground/50" aria-hidden="true" /><p className="text-sm font-medium">{hasFilters ? 'No matching routers' : 'No routers found.'}</p><p className="max-w-sm text-xs text-muted-foreground">{hasFilters ? 'Try changing the filters or search terms.' : 'Add a router to begin building your network inventory.'}</p>{hasFilters ? <Button type="button" variant="outline" size="sm" onClick={clearFilters}>Clear filters</Button> : canCreate && <Button type="button" size="sm" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />Add first router</Button>}</div></TableCell></TableRow>}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <CrudModal key={modal ? `${modal.mode}-${modal.row?.public_id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? 'New router' : modal?.mode === 'edit' ? 'Edit router' : 'Router details'} description={modal?.mode === 'view' ? 'Review this router inventory record.' : 'Save router inventory details. SSH credentials are encrypted and used for SSH connection tests.'} fields={fields} initialValues={modal?.row ? rowValues(modal.row) : { vendor: 'mikrotik', preferred_transport: 'ssh', status: 'unknown', username: '', password: '' }} loading={saving} onClose={() => setModal(null)} onSubmit={submit} renderExtra={() => testResult && <div className={`mt-4 border p-3 text-xs ${testResult.success ? 'border-primary/30 bg-primary/5 text-foreground' : 'border-destructive/30 bg-destructive/5 text-destructive'}`} role="status"><div className="font-semibold">{testResult.title}</div><div className="mt-1">{testResult.message}</div></div>} renderActions={values => <Button type="button" variant="outline" onClick={() => void testConnection(values)} disabled={testing || saving}>{testing ? 'Testing SSH…' : 'Test SSH connection'}</Button>} />
  </div>
}
