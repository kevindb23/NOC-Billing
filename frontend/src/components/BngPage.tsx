import { useEffect, useMemo, useRef, useState } from 'react'
import { MagnifyingGlassIcon, PlusIcon, ShareNetworkIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { apiRequest } from '@/lib/api'
import { getErrorMessage, notify } from '@/lib/notifications'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'
import { RowCheckbox, SelectAllCheckbox, useTableSelection } from './TableSelection'
import { useConfirm } from './ConfirmProvider'

type BngRecord = { id: number; public_id: string; name: string; vendor: string; model: string | null; management_endpoint: string; preferred_transport: string; ssh_username?: string | null; status: string; notes: string | null }
type Props = { token: string; onManage?: (publicId: string) => void }
type ModalState = { mode: 'create' | 'edit'; row?: BngRecord } | null

const fields: CrudField[] = [
  { name: 'name', label: 'BNG name', required: true },
  { name: 'vendor', label: 'Vendor', required: true, options: [{ value: 'linux', label: 'Linux' }, { value: 'mikrotik', label: 'MikroTik' }], searchable: false },
  { name: 'model', label: 'Model' },
  { name: 'management_endpoint', label: 'Management endpoint', required: true },
  { name: 'preferred_transport', label: 'Preferred transport', required: true, options: [{ value: 'ssh', label: 'SSH' }], searchable: false },
  { name: 'username', label: 'SSH username', required: true },
  { name: 'password', label: 'SSH password', type: 'password', placeholder: 'Leave blank to keep current password', required: true },
  { name: 'status', label: 'Status', required: true, options: [{ value: 'unknown', label: 'Unknown' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], searchable: false },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const sessionActive = (status?: string) => ['connecting', 'connected', 'disconnected'].includes(status || '')

export function BngPage({ token, onManage }: Props) {
  const [rows, setRows] = useState<BngRecord[]>([])
  const [sessionStates, setSessionStates] = useState<Record<string, string>>({})
  const [modal, setModal] = useState<ModalState>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [query, setQuery] = useState('')
  const [vendor, setVendor] = useState('all')
  const [status, setStatus] = useState('all')
  const pendingConnectionNotices = useRef(new Set<string>())
  const { selected, toggle, toggleAll, clear, allSelected } = useTableSelection(rows, row => row.public_id)
  const confirm = useConfirm()

  const load = async () => {
    setLoading(true)
    try {
      const response = await apiRequest<{ data: BngRecord[] }>('/bngs', {}, token)
      const nextRows = response.data || []
      setRows(nextRows)
      const states = await Promise.all(nextRows.map(async row => {
        try {
          const result = await apiRequest<{ data: { status: string } }>(`/bngs/${row.public_id}/session-status`, {}, token)
          return [row.public_id, result.data.status] as const
        } catch { return [row.public_id, 'stopped'] as const }
      }))
      setSessionStates(Object.fromEntries(states))
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load BNGs.')) }
    finally { setLoading(false) }
  }

  const refreshSession = async (row: BngRecord) => {
    try {
      const response = await apiRequest<{ data: { status: string } }>(`/bngs/${row.public_id}/session-status`, {}, token)
      const nextStatus = response.data.status
      setSessionStates(current => ({ ...current, [row.public_id]: nextStatus }))
      if (nextStatus === 'connected' && pendingConnectionNotices.current.has(row.public_id)) {
        pendingConnectionNotices.current.delete(row.public_id)
        notify.success(`${row.name} SSH connection established.`)
      }
      if (sessionActive(nextStatus)) window.setTimeout(() => { void refreshSession(row) }, 5000)
    } catch { /* retry on the next scheduled inventory refresh */ }
  }

  const toggleConnection = async (row: BngRecord) => {
    const connected = sessionActive(sessionStates[row.public_id])
    try {
      if (!connected) pendingConnectionNotices.current.add(row.public_id)
      const response = await apiRequest<{ data: { status: string } }>(`/bngs/${row.public_id}/${connected ? 'disconnect' : 'connect'}`, { method: 'POST' }, token)
      setSessionStates(current => ({ ...current, [row.public_id]: response.data.status }))
      notify.info(connected ? `${row.name} SSH connection stopped.` : `${row.name} SSH connection is starting.`)
      if (!connected) void refreshSession(row)
    } catch (exception) {
      pendingConnectionNotices.current.delete(row.public_id)
      notify.error(getErrorMessage(exception, connected ? 'Unable to stop the BNG session.' : 'Unable to connect to the BNG.'))
    }
  }

  const save = async (values: Record<string, string>) => {
    setSaving(true)
    try {
      const isEdit = Boolean(modal?.row)
      const path = isEdit ? `/bngs/${modal?.row?.public_id}` : '/bngs'
      await notify.promise(apiRequest(path, { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(values) }, token), { loading: isEdit ? 'Saving BNG…' : 'Creating BNG…', success: isEdit ? 'BNG saved.' : 'BNG created.', error: isEdit ? 'Unable to save BNG.' : 'Unable to create BNG.' })
      setModal(null)
      await load()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save BNG.')) }
    finally { setSaving(false) }
  }

  const remove = async (row: BngRecord) => {
    try { await notify.promise(apiRequest(`/bngs/${row.public_id}`, { method: 'DELETE' }, token), { loading: 'Archiving BNG…', success: 'BNG archived.', error: 'Unable to archive BNG.' }); await load() }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to archive BNG.')) }
  }
  const removeSelected = async () => {
    if (selected.size < 2) return
    const confirmed = await confirm({ title: `Delete ${selected.size} BNGs?`, description: 'This archives the selected BNG records.', confirmLabel: 'Delete selected', destructive: true })
    if (!confirmed) return
    try { await notify.promise(Promise.all([...selected].map(id => apiRequest(`/bngs/${id}`, { method: 'DELETE' }, token))), { loading: 'Deleting selected BNGs…', success: 'Selected BNGs deleted.', error: 'Unable to delete selected BNGs.' }); clear(); await load() }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to delete selected BNGs.')) }
  }

  useEffect(() => { void load() }, [token])

  const filteredRows = useMemo(() => {
    const normalized = query.trim().toLowerCase()
    return rows.filter(row => {
      const matchesQuery = !normalized || [row.name, row.vendor, row.model, row.management_endpoint].some(value => value?.toLowerCase().includes(normalized))
      return matchesQuery && (vendor === 'all' || row.vendor === vendor) && (status === 'all' || row.status === status)
    })
  }, [query, rows, status, vendor])

  const values: Record<string, string> = modal?.row ? { name: modal.row.name, vendor: modal.row.vendor, model: modal.row.model || '', management_endpoint: modal.row.management_endpoint || '', preferred_transport: modal.row.preferred_transport || 'ssh', username: modal.row.ssh_username || '', password: '', status: modal.row.status || 'unknown', notes: modal.row.notes || '' } : { name: '', vendor: 'linux', model: '', management_endpoint: '', preferred_transport: 'ssh', username: '', password: '', status: 'unknown', notes: '' }

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4"><div><p className="billing-eyebrow">Network / inventory</p><h1 className="mt-2 text-lg font-semibold tracking-tight">BNGs</h1><p className="mt-1 max-w-2xl text-xs text-muted-foreground">Register broadband network gateways used for subscriber access and policy operations.</p></div>{selected.size > 1 && <Button type="button" variant="destructive" onClick={() => void removeSelected()}>Delete selected ({selected.size})</Button>}<Button type="button" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New BNG</Button></div>
    <Card className="billing-records overflow-hidden"><CardHeader className="gap-4 border-b border-border/60 pb-4"><div className="flex flex-wrap items-start justify-between gap-4"><div><CardTitle className="flex items-center gap-2">BNG inventory <span className="bg-muted px-2 py-1 text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle><CardDescription className="mt-2">Network gateways ready for access and subscriber operations.</CardDescription></div><div className="text-xs text-muted-foreground">{loading ? 'Loading…' : `Showing ${filteredRows.length} of ${rows.length}`}</div></div><div className="flex flex-wrap gap-2"><div className="relative min-w-[240px] flex-1"><MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search name, vendor, model, or endpoint" aria-label="Search BNGs" className="h-9 pl-9" /></div><select value={vendor} onChange={event => setVendor(event.target.value)} aria-label="Filter BNG vendor" className="box-border h-9 border border-input bg-background px-3 text-xs"><option value="all">All vendors</option><option value="linux">Linux</option><option value="mikrotik">MikroTik</option></select><select value={status} onChange={event => setStatus(event.target.value)} aria-label="Filter BNG status" className="box-border h-9 border border-input bg-background px-3 text-xs"><option value="all">All statuses</option><option value="unknown">Unknown</option><option value="active">Active</option><option value="inactive">Inactive</option></select>{(query || vendor !== 'all' || status !== 'all') && <Button type="button" variant="ghost" size="sm" onClick={() => { setQuery(''); setVendor('all'); setStatus('all') }}><XIcon data-icon="inline-start" />Clear</Button>}</div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><table className="w-full min-w-[980px] text-left text-xs"><thead><tr className="border-b text-muted-foreground"><th className="w-10 px-4 py-3 font-normal"><SelectAllCheckbox checked={allSelected} onChange={toggleAll} /></th><th className="px-4 py-3 font-normal">BNG</th><th className="px-4 py-3 font-normal">Vendor / model</th><th className="px-4 py-3 font-normal">Management endpoint</th><th className="px-4 py-3 font-normal">Transport</th><th className="px-4 py-3 font-normal">Status</th><th className="px-4 py-3 font-normal">Manage</th><th className="px-4 py-3 text-right font-normal">Actions</th></tr></thead><tbody>{filteredRows.length ? filteredRows.map(row => <tr className="border-b last:border-0" key={row.public_id}><td className="px-4 py-3"><RowCheckbox checked={selected.has(row.public_id)} onChange={() => toggle(row.public_id)} label="Select BNG" /></td><td className="px-4 py-3"><div className="font-medium">{row.name}</div><div className="text-[10px] text-muted-foreground">{row.public_id}</div></td><td className="px-4 py-3">{row.vendor}<div className="text-muted-foreground">{row.model || 'Model not set'}</div></td><td className="px-4 py-3">{row.management_endpoint || '—'}</td><td className="px-4 py-3 uppercase">{row.preferred_transport}</td><td className="px-4 py-3"><Badge variant="outline">{sessionStates[row.public_id] || row.status}</Badge></td><td className="px-4 py-3"><Button type="button" variant="outline" size="sm" onClick={() => onManage?.(row.public_id)}>Manage</Button></td><td className="px-4 py-3 text-right"><TableActions label={row.name} kind="operational" connected={sessionActive(sessionStates[row.public_id])} onConnect={() => void toggleConnection(row)} onView={() => setModal({ mode: 'edit', row })} onEdit={() => setModal({ mode: 'edit', row })} onDelete={() => void remove(row)} /></td></tr>) : <tr><td colSpan={8} className="h-52 text-center"><ShareNetworkIcon size={28} className="mx-auto text-muted-foreground/50" /><p className="mt-2 text-sm font-medium">No BNGs configured.</p><p className="mt-1 text-xs text-muted-foreground">Add a BNG to begin building your access network inventory.</p></td></tr>}</tbody></table></div></CardContent></Card>
    <CrudModal key={`${modal?.mode || 'closed'}-${modal?.row?.public_id || 'new'}`} open={modal !== null} mode={modal?.mode || 'create'} title={modal?.mode === 'edit' ? 'Edit BNG' : 'New BNG'} description="Configure this broadband network gateway." fields={modal?.mode === 'edit' ? fields.map(field => ['username', 'password'].includes(field.name) ? { ...field, required: false } : field) : fields} initialValues={values} loading={saving} onClose={() => setModal(null)} onSubmit={save} />
  </div>
}
