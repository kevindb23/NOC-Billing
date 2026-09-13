import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ArrowsClockwiseIcon, CaretDownIcon, PlugsConnectedIcon, PlusIcon, XIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiRequest, ApiRequestError } from '@/lib/api'
import { formatDate } from '@/lib/formatters'
import { getErrorMessage, notify } from '@/lib/notifications'
import { hasPermission } from '@/lib/usersRoles'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { RouterCredentialFields, type RouterCredentialValues, type RouterTransport } from './RouterCredentialFields'
import { RouterOperationPanel, type RouterOperation } from './RouterOperationPanel'
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
  credential_configured?: boolean
  credential_profile?: {
    public_id?: string
    name?: string | null
    auth_type?: string | null
    is_primary?: boolean
    version?: number | null
    connection_metadata?: Record<string, unknown> | null
  } | null
  credential_version?: number | null
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
type RouterFormValues = Record<string, string> & { preferred_transport: RouterTransport }

const transports: Array<{ value: RouterTransport; label: string }> = [{ value: 'api', label: 'API' }, { value: 'ssh', label: 'SSH' }, { value: 'netconf', label: 'NETCONF' }, { value: 'snmp', label: 'SNMP' }]
const vendors = [['mikrotik', 'MikroTik'], ['juniper', 'Juniper'], ['cisco', 'Cisco'], ['linux_frr', 'Linux / FRR'], ['other', 'Other']].map(([value, label]) => ({ value, label }))
const drivers = [['mikrotik_router', 'MikroTik router'], ['juniper_router', 'Juniper router'], ['cisco_router', 'Cisco router'], ['linux_frr_router', 'Linux / FRR router']].map(([value, label]) => ({ value, label }))
const statuses = ['active', 'inactive', 'maintenance', 'unknown'].map(value => ({ value, label: value[0].toUpperCase() + value.slice(1) }))

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

