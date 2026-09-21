import { useEffect, useMemo, useState } from 'react'
import { CircleNotchIcon, GearIcon, MagnifyingGlassIcon, PencilSimpleIcon, PlusIcon, TrashIcon, WrenchIcon, XIcon } from '@phosphor-icons/react'
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

type OltOption = { public_id: string; name: string; vendor: string; status?: string }
type OntRecord = { id: number; public_id: string; olt_id: number; frame: number; slot: number; pon_port: number; ont_id?: number | null; serial_number: string; name: string; status: 'unknown' | 'discovered' | 'rogue' | 'online' | 'offline'; notes?: string | null; last_discovered_at?: string | null; olt?: OltOption }
type OnuInventoryRecord = { public_id: string; serial_number?: string | null; vendor: string; model: string; status: string }
type OntForm = { olt_public_id: string; frame: string; slot: string; pon_port: string; ont_id: string; serial_number: string; name: string; status: OntRecord['status']; notes: string }
type Props = { token: string; permissions?: string[]; isSuperadmin?: boolean; onManage?: (publicId: string) => void }

const emptyForm = (oltPublicId = ''): OntForm => ({ olt_public_id: oltPublicId, frame: '0', slot: '', pon_port: '', ont_id: '', serial_number: '', name: '', status: 'unknown', notes: '' })

const statusVariant = (status: OntRecord['status']) => status === 'offline' ? 'secondary' : status === 'rogue' ? 'destructive' : 'outline'

