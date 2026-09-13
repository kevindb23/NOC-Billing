import { useCallback, useEffect, useState } from 'react'
import { ArrowsClockwiseIcon, PlugsConnectedIcon, PlusIcon, XIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiRequest, ApiRequestError } from '@/lib/api'
import { formatDate } from '@/lib/formatters'
import { getErrorMessage, notify } from '@/lib/notifications'
import { hasPermission } from '@/lib/usersRoles'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'

export type RouterCapabilityValue = boolean | 'supported' | 'unsupported' | 'not_supported' | 'unknown' | null
export type RouterCapabilities = string[] | Record<string, RouterCapabilityValue>

export type Router = {
  public_id: string
  name: string
  hostname?: string | null
  management_ip?: string | null
  vendor?: string | null
  model?: string | null
  software_version?: string | null
  serial_number?: string | null
  driver: string
  preferred_transport: string
  status: string
  last_contact_at?: string | null
  last_synchronized_at?: string | null
  capabilities?: RouterCapabilities | null
  notes?: string | null
}

export type RouterActionStatus = 'connected' | 'not_configured' | 'unsupported' | 'failed'
export type RouterActionResult = {
  status: RouterActionStatus
  driver?: string
  vendor?: string
  hostname?: string
  model?: string
  serial_number?: string
  software_version?: string
  uptime_seconds?: number
  checked_at?: string
  capabilities?: string[]
  details?: Record<string, unknown> | null
}

type RouterListResponse = { data: { data: Router[]; last_page?: number } }
type RouterResponse = { data: Router }
type RouterActionResponse = { data: RouterActionResult }
type Capability = { id: string; label: string; supported: boolean }

const fields: CrudField[] = [
  { name: 'name', label: 'Name', required: true },
  { name: 'vendor', label: 'Vendor', required: true, searchable: false, options: [['mikrotik', 'MikroTik'], ['juniper', 'Juniper'], ['cisco', 'Cisco'], ['linux_frr', 'Linux / FRR'], ['other', 'Other']].map(([value, label]) => ({ value, label })) },
  { name: 'hostname', label: 'Hostname' },
  { name: 'management_ip', label: 'Management IP' },
  { name: 'model', label: 'Model' },
  { name: 'software_version', label: 'Software version' },
  { name: 'serial_number', label: 'Serial number' },
  { name: 'driver', label: 'Driver', required: true, searchable: false, options: [['mikrotik_router', 'MikroTik router'], ['juniper_router', 'Juniper router'], ['cisco_router', 'Cisco router'], ['linux_frr_router', 'Linux / FRR router']].map(([value, label]) => ({ value, label })) },
  { name: 'preferred_transport', label: 'Preferred transport', required: true, searchable: false, options: ['api', 'ssh', 'netconf', 'snmp', 'mock'].map(value => ({ value, label: value.toUpperCase() })) },
  { name: 'status', label: 'Status', required: true, searchable: false, options: ['active', 'inactive', 'maintenance', 'unknown'].map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) })) },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const capabilityLabels: Record<string, string> = {
  connection_test: 'Connection Test',
  system_info: 'System Information',
}