const systemInfoCopy: Record<RouterActionStatus, string> = {
  connected: 'System information loaded.',
  not_configured: 'No router transport is configured.',
  unsupported: 'This operation is not supported by the selected driver.',
  failed: 'The system information request failed.',
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

function routerFormValues(row?: Router): RouterFormValues {
  return {
    name: row?.name || '',
    vendor: row?.vendor || 'mikrotik',
    hostname: row?.hostname || '',
    management_ip: row?.management_ip || '',
    model: row?.model || '',
    software_version: row?.software_version || '',
    serial_number: row?.serial_number || '',
    driver: row?.driver || 'mikrotik_router',
    preferred_transport: (row?.preferred_transport === 'ssh' || row?.preferred_transport === 'netconf' || row?.preferred_transport === 'snmp' ? row.preferred_transport : 'api'),
    status: row?.status || 'unknown',
    notes: row?.notes || '',
  }
}

function selectLabel(value: string) {
  return value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

function FormSelect({ id, label: fieldLabel, value, options, onChange, required, error }: {
  id: string
  label: string
  value: string
  options: Array<{ value: string; label: string }>
  onChange: (value: string) => void
  required?: boolean
  error?: string
}) {
  return <Field>
    <FieldLabel htmlFor={id}>{fieldLabel}{required && <span className="text-destructive"> *</span>}</FieldLabel>
    <div className="relative">
      <select id={id} className="h-9 w-full appearance-none border border-input bg-background/60 px-2.5 pr-9 text-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={value} onChange={event => onChange(event.target.value)} required={required} aria-invalid={error ? true : undefined}>
        {options.map(option => <option value={option.value} key={option.value}>{option.label}</option>)}
      </select>
      <CaretDownIcon aria-hidden="true" className="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
    </div>
    {error && <p className="text-[11px] text-destructive">{error}</p>}
  </Field>
}

function FormInput({ id, label: fieldLabel, value, onChange, type = 'text', required, error }: {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  type?: string
  required?: boolean
  error?: string
}) {
  return <Field>
    <FieldLabel htmlFor={id}>{fieldLabel}{required && <span className="text-destructive"> *</span>}</FieldLabel>
    <Input id={id} className="h-9 bg-background/60" type={type} value={value} onChange={event => onChange(event.target.value)} required={required} aria-invalid={error ? true : undefined} />
    {error && <p className="text-[11px] text-destructive">{error}</p>}
  </Field>
}

function RouterFormModal({ open, mode, row, error, fieldErrors, loading, onClose, onSubmit }: {
  open: boolean
  mode: 'create' | 'edit'
  row?: Router
  error?: string
  fieldErrors?: Record<string, string>
  loading?: boolean
  onClose: () => void
  onSubmit: (values: RouterFormValues, credentials: RouterCredentialValues) => Promise<void>
}) {
  const [values, setValues] = useState<RouterFormValues>(() => routerFormValues(row))
  const [credentials, setCredentials] = useState<RouterCredentialValues>(() => {
    const metadata = row?.credential_profile?.connection_metadata || {}
    return Object.fromEntries(Object.entries(metadata).map(([key, value]) => [key, String(value)]))
  })
  const update = (name: string, value: string) => setValues(current => ({ ...current, [name]: value }))
  const updateCredential = (name: string, value: string) => setCredentials(current => ({ ...current, [name]: value }))
  const changeTransport = (transport: string) => {
    if (!['api', 'ssh', 'netconf', 'snmp'].includes(transport)) return
    setValues(current => ({ ...current, preferred_transport: transport as RouterTransport }))
    setCredentials({})
  }
  const submit = async (event: FormEvent) => { event.preventDefault(); await onSubmit(values, credentials) }
  if (!open) return null
  const transport = values.preferred_transport

  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="router-form-title">
    <Card className="billing-modal-card relative w-full max-w-5xl shadow-2xl">
      <CardHeader className="billing-modal-header border-b pr-14"><p className="billing-modal-eyebrow">{mode === 'create' ? 'CREATE RECORD' : 'UPDATE RECORD'}</p><CardTitle id="router-form-title">{mode === 'create' ? 'New router' : `Edit ${row?.name || 'router'}`}</CardTitle><CardDescription>Configure inventory and the secure transport profile for this device.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close router form"><XIcon /></Button></CardHeader>
      <CardContent className="billing-modal-content max-h-[calc(100vh-10rem)] overflow-y-auto pt-5"><form onSubmit={submit}>
        <FieldGroup className="grid gap-4 sm:grid-cols-2">
          <FormInput id="router-name" label="Name" value={values.name} onChange={value => update('name', value)} required error={fieldErrors?.name} />
          <FormSelect id="router-vendor" label="Vendor" value={values.vendor} options={vendors} onChange={value => update('vendor', value)} required error={fieldErrors?.vendor} />
          <FormInput id="router-hostname" label="Hostname" value={values.hostname} onChange={value => update('hostname', value)} error={fieldErrors?.hostname} />
          <FormInput id="router-management-ip" label="Management IP" value={values.management_ip} onChange={value => update('management_ip', value)} error={fieldErrors?.management_ip} />
          <FormInput id="router-model" label="Model" value={values.model} onChange={value => update('model', value)} error={fieldErrors?.model} />
          <FormInput id="router-software-version" label="Software version" value={values.software_version} onChange={value => update('software_version', value)} error={fieldErrors?.software_version} />
          <FormInput id="router-serial-number" label="Serial number" value={values.serial_number} onChange={value => update('serial_number', value)} error={fieldErrors?.serial_number} />
          <FormSelect id="router-driver" label="Driver" value={values.driver} options={drivers} onChange={value => update('driver', value)} required error={fieldErrors?.driver} />
          <FormSelect id="router-transport" label="Preferred transport" value={transport} options={transports} onChange={changeTransport} required error={fieldErrors?.preferred_transport} />
          <FormSelect id="router-status" label="Status" value={values.status} options={statuses} onChange={value => update('status', value)} required error={fieldErrors?.status} />
          <Field className="sm:col-span-2"><FieldLabel htmlFor="router-notes">Notes</FieldLabel><textarea id="router-notes" className="min-h-20 w-full border border-input bg-background/60 px-2.5 py-2 text-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values.notes} onChange={event => update('notes', event.target.value)} aria-invalid={fieldErrors?.notes ? true : undefined} />{fieldErrors?.notes && <p className="text-[11px] text-destructive">{fieldErrors.notes}</p>}</Field>
          <RouterCredentialFields transport={transport} values={credentials} configured={row?.credential_configured} metadata={row?.credential_profile} errors={Object.fromEntries(Object.entries(fieldErrors || {}).map(([key, value]) => [key.replace(/^credential_profile\./, ''), value]))} onChange={updateCredential} />
        </FieldGroup>
        {error && <Alert variant="destructive" className="mt-4"><AlertTitle>Unable to save router</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
        <div className="billing-modal-footer mt-6 flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={loading}>{loading ? 'Saving…' : mode === 'create' ? 'Create' : 'Save changes'}</Button></div>
      </form></CardContent>
    </Card>
  </div>
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
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [viewError, setViewError] = useState('')
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [result, setResult] = useState<RouterActionResult | null>(null)
  const [systemInfo, setSystemInfo] = useState<RouterActionResult | null>(null)
  const [systemInfoLoading, setSystemInfoLoading] = useState(false)
  const [systemInfoError, setSystemInfoError] = useState('')
  const [operationLoading, setOperationLoading] = useState<string | null>(null)
  const [lastOperation, setLastOperation] = useState<RouterOperation | null>(null)

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
    setFieldErrors({})
    setViewError('')
    setResult(null)
    setSystemInfo(null)
    setSystemInfoError('')
    setOperationLoading(null)
    setLastOperation(null)
  }

  const save = async (form: RouterFormValues, credentials: RouterCredentialValues) => {
    const isCreate = modal === 'create'
    setSaving(true)
    setModalError('')
    setFieldErrors({})
    const credentialProfile = Object.fromEntries(Object.entries(credentials).filter(([, value]) => value.trim() !== ''))
    const body = { ...form, ...(Object.keys(credentialProfile).length > 0 ? { credential_profile: credentialProfile } : {}) }
    try {
      await notify.promise(apiRequest(isCreate ? '/routers' : `/routers/${selected?.public_id}`, { method: isCreate ? 'POST' : 'PUT', body: JSON.stringify(body) }, token), { loading: isCreate ? 'Creating router…' : 'Saving router…', success: isCreate ? 'Router created.' : 'Router updated.', error: 'Unable to save router.' })
      close()
      await load()
    } catch (exception) {
      const exceptionBody = exception instanceof ApiRequestError && isRecord(exception.body) ? exception.body : null
      const errors = exceptionBody && isRecord(exceptionBody.errors) ? Object.fromEntries(Object.entries(exceptionBody.errors).map(([key, value]) => [key, Array.isArray(value) && typeof value[0] === 'string' ? value[0] : String(value)])) : {}
      setFieldErrors(errors)
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
      notify.error('Unable to test router connection.')
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
      const normalized = actionError(exception)
      setSystemInfo(normalized)
      setSystemInfoError(systemInfoCopy[normalized.status])
    } finally {
      setSystemInfoLoading(false)
    }
  }

  const runOperation = async (row: Router, operation: string) => {
    setOperationLoading(operation)
    setLastOperation(null)
    try {
      const response = await notify.promise(apiRequest<{ data: RouterOperation }>(`/routers/${row.public_id}/operations`, { method: 'POST', body: JSON.stringify({ operation, parameters: {} }) }, token), { loading: `Starting ${selectLabel(operation)}…`, success: `${selectLabel(operation)} queued.`, error: `Unable to start ${selectLabel(operation)}.` })
      setLastOperation(response.data)
    } catch (exception) {
      const body = exception instanceof ApiRequestError && isRecord(exception.body) ? exception.body : null
      const data = body && isRecord(body.data) ? body.data : {}
      setLastOperation({ operation, status: 'failed', correlation_id: typeof body?.correlation_id === 'string' ? body.correlation_id : undefined, error_message: typeof body?.message === 'string' ? body.message : typeof data.error_message === 'string' ? data.error_message : 'The operation could not be started.' })
    } finally {
      setOperationLoading(null)
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
    <RouterFormModal key={modal ? `${modal}-${selected?.public_id || 'new'}` : 'closed'} open={modal === 'create' || modal === 'edit'} mode={modal === 'create' ? 'create' : 'edit'} row={selected} error={modalError} fieldErrors={fieldErrors} loading={saving} onClose={close} onSubmit={save} />
    {modal === 'view' && selected && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="router-view-title"><Card className="billing-modal-card relative w-full max-w-3xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">RECORD DETAILS</p><CardTitle id="router-view-title">{selected.name}</CardTitle><CardDescription>Review router inventory and driver capabilities.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={close} aria-label="Close modal"><XIcon /></Button></CardHeader><CardContent className="space-y-5 pt-5">{viewError && <Alert variant="destructive"><AlertTitle>Unable to view router</AlertTitle><AlertDescription>{viewError}</AlertDescription></Alert>}<dl className="grid gap-4 sm:grid-cols-2">{viewFields.map(([key, value]) => <div key={key}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{key}</dt><dd className="mt-1 text-sm">{value || '—'}</dd></div>)}</dl><section className="rounded-md border"><div className="border-b px-4 py-3"><h3 className="text-sm font-semibold">Capabilities</h3></div>{capabilities(selected.capabilities).length ? capabilities(selected.capabilities).map(capability => <div className="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-0" key={capability.id}><span className="text-sm">{capability.label}</span>{capabilityBadge(capability.supported)}</div>) : <p className="px-4 py-4 text-sm text-muted-foreground">No capabilities reported.</p>}</section><RouterOperationPanel router={selected} capabilities={capabilities(selected.capabilities).filter(capability => capability.supported).map(capability => capability.id)} permissions={permissions} isSuperadmin={isSuperadmin} lastOperation={lastOperation} loadingOperation={operationLoading} onOperation={operation => void runOperation(selected, operation)} /><div className="rounded-md border bg-muted/30 p-4"><div className="flex items-center justify-between gap-3"><div><p className="text-sm font-semibold">Connection test</p><p className="mt-1 text-xs text-muted-foreground">{result ? actionCopy[result.status].message : 'Verify whether the configured driver can reach this router.'}</p></div>{can('routers.test') && <Button type="button" size="sm" onClick={() => void test(selected)} disabled={testing}>{testing && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{testing ? 'Testing…' : 'Test connection'}</Button>}</div>{result && <div className="mt-3 flex items-center gap-3 text-xs">{actionBadge(result.status)}{result.checked_at && <span>Tested at {formatDate(result.checked_at)}</span>}</div>}</div>{can('routers.test') && <section className="rounded-md border bg-muted/30 p-4"><div className="flex items-center justify-between gap-3"><div><h3 className="text-sm font-semibold">System information</h3><p className="mt-1 text-xs text-muted-foreground">Read normalized device information without exposing router credentials.</p></div><Button type="button" size="sm" onClick={() => void loadSystemInfo(selected)} disabled={systemInfoLoading}>{systemInfoLoading && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{systemInfoLoading ? 'Loading…' : 'Load system information'}</Button></div>{systemInfoError && <Alert variant="destructive" className="mt-3"><AlertTitle>Unable to load system information</AlertTitle><AlertDescription>{systemInfoError}</AlertDescription></Alert>}{systemInfo && <div className="mt-3 space-y-3"><div className="flex items-center gap-3 text-xs">{actionBadge(systemInfo.status)}{systemInfo.checked_at && <span>Checked at {formatDate(systemInfo.checked_at)}</span>}</div>{systemInfo.status === 'connected' && <dl className="grid gap-3 sm:grid-cols-2">{actionFields(systemInfo).map(([key, value]) => <div key={key}><dt className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">{key}</dt><dd className="mt-1 text-sm">{value}</dd></div>)}</dl>}{!systemInfoError && <p className="text-xs text-muted-foreground">{systemInfoCopy[systemInfo.status]}</p>}</div>}</section>}</CardContent></Card></div>}
  </div>
}