export function OntPage({ token, permissions, isSuperadmin, onManage }: Props) {
  const [rows, setRows] = useState<OntRecord[]>([])
  const [olts, setOlts] = useState<OltOption[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [discovering, setDiscovering] = useState(false)
  const [error, setError] = useState('')
  const [oltError, setOltError] = useState('')
  const [settingsError, setSettingsError] = useState('')
  const [doNotAllowRogueOnus, setDoNotAllowRogueOnus] = useState(false)
  const [ontIdCapacityPerPort, setOntIdCapacityPerPort] = useState('64')
  const [onuInventory, setOnuInventory] = useState<OnuInventoryRecord[]>([])
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [settingsSaving, setSettingsSaving] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<OntRecord | null>(null)
  const [form, setForm] = useState<OntForm>(emptyForm())
  const [discoverOpen, setDiscoverOpen] = useState(false)
  const [discoverOlt, setDiscoverOlt] = useState('')
  const [discoverResult, setDiscoverResult] = useState<{ discovered: number; updated: number; skipped: number; message?: string | null } | null>(null)
  const confirm = useConfirm()

  const canCreate = isSuperadmin !== false || hasPermission(permissions, 'onts.create')
  const canUpdate = isSuperadmin !== false || hasPermission(permissions, 'onts.update')
  const canDelete = isSuperadmin !== false || hasPermission(permissions, 'onts.delete')
  const canDiscover = isSuperadmin !== false || hasPermission(permissions, 'onts.discover')

  const load = async () => {
    setLoading(true)
    setError('')
    setOltError('')
    setSettingsError('')
    const [ontResult, oltResult, settingsResult, onuResult] = await Promise.allSettled([
      apiRequest<{ data: { data: OntRecord[] } }>('/onts?per_page=100', {}, token),
      apiRequest<{ data: { data: OltOption[] } }>('/olts?per_page=100', {}, token),
      apiRequest<{ data: { do_not_allow_rogue_onus: boolean; ont_id_capacity_per_port?: number } }>('/onts/settings', {}, token),
      apiRequest<{ data: { data: OnuInventoryRecord[] } }>('/onus?per_page=100', {}, token),
    ])
    if (ontResult.status === 'fulfilled') setRows(ontResult.value.data.data || [])
    else setError(getErrorMessage(ontResult.reason, 'Unable to load ONT inventory.'))
    if (oltResult.status === 'fulfilled') setOlts(oltResult.value.data.data || [])
    else setOltError(getErrorMessage(oltResult.reason, 'Unable to load the OLT list.'))
    if (settingsResult.status === 'fulfilled') { setDoNotAllowRogueOnus(Boolean(settingsResult.value.data.do_not_allow_rogue_onus)); setOntIdCapacityPerPort(String(settingsResult.value.data.ont_id_capacity_per_port ?? 64)) }
    else setSettingsError(getErrorMessage(settingsResult.reason, 'Unable to load ONT settings.'))
    if (onuResult.status === 'fulfilled') setOnuInventory(onuResult.value.data.data || [])
    setLoading(false)
  }

  useEffect(() => { void load() }, [token])

  const openSettings = () => setSettingsOpen(true)

  const saveSettings = async () => {
    setSettingsSaving(true)
    try {
      const capacity = Number(ontIdCapacityPerPort)
      if (!Number.isInteger(capacity) || capacity < 1 || capacity > 256) { notify.error('Max ONTs per PON / port must be between 1 and 256.'); return }
      const response = await apiRequest<{ data: { do_not_allow_rogue_onus: boolean; ont_id_capacity_per_port: number } }>('/onts/settings', { method: 'PATCH', body: JSON.stringify({ do_not_allow_rogue_onus: doNotAllowRogueOnus, ont_id_capacity_per_port: capacity }) }, token)
      setDoNotAllowRogueOnus(Boolean(response.data.do_not_allow_rogue_onus))
      setOntIdCapacityPerPort(String(response.data.ont_id_capacity_per_port))
      notify.success('ONT settings saved.')
      setSettingsOpen(false)
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to save ONT settings.'))
    } finally {
      setSettingsSaving(false)
    }
  }

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm(olts.length === 1 ? olts[0].public_id : ''))
    setFormOpen(true)
  }

  const openEdit = (row: OntRecord) => {
    setEditing(row)
    setForm({
      olt_public_id: row.olt?.public_id || olts.find(olt => olt.name === row.olt?.name)?.public_id || '',
      frame: String(row.frame),
      slot: String(row.slot),
      pon_port: String(row.pon_port),
      ont_id: row.ont_id === null || row.ont_id === undefined ? '' : String(row.ont_id),
      serial_number: row.serial_number,
      name: row.name,
      status: row.status,
      notes: row.notes || '',
    })
    setFormOpen(true)
  }

  const save = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    try {
      const payload = {
        ...form,
        frame: Number(form.frame),
        slot: Number(form.slot),
        pon_port: Number(form.pon_port),
        ont_id: form.ont_id ? Number(form.ont_id) : null,
      }
      const path = editing ? `/onts/${editing.public_id}` : '/onts'
      await apiRequest(path, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) }, token)
      notify.success(editing ? 'ONT updated.' : 'ONT added.')
      setFormOpen(false)
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, editing ? 'Unable to update ONT.' : 'Unable to add ONT.'))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (row: OntRecord) => {
    const accepted = await confirm({ title: `Delete ${row.name}?`, description: 'This permanently removes the ONT inventory record.', confirmLabel: 'Delete permanently', destructive: true })
    if (!accepted) return
    setDeletingId(row.id)
    try {
      await apiRequest(`/onts/${row.public_id}`, { method: 'DELETE' }, token)
      notify.success('ONT deleted.')
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to delete ONT.'))
    } finally {
      setDeletingId(null)
    }
  }

  const openDiscover = () => {
    setDiscoverResult(null)
    setDiscoverOlt(olts.length === 1 ? olts[0].public_id : '')
    setDiscoverOpen(true)
  }

  const discover = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!discoverOlt) return
    setDiscovering(true)
    setDiscoverResult(null)
    try {
      const response = await apiRequest<{ data: { discovered: number; updated: number; skipped: number; message?: string | null } }>('/onts/discover', { method: 'POST', body: JSON.stringify({ olt_public_id: discoverOlt }) }, token)
      setDiscoverResult(response.data)
      if (response.data.message) notify.warning(response.data.message)
      else notify.success(`ONT discovery finished: ${response.data.discovered} new, ${response.data.updated} updated.`)
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to discover ONTs.'))
    } finally {
      setDiscovering(false)
    }
  }

  const oltOptions = useMemo(() => olts.map(olt => ({ value: olt.public_id, label: `${olt.name} · ${olt.vendor}` })), [olts])
  const serialOptions = useMemo(() => {
    const values = onuInventory.filter(onu => onu.serial_number).map(onu => ({ value: onu.serial_number as string, label: `${onu.serial_number} · ${onu.vendor} ${onu.model}` }))
    if (form.serial_number && !values.some(option => option.value === form.serial_number)) values.unshift({ value: form.serial_number, label: `${form.serial_number} · current ONT` })
    return values
  }, [form.serial_number, onuInventory])
  const noOlts = !loading && !oltError && olts.length === 0
  const discoveryUnavailable = loading || Boolean(oltError) || olts.length === 0

  return <div className="flex min-w-0 flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4">
      <div><p className="billing-eyebrow">Network / access</p><h1 className="mt-2 text-lg font-semibold tracking-tight">ONTs</h1><p className="mt-1 max-w-2xl text-xs text-muted-foreground">Track optical network terminals discovered from your connected OLTs.</p></div>
      <div className="flex flex-wrap gap-2">
        {canUpdate && <Button type="button" variant="outline" className="min-h-11 sm:min-h-8" onClick={openSettings}><GearIcon data-icon="inline-start" />Settings</Button>}
        <Button type="button" variant="outline" className="min-h-11 sm:min-h-8" onClick={openDiscover} disabled={!canDiscover || discoveryUnavailable} title={noOlts ? 'Create an OLT before discovering ONTs' : undefined}><MagnifyingGlassIcon data-icon="inline-start" />Discover ONTs</Button>
        {canCreate && <Button type="button" className="min-h-11 sm:min-h-8" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add ONT</Button>}
      </div>
    </div>
    {error && <Alert variant="destructive"><AlertTitle>ONT inventory unavailable</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    {oltError && <Alert variant="destructive"><AlertTitle>OLT list unavailable</AlertTitle><AlertDescription>{oltError}</AlertDescription></Alert>}
    {settingsError && <Alert variant="destructive"><AlertTitle>ONT settings unavailable</AlertTitle><AlertDescription>{settingsError}</AlertDescription></Alert>}
    {noOlts && <Alert><AlertTitle>No OLTs available</AlertTitle><AlertDescription>Create an OLT first to associate ONTs and use discovery. You can still review this empty inventory.</AlertDescription></Alert>}
    <Card className="billing-records min-w-0 overflow-hidden">
      <CardHeader className="gap-4 border-b border-border/60 pb-4"><div className="flex flex-wrap items-start justify-between gap-4"><div><CardTitle className="flex items-center gap-2">ONT inventory <span className="bg-muted px-2 py-1 text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle><CardDescription className="mt-2">Optical network terminals registered to your access OLTs.</CardDescription></div><div className="text-xs text-muted-foreground">{loading ? 'Loading…' : `${rows.length} ${rows.length === 1 ? 'record' : 'records'}`}</div></div></CardHeader>
      <CardContent className="p-0">
        <div className="hidden overflow-x-auto md:block"><Table className="min-w-[940px]"><TableHeader><TableRow><TableHead>ONT</TableHead><TableHead>OLT</TableHead><TableHead>Frame / slot / PON</TableHead><TableHead>ONT ID</TableHead><TableHead>Serial number</TableHead><TableHead>Status</TableHead><TableHead>Manage</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{loading ? <TableRow><TableCell colSpan={8} className="h-40 text-center text-muted-foreground">Loading ONT inventory…</TableCell></TableRow> : rows.length ? rows.map(row => <TableRow key={row.public_id}><TableCell className="font-medium">{row.name}</TableCell><TableCell>{row.olt?.name || '—'}</TableCell><TableCell className="font-mono text-[11px]">{row.frame}/{row.slot}/{row.pon_port}</TableCell><TableCell>{row.ont_id ?? '—'}</TableCell><TableCell className="font-mono text-[11px]">{row.serial_number}</TableCell><TableCell><Badge variant={statusVariant(row.status)}>{row.status}</Badge></TableCell><TableCell>{canUpdate && (onManage ? <Button type="button" variant="outline" className="min-h-11 gap-1.5 sm:min-h-8" onClick={() => onManage(row.public_id)}><WrenchIcon data-icon="inline-start" />Manage</Button> : <Button asChild type="button" variant="outline" className="min-h-11 gap-1.5 sm:min-h-8"><a href={"/ont/" + row.public_id + "/manage"}><WrenchIcon data-icon="inline-start" />Manage</a></Button>)}</TableCell><TableCell className="text-right"><div className="inline-flex items-center justify-end gap-1">{canUpdate && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11" aria-label={`Edit ${row.name}`} title="Edit" onClick={() => openEdit(row)}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Delete ${row.name}`} title="Delete" disabled={deletingId === row.id} onClick={() => void remove(row)}><TrashIcon /></Button>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={8} className="h-56 text-center"><p className="text-sm font-medium">No ONTs configured yet.</p><p className="mx-auto mt-1 max-w-md text-xs text-muted-foreground">Add an ONT manually or discover unregistered ONTs from a connected OLT.</p><div className="mt-4 flex flex-wrap justify-center gap-2">{canCreate && <Button type="button" size="sm" className="min-h-11" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add ONT</Button>}{canDiscover && <Button type="button" variant="outline" size="sm" className="min-h-11" onClick={openDiscover} disabled={discoveryUnavailable}><MagnifyingGlassIcon data-icon="inline-start" />Discover ONTs</Button>}</div></TableCell></TableRow>}</TableBody></Table></div>
        <div className="space-y-3 p-3 md:hidden">{loading ? <div className="py-12 text-center text-xs text-muted-foreground">Loading ONT inventory…</div> : rows.length ? rows.map(row => <article className="rounded-md border border-border/70 bg-background/55 p-3" key={row.public_id}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><h2 className="truncate text-sm font-semibold">{row.name}</h2><p className="mt-1 text-xs text-muted-foreground">{row.olt?.name || 'No OLT'} · {row.frame}/{row.slot}/{row.pon_port}</p></div><Badge variant={statusVariant(row.status)}>{row.status}</Badge></div><dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs"><div><dt className="text-muted-foreground">ONT ID</dt><dd className="mt-0.5">{row.ont_id ?? '—'}</dd></div><div><dt className="text-muted-foreground">Serial number</dt><dd className="mt-0.5 break-all font-mono text-[11px]">{row.serial_number}</dd></div></dl>{canUpdate && (onManage ? <Button type="button" variant="outline" className="mt-3 min-h-11 w-full justify-center gap-2" onClick={() => onManage(row.public_id)}><WrenchIcon data-icon="inline-start" />Manage ONT</Button> : <Button asChild type="button" variant="outline" className="mt-3 min-h-11 w-full justify-center gap-2"><a href={"/ont/" + row.public_id + "/manage"}><WrenchIcon data-icon="inline-start" />Manage ONT</a></Button>)}<div className="mt-3 flex justify-end gap-1 border-t border-border/60 pt-2">{canUpdate && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11" aria-label={`Edit ${row.name}`} onClick={() => openEdit(row)}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Delete ${row.name}`} disabled={deletingId === row.id} onClick={() => void remove(row)}><TrashIcon /></Button>}</div></article>) : <div className="py-12 text-center"><p className="text-sm font-medium">No ONTs configured yet.</p><p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">Add an ONT manually or discover unregistered ONTs from a connected OLT.</p><div className="mt-4 grid gap-2 sm:flex sm:justify-center">{canCreate && <Button type="button" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add ONT</Button>}{canDiscover && <Button type="button" variant="outline" onClick={openDiscover} disabled={noOlts}><MagnifyingGlassIcon data-icon="inline-start" />Discover ONTs</Button>}</div></div>}</div>
      </CardContent>
    </Card>
    {formOpen && <OntFormModal editing={Boolean(editing)} form={form} olts={oltOptions} serialOptions={serialOptions} restrictSerial={doNotAllowRogueOnus} saving={saving} onChange={setForm} onClose={() => setFormOpen(false)} onSubmit={save} />}
    {discoverOpen && <DiscoverModal olts={oltOptions} selectedOlt={discoverOlt} discovering={discovering} result={discoverResult} onChange={setDiscoverOlt} onClose={() => setDiscoverOpen(false)} onSubmit={discover} />}
    {settingsOpen && <OntSettingsModal enabled={doNotAllowRogueOnus} ontIdCapacityPerPort={ontIdCapacityPerPort} saving={settingsSaving} onChange={setDoNotAllowRogueOnus} onCapacityChange={setOntIdCapacityPerPort} onClose={() => setSettingsOpen(false)} onSave={() => void saveSettings()} />}
    {discovering && <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/35 p-4" role="status" aria-live="polite"><Card className="w-full max-w-sm shadow-2xl"><CardContent className="flex items-center gap-3 p-5"><CircleNotchIcon className="animate-spin text-primary" size={22} aria-hidden="true" /><div><p className="text-sm font-semibold">Discovering ONTs</p><p className="mt-1 text-xs text-muted-foreground">Reading auto-find records from the selected OLT…</p></div></CardContent></Card></div>}
  </div>
}

function OntFormModal({ editing, form, olts, serialOptions, restrictSerial, saving, onChange, onClose, onSubmit }: { editing: boolean; form: OntForm; olts: { value: string; label: string }[]; serialOptions: { value: string; label: string }[]; restrictSerial: boolean; saving: boolean; onChange: (form: OntForm) => void; onClose: () => void; onSubmit: (event: React.FormEvent) => void }) {
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="ont-form-title"><Card className="billing-modal-card relative w-full max-w-2xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{editing ? 'Update record' : 'Create record'}</p><CardTitle id="ont-form-title">{editing ? 'Edit ONT' : 'Add ONT'}</CardTitle><CardDescription>Save ONT inventory details without changing the OLT configuration.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close ONT form"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form className="grid gap-4 sm:grid-cols-2" onSubmit={onSubmit}><Field><FieldLabel htmlFor="ont-olt">OLT</FieldLabel><select id="ont-olt" className="h-9 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.olt_public_id} onChange={event => onChange({ ...form, olt_public_id: event.target.value })} required><option value="">Select OLT</option>{olts.map(olt => <option key={olt.value} value={olt.value}>{olt.label}</option>)}</select></Field><Field><FieldLabel htmlFor="ont-name">ONT name</FieldLabel><Input id="ont-name" value={form.name} onChange={event => onChange({ ...form, name: event.target.value })} placeholder="Customer ONT" required /></Field><Field><FieldLabel htmlFor="ont-frame">Frame</FieldLabel><Input id="ont-frame" type="number" min="0" max="255" value={form.frame} onChange={event => onChange({ ...form, frame: event.target.value })} required /></Field><Field><FieldLabel htmlFor="ont-slot">Slot</FieldLabel><Input id="ont-slot" type="number" min="0" max="255" value={form.slot} onChange={event => onChange({ ...form, slot: event.target.value })} required /></Field><Field><FieldLabel htmlFor="ont-pon-port">PON port</FieldLabel><Input id="ont-pon-port" type="number" min="0" max="255" value={form.pon_port} onChange={event => onChange({ ...form, pon_port: event.target.value })} required /></Field><Field><FieldLabel htmlFor="ont-id">ONT ID <span className="font-normal text-muted-foreground">(optional)</span></FieldLabel><Input id="ont-id" type="number" min="0" max="255" value={form.ont_id} onChange={event => onChange({ ...form, ont_id: event.target.value })} placeholder="Assigned after registration" /></Field><Field><FieldLabel htmlFor="ont-serial">Serial number</FieldLabel>{restrictSerial ? <><select id="ont-serial" aria-label="Serial number" className="h-9 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.serial_number} onChange={event => onChange({ ...form, serial_number: event.target.value })} required><option value="">Select ONU serial</option>{serialOptions.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}</select><p className="mt-1 text-[11px] text-muted-foreground">Rogue ONU protection is enabled. Choose a serial already recorded in ONU inventory.</p></> : <Input id="ont-serial" value={form.serial_number} onChange={event => onChange({ ...form, serial_number: event.target.value })} placeholder="HWTC12345678" required />}</Field><Field><FieldLabel htmlFor="ont-status">Status</FieldLabel><select id="ont-status" className="h-9 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.status} onChange={event => onChange({ ...form, status: event.target.value as OntRecord['status'] })}><option value="unknown">Unknown</option><option value="discovered">Discovered</option><option value="rogue">Rogue</option><option value="online">Online</option><option value="offline">Offline</option></select></Field><Field className="sm:col-span-2"><FieldLabel htmlFor="ont-notes">Notes</FieldLabel><textarea id="ont-notes" className="min-h-24 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={form.notes} onChange={event => onChange({ ...form, notes: event.target.value })} placeholder="Optional context for this ONT" /></Field><div className="flex flex-wrap justify-end gap-2 border-t pt-4 sm:col-span-2"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : editing ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Add ONT</>}</Button></div></form></CardContent></Card></div>
}
function OntSettingsModal({ enabled, ontIdCapacityPerPort, saving, onChange, onCapacityChange, onClose, onSave }: { enabled: boolean; ontIdCapacityPerPort: string; saving: boolean; onChange: (enabled: boolean) => void; onCapacityChange: (value: string) => void; onClose: () => void; onSave: () => void }) {
  const capacity = Number(ontIdCapacityPerPort) || 64

  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid min-h-screen place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="ont-settings-title"><Card className="billing-modal-card relative w-full max-w-lg shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">Device policy</p><CardTitle id="ont-settings-title">ONT settings</CardTitle><CardDescription>Set ONT admission policy and automatic ID capacity for each PON / port.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close ONT settings"><XIcon /></Button></CardHeader><CardContent className="space-y-4 pt-5"><div className="rounded-md border border-border/70 bg-muted/20 p-4"><div className="flex items-start justify-between gap-4"><div><p className="text-sm font-medium">Do not allow rogue ONUs</p><p className="mt-1 text-xs leading-5 text-muted-foreground">Require every manually added ONT serial number to come from the ONU inventory. New discovery results are marked rogue.</p></div><button type="button" role="switch" aria-checked={enabled} aria-label="Do not allow rogue ONUs" onClick={() => onChange(!enabled)} className={enabled ? 'relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-primary bg-primary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2' : 'relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-input bg-muted transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2'}><span className={enabled ? 'size-5 translate-x-6 rounded-full bg-background shadow-sm transition-transform' : 'size-5 translate-x-1 rounded-full bg-background shadow-sm transition-transform'} /></button></div><p className={enabled ? 'mt-3 text-[11px] text-primary' : 'mt-3 text-[11px] text-muted-foreground'}>{enabled ? 'Enabled: serial selection is limited to ONU inventory.' : 'Disabled: serial numbers can be entered manually.'}</p></div><div className="rounded-md border border-border/70 bg-muted/20 p-4"><label className="grid gap-2 text-xs font-medium"><span className="text-sm text-foreground">Max ONTs per PON / port</span><Input type="number" min="1" max="256" value={ontIdCapacityPerPort} onChange={event => onCapacityChange(event.target.value)} aria-label="Max ONTs per PON / port" /><p className="mt-2 text-xs leading-5 text-muted-foreground">Automatic activation assigns IDs from 0 through {Math.max(0, capacity - 1)} on each PON / port. Saving applies this capacity to existing OLTs; adjust an individual OLT later if needed.</p></label></div><div className="flex flex-wrap justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="button" onClick={onSave} disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : 'Save settings'}</Button></div></CardContent></Card></div>
}

function DiscoverModal({ olts, selectedOlt, discovering, result, onChange, onClose, onSubmit }: { olts: { value: string; label: string }[]; selectedOlt: string; discovering: boolean; result: { discovered: number; updated: number; skipped: number; message?: string | null } | null; onChange: (value: string) => void; onClose: () => void; onSubmit: (event: React.FormEvent) => void }) {
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="ont-discover-title"><Card className="billing-modal-card relative w-full max-w-lg shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">Device scan</p><CardTitle id="ont-discover-title">Discover ONTs</CardTitle><CardDescription>Read unregistered ONTs from every PON port on a connected OLT.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close ONT discovery"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form className="grid gap-4" onSubmit={onSubmit}><Field><FieldLabel htmlFor="discover-olt">OLT to scan</FieldLabel><select id="discover-olt" className="h-9 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={selectedOlt} onChange={event => onChange(event.target.value)} required><option value="">Select OLT</option>{olts.map(olt => <option key={olt.value} value={olt.value}>{olt.label}</option>)}</select><p className="mt-1 text-xs text-muted-foreground">The OLT must have an active SSH session before scanning.</p></Field>{result && <div className="border border-primary/30 bg-primary/5 p-3 text-xs" role="status"><p className="font-semibold">Discovery complete</p><p className="mt-1 text-muted-foreground">{result.message || `${result.discovered} new, ${result.updated} updated, ${result.skipped} skipped.`}</p></div>}<div className="flex flex-wrap justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Close</Button><Button type="submit" disabled={discovering || !selectedOlt}>{discovering ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Discovering…</> : <><MagnifyingGlassIcon data-icon="inline-start" />Discover ONTs</>}</Button></div></form></CardContent></Card></div>
}