const actionCopy: Record<RouterActionStatus, { label: string; message: string }> = {
  connected: { label: 'Connected', message: 'The router connection is available.' },
  not_configured: { label: 'Not Configured', message: 'No router transport is configured.' },
  unsupported: { label: 'Unsupported', message: 'This operation is not supported by the selected driver.' },
  failed: { label: 'Failed', message: 'The router connection test failed.' },
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isActionStatus(value: unknown): value is RouterActionStatus {
  return value === 'connected' || value === 'not_configured' || value === 'unsupported' || value === 'failed'
}

function stringValue(value: unknown): string | undefined {
  return typeof value === 'string' && value.trim() ? value : undefined
}

function actionResult(value: unknown): RouterActionResult | null {
  if (!isRecord(value) || !isActionStatus(value.status)) return null
  return {
    status: value.status,
    driver: stringValue(value.driver),
    vendor: stringValue(value.vendor),
    hostname: stringValue(value.hostname),
    model: stringValue(value.model),
    serial_number: stringValue(value.serial_number),
    software_version: stringValue(value.software_version),
    uptime_seconds: typeof value.uptime_seconds === 'number' && Number.isFinite(value.uptime_seconds) ? value.uptime_seconds : undefined,
    checked_at: stringValue(value.checked_at),
    capabilities: Array.isArray(value.capabilities) ? value.capabilities.filter((item): item is string => typeof item === 'string') : undefined,
    details: isRecord(value.details) ? value.details : null,
  }
}

function actionError(error: unknown): RouterActionResult {
  const body = isRecord(error) && 'body' in error ? error.body : error instanceof ApiRequestError ? error.body : undefined
  const bodyRecord = isRecord(body) ? body : null
  return actionResult(bodyRecord?.data) || { status: 'failed' }
}

function values(row?: Router): Record<string, string> {
  if (!row) return { preferred_transport: 'mock', status: 'unknown', driver: 'mikrotik_router', vendor: 'mikrotik' }
  return Object.fromEntries(fields.map(field => [field.name, String(row[field.name as keyof Router] ?? '')]))
}

function label(value: string | undefined) {
  return value ? value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : '—'
}

function capabilityLabel(value: string) {
  return capabilityLabels[value] || label(value)
}

function endpoint(row: Router) {
  return row.management_ip || row.hostname || 'Not configured'
}

function capabilities(value: RouterCapabilities | null | undefined): Capability[] {
  if (Array.isArray(value)) {
    return [...new Set(value.filter(item => typeof item === 'string'))].map(id => ({ id, label: capabilityLabel(id), supported: true }))
  }
  if (!isRecord(value)) return []
  return Object.entries(value).map(([id, state]) => ({ id, label: capabilityLabel(id), supported: state === true || state === 'supported' }))
}

function actionBadge(status: RouterActionStatus) {
  const tone = status === 'connected' ? 'default' : status === 'failed' ? 'destructive' : 'secondary'
  const className = status === 'connected' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : status === 'not_configured' ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300' : status === 'unsupported' ? 'text-muted-foreground' : undefined
  return <Badge variant={tone} className={className}>{actionCopy[status].label}</Badge>
}

function statusBadge(status: string) {
  if (isActionStatus(status)) return actionBadge(status)
  return <Badge variant={status === 'active' ? 'default' : 'secondary'}>{label(status)}</Badge>
}

function capabilityBadge(supported: boolean) {
  return <Badge variant={supported ? 'default' : 'secondary'} className={supported ? undefined : 'text-muted-foreground'}>{supported ? 'Supported' : 'Unsupported'}</Badge>
}

function actionFields(result: RouterActionResult) {
  const fields: Array<[string, string | undefined]> = [
    ['Vendor', result.vendor],
    ['Hostname', result.hostname],
    ['Model', result.model],
    ['Serial number', result.serial_number],
    ['Software version', result.software_version],
    ['Uptime', result.uptime_seconds === undefined ? undefined : `${result.uptime_seconds} seconds`],
  ]
  return fields.filter((field): field is [string, string] => typeof field[1] === 'string' && field[1].length > 0)
}

export function RoutersPage({ token, permissions, isSuperadmin }: { token: string; permissions?: string[]; isSuperadmin?: boolean }) {
  const can = (permission: string) => isSuperadmin === true || hasPermission(permissions, permission)
  const [rows, setRows] = useState<Router[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [pageAlert, setPageAlert] = useState<{ title: string; message: string } | null>(null)
  const [modal, setModal] = useState<'create' | 'edit' | 'view' | null>(null)
  const [selected, setSelected] = useState<Router | undefined>()
  const [modalError, setModalError] = useState('')
  const [viewError, setViewError] = useState('')
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [result, setResult] = useState<RouterActionResult | null>(null)
  const [systemInfo, setSystemInfo] = useState<RouterActionResult | null>(null)
  const [systemInfoLoading, setSystemInfoLoading] = useState(false)
  const [systemInfoError, setSystemInfoError] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setPageAlert(null)
    try {
      const response = await apiRequest<RouterListResponse>(`/routers?per_page=20&page=${page}`, {}, token)
      setRows(response.data.data)
      setLastPage(response.data.last_page || 1)
    } catch (exception) {
      setPageAlert({ title: 'Could not load routers', message: getErrorMessage(exception, 'Unable to load routers.') })
    } finally {
      setLoading(false)
    }
  }, [page, token])

  useEffect(() => { void load() }, [load])

  const close = () => {
    setModal(null)
    setSelected(undefined)
    setModalError('')
    setViewError('')
    setResult(null)
    setSystemInfo(null)
    setSystemInfoError('')
  }

  const save = async (form: Record<string, string>) => {
    const isCreate = modal === 'create'
    setSaving(true)
    setModalError('')
    try {
      await notify.promise(apiRequest(isCreate ? '/routers' : `/routers/${selected?.public_id}`, { method: isCreate ? 'POST' : 'PUT', body: JSON.stringify(form) }, token), { loading: isCreate ? 'Creating router…' : 'Saving router…', success: isCreate ? 'Router created.' : 'Router updated.', error: 'Unable to save router.' })
      close()
      await load()
    } catch (exception) {
      setModalError(getErrorMessage(exception, 'Unable to save router.'))
    } finally {
      setSaving(false)
    }
  }

  const archive = async (row: Router) => {
    try {
      await notify.promise(apiRequest(`/routers/${row.public_id}`, { method: 'DELETE' }, token), { loading: 'Archiving router…', success: 'Router archived.', error: 'Unable to archive router.' })
      await load()
    } catch (exception) {
      setPageAlert({ title: 'Unable to archive router', message: getErrorMessage(exception, 'Unable to archive router.') })
    }
  }

  const test = async (row: Router) => {
    setSelected(row)
    setModal('view')
    setViewError('')
    setTesting(true)
    setResult(null)
    try {
      const response = await apiRequest<RouterActionResponse>(`/routers/${row.public_id}/connection-test`, { method: 'POST' }, token)
      setResult(actionResult(response.data) || { status: 'failed' })
    } catch (exception) {
      setResult(actionError(exception))
    } finally {
      setTesting(false)
    }
  }

  const loadSystemInfo = async (row: Router) => {
    setSystemInfoLoading(true)
    setSystemInfoError('')
    setSystemInfo(null)
    try {
      const response = await apiRequest<RouterActionResponse>(`/routers/${row.public_id}/system-info`, {}, token)
      setSystemInfo(actionResult(response.data) || { status: 'failed' })
    } catch (exception) {
      setSystemInfo(actionError(exception))
    } finally {
      setSystemInfoLoading(false)
    }
  }

  const view = async (row: Router) => {
    setSelected(row)
    setModal('view')
    setViewError('')
    setResult(null)
    setSystemInfo(null)
    setSystemInfoError('')
    try {
      const response = await apiRequest<RouterResponse>(`/routers/${row.public_id}`, {}, token)
      setSelected(response.data)
    } catch (exception) {
      setViewError(getErrorMessage(exception, 'Unable to view router.'))
    }
  }

  const viewFields: [string, string | null | undefined][] = selected ? [
    ['Vendor', label(selected.vendor || undefined)], ['Driver', selected.driver], ['Hostname', selected.hostname], ['Management IP', selected.management_ip], ['Model', selected.model], ['Software version', selected.software_version], ['Serial number', selected.serial_number], ['Preferred transport', label(selected.preferred_transport)], ['Status', label(selected.status)], ['Last contact', selected.last_contact_at ? formatDate(selected.last_contact_at) : 'Never contacted'], ['Last synchronized', selected.last_synchronized_at ? formatDate(selected.last_synchronized_at) : 'Never synchronized'],
  ] : []

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex items-center justify-between gap-4"><div><p className="billing-modal-eyebrow">ISP BILLING / NETWORK</p><h1 className="text-sm font-semibold">Routers</h1><p className="mt-1 text-xs text-muted-foreground">Manage vendor-neutral router inventory and connection capabilities.</p></div>{can('routers.create') && <Button onClick={() => { setSelected(undefined); setModalError(''); setModal('create') }}><PlusIcon data-icon="inline-start" />New router</Button>}</div>
    {pageAlert && <Alert variant="destructive"><AlertTitle>{pageAlert.title}</AlertTitle><AlertDescription>{pageAlert.message}</AlertDescription></Alert>}
    <div className="billing-records overflow-x-auto rounded-lg border bg-card"><Table className="min-w-[1080px]"><TableHeader><TableRow>{['Router', 'Vendor / model', 'Management endpoint', 'Driver / transport', 'Status', 'Last contact', 'Actions'].map(column => <TableHead key={column}>{column}</TableHead>)}</TableRow></TableHeader><TableBody>{loading ? <TableRow><TableCell colSpan={7} className="h-48 text-center">Loading routers…</TableCell></TableRow> : rows.length === 0 ? <TableRow><TableCell colSpan={7} className="h-48 text-center">No routers found.</TableCell></TableRow> : rows.map(row => <TableRow key={row.public_id}><TableCell><p className="font-medium">{row.name}</p><p className="font-mono text-[10px] text-muted-foreground">{row.hostname || row.management_ip || '—'}</p></TableCell><TableCell><p>{label(row.vendor || undefined)}</p><p className="text-xs text-muted-foreground">{row.model || '—'}</p></TableCell><TableCell>{endpoint(row)}</TableCell><TableCell><p className="font-mono text-xs">{row.driver}</p><p className="text-xs text-muted-foreground">{label(row.preferred_transport)}</p></TableCell><TableCell>{statusBadge(row.status)}</TableCell><TableCell>{row.last_contact_at ? formatDate(row.last_contact_at) : 'Never contacted'}</TableCell><TableCell><div className="inline-flex items-center justify-end gap-1"><TableActions label={row.name} kind="operational" onView={() => void view(row)} onEdit={can('routers.update') ? () => { setSelected(row); setModalError(''); setModal('edit') } : undefined} onArchive={can('routers.delete') ? () => void archive(row) : undefined} />{can('routers.test') && <Button type="button" variant="ghost" size="icon-sm" aria-label={`Test connection for ${row.name}`} title="Test connection" onClick={() => void test(row)} disabled={testing}><PlugsConnectedIcon /></Button>}</div></TableCell></TableRow>)}</TableBody></Table></div>
    {lastPage > 1 && <div className="flex items-center justify-end gap-2"><Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(value => value - 1)}>Previous</Button><span className="text-xs text-muted-foreground">Page {page} of {lastPage}</span><Button variant="outline" size="sm" disabled={page === lastPage} onClick={() => setPage(value => value + 1)}>Next</Button></div>}
    <CrudModal key={modal ? `${modal}-${selected?.public_id || 'new'}` : 'closed'} open={modal === 'create' || modal === 'edit'} mode={modal === 'create' ? 'create' : 'edit'} title={modal === 'create' ? 'New router' : `Edit ${selected?.name || 'router'}`} description="Manage router inventory without storing credentials." fields={fields} initialValues={values(selected)} error={modalError} loading={saving} onClose={close} onSubmit={save} wide />
    {modal === 'view' && selected && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="router-view-title"><Card className="billing-modal-card relative w-full max-w-3xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">RECORD DETAILS</p><CardTitle id="router-view-title">{selected.name}</CardTitle><CardDescription>Review router inventory and driver capabilities.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={close} aria-label="Close modal"><XIcon /></Button></CardHeader><CardContent className="space-y-5 pt-5">{viewError && <Alert variant="destructive"><AlertTitle>Unable to view router</AlertTitle><AlertDescription>{viewError}</AlertDescription></Alert>}<dl className="grid gap-4 sm:grid-cols-2">{viewFields.map(([key, value]) => <div key={key}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{key}</dt><dd className="mt-1 text-sm">{value || '—'}</dd></div>)}</dl><section className="rounded-md border"><div className="border-b px-4 py-3"><h3 className="text-sm font-semibold">Capabilities</h3></div>{capabilities(selected.capabilities).length ? capabilities(selected.capabilities).map(capability => <div className="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-0" key={capability.id}><span className="text-sm">{capability.label}</span>{capabilityBadge(capability.supported)}</div>) : <p className="px-4 py-4 text-sm text-muted-foreground">No capabilities reported.</p>}</section><div className="rounded-md border bg-muted/30 p-4"><div className="flex items-center justify-between gap-3"><div><p className="text-sm font-semibold">Connection test</p><p className="mt-1 text-xs text-muted-foreground">{result ? actionCopy[result.status].message : 'Verify whether the configured driver can reach this router.'}</p></div>{can('routers.test') && <Button type="button" size="sm" onClick={() => void test(selected)} disabled={testing}>{testing && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{testing ? 'Testing…' : 'Test connection'}</Button>}</div>{result && <div className="mt-3 flex items-center gap-3 text-xs">{actionBadge(result.status)}{result.checked_at && <span>Tested at {formatDate(result.checked_at)}</span>}</div>}</div>{can('routers.test') && <section className="rounded-md border bg-muted/30 p-4"><div className="flex items-center justify-between gap-3"><div><h3 className="text-sm font-semibold">System information</h3><p className="mt-1 text-xs text-muted-foreground">Read normalized device information without exposing router credentials.</p></div><Button type="button" size="sm" onClick={() => void loadSystemInfo(selected)} disabled={systemInfoLoading}>{systemInfoLoading && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{systemInfoLoading ? 'Loading…' : 'Load system information'}</Button></div>{systemInfoError && <Alert variant="destructive" className="mt-3"><AlertTitle>Unable to load system information</AlertTitle><AlertDescription>{systemInfoError}</AlertDescription></Alert>}{systemInfo && <div className="mt-3 space-y-3"><div className="flex items-center gap-3 text-xs">{actionBadge(systemInfo.status)}{systemInfo.checked_at && <span>Checked at {formatDate(systemInfo.checked_at)}</span>}</div>{systemInfo.status === 'connected' && <dl className="grid gap-3 sm:grid-cols-2">{actionFields(systemInfo).map(([key, value]) => <div key={key}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{key}</dt><dd className="mt-1 text-sm">{value}</dd></div>)}</dl>}<p className="text-xs text-muted-foreground">{actionCopy[systemInfo.status].message}</p></div>}</section>}</CardContent></Card></div>}
  </div>
}
