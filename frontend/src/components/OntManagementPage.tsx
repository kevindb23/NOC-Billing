import { useCallback, useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import { ArrowLeftIcon, ArrowsClockwiseIcon, CircleNotchIcon, DownloadSimpleIcon, PowerIcon, WifiHighIcon, WrenchIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { apiDownload, apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { useConfirm } from './ConfirmProvider'

type ManagementData = {
  name: string
  serial_number: string
  status: string
  device_id: string
  manufacturer?: string | null
  model?: string | null
  last_inform?: string | null
  acs_server: { public_id?: string; name: string; status?: string }
  parameters?: {
    wifi_ssid?: string | null
    lan_status?: string | null
    upstream_port?: unknown
    wlan?: { band_24?: WlanBandData; band_5?: WlanBandData }
    wan?: WanData
  }
}

type WanData = {
  internet?: WanConnectionData[]
  tr069?: WanConnectionData[]
  connection_request_url?: unknown
}

type WanConnectionData = {
  type?: unknown
  name?: unknown
  pppoe_username?: unknown
  connection_status?: unknown
  ip_address?: unknown
  subnet_mask?: unknown
  gateway?: unknown
  dns_servers?: unknown
  enabled?: unknown
}

type WlanBandData = {
  available?: boolean
  enabled?: unknown
  ssid?: unknown
  hide_ssid?: unknown
  auto_channel?: unknown
  channel?: unknown
}

type WlanBandForm = {
  available: boolean
  enabled: boolean
  ssid: string
  password: string
  hide_ssid: boolean
  auto_channel: boolean
  channel: string
}

type WlanForm = { band_24: WlanBandForm; band_5: WlanBandForm }
type ManagementTab = 'overview' | 'wan' | 'wlan' | 'firmware' | 'maintenance'
type Props = { token: string; publicId: string; canManage: boolean; onBack?: () => void }
type TaskResult = { action?: string; accepted?: boolean; execution_status?: 'applied' | 'queued'; task_id?: string | null }

const actionLabels: Record<string, string> = {
  refresh_device: 'Device data refreshed.',
  refresh_wifi: 'Wi-Fi data refresh requested.',
  refresh_lan: 'LAN data refresh requested.',
  reboot: 'Reboot requested.',
  firmware: 'Firmware download requested.',
  update_upstream_port: 'Upstream port change requested.',
}

function emptyBand(): WlanBandForm {
  return { available: false, enabled: false, ssid: '', password: '', hide_ssid: false, auto_channel: true, channel: '' }
}

function emptyWlanForm(): WlanForm {
  return { band_24: emptyBand(), band_5: emptyBand() }
}

export function OntManagementPage({ token, publicId, canManage, onBack }: Props) {
  const [data, setData] = useState<ManagementData | null>(null)
  const [loading, setLoading] = useState(true)
  const [busyAction, setBusyAction] = useState('')
  const [firmwareUrl, setFirmwareUrl] = useState('')
  const [upstreamPort, setUpstreamPort] = useState('optical')
  const [wlanForm, setWlanForm] = useState<WlanForm>(emptyWlanForm)
  const [activeTab, setActiveTab] = useState<ManagementTab>('overview')
  const [error, setError] = useState('')
  const confirm = useConfirm()

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const response = await apiRequest<{ data: ManagementData }>('/onts/' + publicId + '/management', {}, token)
      const nextData = response.data ?? null
      setData(nextData)
      if (nextData?.parameters?.wlan) {
        setWlanForm(current => ({
          band_24: mergeWlanBand(current.band_24, nextData.parameters?.wlan?.band_24),
          band_5: mergeWlanBand(current.band_5, nextData.parameters?.wlan?.band_5),
        }))
      }
      const discoveredUpstreamPort = normalizeUpstreamPort(nextData?.parameters?.upstream_port)
      if (discoveredUpstreamPort) setUpstreamPort(discoveredUpstreamPort)
    } catch (exception) {
      setError(getErrorMessage(exception, 'Unable to connect to the assigned ACS.'))
    } finally {
      setLoading(false)
    }
  }, [publicId, token])

  useEffect(() => { void load() }, [load])

  const runAction = async (action: string, url?: string, extra: Record<string, unknown> = {}) => {
    if (!canManage || busyAction) return
    if (action === 'reboot' && !await confirm({ title: 'Reboot this ONT?', description: 'GenieACS will send a reboot task to the ONT. The device may be unavailable briefly.', confirmLabel: 'Reboot ONT', destructive: true })) return
    setBusyAction(action)
    try {
      const response = await apiRequest<{ data?: TaskResult }>('/onts/' + publicId + '/management/tasks', { method: 'POST', body: JSON.stringify({ action, ...extra, ...(url ? { firmware_url: url } : {}) }) }, token)
      notifyTaskResult(response.data, action)
      if (action !== 'firmware') await load()
    } catch (exception) {
      notify.error('ONT task rejected: ' + getErrorMessage(exception, 'Unable to queue the ONT task.'))
    } finally {
      setBusyAction('')
    }
  }

  const exportConfiguration = async () => {
    if (!data || busyAction) return
    setBusyAction('export_configuration')
    try {
      const blob = await apiDownload('/onts/' + publicId + '/management/configuration-export', token)
      const link = document.createElement('a')
      const objectUrl = URL.createObjectURL(blob)
      link.href = objectUrl
      link.download = 'hw_ctree.xml'
      link.click()
      URL.revokeObjectURL(objectUrl)
      notify.success('The ONT configuration was exported.')
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to export the ONT configuration.'))
    } finally {
      setBusyAction('')
    }
  }

  const saveWlan = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!canManage || !data || busyAction) return
    setBusyAction('update_wifi')
    try {
      const response = await apiRequest<{ data?: TaskResult }>('/onts/' + publicId + '/management/tasks', {
        method: 'POST',
        body: JSON.stringify({ action: 'update_wifi', wlan: serializeWlan(wlanForm) }),
      }, token)
      notifyTaskResult(response.data, 'update_wifi')
      setWlanForm(current => ({ band_24: { ...current.band_24, password: '' }, band_5: { ...current.band_5, password: '' } }))
      await load()
    } catch (exception) {
      notify.error('WLAN task rejected: ' + getErrorMessage(exception, 'Unable to queue the WLAN settings.'))
    } finally {
      setBusyAction('')
    }
  }

  const pageName = displayValue(data?.name, 'ONT management')
  const status = displayValue(data?.status, 'unknown')
  const actionDisabled = !canManage || !data || Boolean(busyAction)

  return <div className="relative flex min-w-0 flex-col gap-4">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4">
      <div className="min-w-0">
        <p className="billing-eyebrow">Network / ONT management</p>
        <div className="mt-2 flex flex-wrap items-center gap-3">
          <h1 className="text-xl font-semibold tracking-tight">{pageName}</h1>
          <Badge variant="outline" className="billing-status-badge">{status}</Badge>
        </div>
        <p className="mt-1 max-w-3xl truncate text-xs text-muted-foreground">
          {displayValue(data?.manufacturer, 'Manufacturer not reported')} · {displayValue(data?.model, 'Model not reported')} · {displayValue(data?.serial_number, 'Serial number not reported')}
        </p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" variant="outline" className="min-h-11 sm:min-h-8" onClick={() => onBack ? onBack() : window.location.assign('/ont')}><ArrowLeftIcon data-icon="inline-start" />Back to ONTs</Button>
        <Button type="button" variant="outline" className="min-h-11 sm:min-h-8" onClick={() => void load()} disabled={loading}><ArrowsClockwiseIcon data-icon="inline-start" />Refresh</Button>
      </div>
    </div>

    <div className="flex max-w-full flex-wrap items-center gap-1 rounded-lg border border-emerald-900/10 bg-[#f7f3ea]/90 p-1 shadow-[inset_0_1px_0_rgb(255_255_255_/_0.75)]" role="tablist" aria-label="ONT management sections">
      <TabButton active={activeTab === 'overview'} label="Overview" onClick={() => setActiveTab('overview')} />
      <TabButton active={activeTab === 'wan'} label="WAN" onClick={() => setActiveTab('wan')} />
      <TabButton active={activeTab === 'wlan'} label="WLAN" onClick={() => setActiveTab('wlan')} />
      <TabButton active={activeTab === 'firmware'} label="Firmware" onClick={() => setActiveTab('firmware')} />
      <TabButton active={activeTab === 'maintenance'} label="Maintenance" onClick={() => setActiveTab('maintenance')} />
    </div>

    {loading && <Alert role="status" className="border-border/70 bg-background/55"><CircleNotchIcon className="animate-spin" aria-hidden="true" /><AlertTitle>Loading ACS values</AlertTitle><AlertDescription>Reading the latest connection details from GenieACS.</AlertDescription></Alert>}
    {error && <Alert variant="destructive"><AlertTitle>ACS management unavailable</AlertTitle><AlertDescription>{error}<span className="mt-2 block text-xs">Assign an ACS and send a CWMP inform, then refresh this page.</span></AlertDescription></Alert>}
    {!loading && !error && !data && <Alert><AlertTitle>No ACS device data yet</AlertTitle><AlertDescription>This page is ready. Connect the ONT to an ACS and send a CWMP inform to populate live values.</AlertDescription></Alert>}

    {activeTab === 'overview' && <OverviewTab data={data} canManage={canManage} actionDisabled={actionDisabled} busyAction={busyAction} runAction={runAction} />}
    {activeTab === 'wan' && <WanTab data={data} />}
    {activeTab === 'wlan' && <WlanTab data={data} canManage={canManage} busyAction={busyAction} form={wlanForm} setForm={setWlanForm} onSubmit={saveWlan} />}
    {activeTab === 'firmware' && <FirmwareTab data={data} actionDisabled={actionDisabled} busyAction={busyAction} firmwareUrl={firmwareUrl} setFirmwareUrl={setFirmwareUrl} runAction={runAction} />}
    {activeTab === 'maintenance' && <MaintenanceTab data={data} actionDisabled={actionDisabled} busyAction={busyAction} upstreamPort={upstreamPort} setUpstreamPort={setUpstreamPort} exportConfiguration={exportConfiguration} runAction={runAction} />}
  </div>
}

function notifyTaskResult(result: TaskResult | undefined, action: string): void {
  if (result?.execution_status === 'applied') {
    notify.success('ONT task accepted and applied by GenieACS.')
    return
  }

  if (result?.execution_status === 'queued') {
    notify.info('GenieACS accepted the ONT task and queued it; device confirmation is pending.')
    return
  }

  notify.info(actionLabels[action] || 'ONT task accepted and queued.')
}

function WanTab({ data }: { data: ManagementData | null }) {
  const wan = data?.parameters?.wan
  const internet = wan?.internet ?? []
  const tr069 = wan?.tr069 ?? []
  const hasConnections = internet.length > 0 || tr069.length > 0
  const connectionRequestUrl = displayValue(wan?.connection_request_url, '')

  return <Card className="billing-records overflow-hidden">
    <CardHeader className="border-b border-border/60"><CardTitle>WAN details</CardTitle><CardDescription>IP information reported by the ONT for Internet and TR-069 connectivity.</CardDescription></CardHeader>
    <CardContent className="p-4 sm:p-5">
      {!hasConnections && <p className="rounded-lg border border-dashed border-border/70 bg-background/40 p-4 text-sm text-muted-foreground">No WAN or TR-069 IP details were reported by this ONT. Refresh the device after its next CWMP inform.</p>}
      {hasConnections && <div className="grid gap-4 lg:grid-cols-2">
        <WanConnectionSection title="Internet WAN" description="The subscriber-facing connection." connections={internet} />
        <WanConnectionSection title="TR-069" description="The management connection used by GenieACS." connections={tr069} />
      </div>}
      {connectionRequestUrl && <div className="mt-4 border-t border-border/60 pt-4"><Info label="Connection request URL" value={connectionRequestUrl} /></div>}
    </CardContent>
  </Card>
}

function WanConnectionSection({ title, description, connections }: { title: string; description: string; connections: WanConnectionData[] }) {
  return <section className="rounded-lg border border-border/70 bg-background/40 p-4" aria-labelledby={title.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-heading'}>
    <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 id={title.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-heading'} className="text-sm font-semibold">{title}</h3><p className="mt-1 text-xs text-muted-foreground">{description}</p></div><Badge variant="outline">{connections.length ? (connections.length === 1 ? '1 connection' : connections.length + ' connections') : 'Not reported'}</Badge></div>
    {connections.length === 0 && <p className="mt-4 text-sm text-muted-foreground">This connection was not reported by the ONT.</p>}
    {connections.map((connection, index) => <dl key={String(connection.name ?? connection.type ?? index) + '-' + index} className="mt-4 grid gap-x-5 gap-y-4 border-t border-border/60 pt-4 sm:grid-cols-2">
      <Info label="Status" value={connection.connection_status} />
      <Info label="IP address" value={connection.ip_address} mono />
      {connection.type === 'pppoe' && <Info label="PPPoE username" value={connection.pppoe_username} mono />}
      <Info label="Subnet mask" value={connection.subnet_mask} mono />
      <Info label="Default gateway" value={connection.gateway} mono />
      <Info label="DNS servers" value={formatDnsServers(connection.dns_servers)} mono />
    </dl>)}
  </section>
}

function OverviewTab({ data, canManage, actionDisabled, busyAction, runAction }: { data: ManagementData | null; canManage: boolean; actionDisabled: boolean; busyAction: string; runAction: (action: string, url?: string) => Promise<void> }) {
  return <div className="flex flex-col gap-4">
    <section className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]" aria-label="ONT connection details">
      <Card className="billing-records overflow-hidden">
        <CardHeader className="border-b border-border/60"><CardTitle>Device connection</CardTitle><CardDescription>Identity and reachability reported by the assigned ACS.</CardDescription></CardHeader>
        <CardContent className="p-4 sm:p-5"><dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
          <Info label="ACS server" value={data?.acs_server?.name} fallback="Not assigned" />
          <Info label="ACS status" value={data?.acs_server?.status} />
          <Info label="Serial number" value={data?.serial_number} mono />
          <Info label="GenieACS device ID" value={data?.device_id} mono />
          <Info label="Manufacturer" value={data?.manufacturer} />
          <Info label="Model" value={data?.model} />
          <Info label="Last inform" value={formatDate(data?.last_inform)} />
        </dl></CardContent>
      </Card>

      <Card className="billing-records overflow-hidden">
        <CardHeader className="border-b border-border/60"><CardTitle>Latest readings</CardTitle><CardDescription>Cached values from the latest GenieACS inform.</CardDescription></CardHeader>
        <CardContent className="p-4 sm:p-5"><dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-1">
          <Info label="Wi-Fi SSID" value={data?.parameters?.wifi_ssid} />
          <Info label="LAN status" value={data?.parameters?.lan_status} />
        </dl><p className="mt-6 border-t border-border/60 pt-4 text-xs text-muted-foreground">Refresh a reading to request the current parameter tree from the ONT.</p></CardContent>
      </Card>
    </section>

    <Card className="billing-records overflow-hidden">
      <CardHeader className="border-b border-border/60"><CardTitle>Provisioning actions</CardTitle><CardDescription>Queue supported GenieACS tasks for this ONT.</CardDescription></CardHeader>
      <CardContent className="p-4 sm:p-5">
        <div className="grid gap-2 sm:grid-cols-3">
          <ActionButton label="Refresh device" icon={<WrenchIcon />} busy={busyAction === 'refresh_device'} disabled={actionDisabled} onClick={() => void runAction('refresh_device')} />
          <ActionButton label="Refresh Wi-Fi" icon={<WifiHighIcon />} busy={busyAction === 'refresh_wifi'} disabled={actionDisabled} onClick={() => void runAction('refresh_wifi')} />
          <ActionButton label="Refresh LAN" icon={<ArrowsClockwiseIcon />} busy={busyAction === 'refresh_lan'} disabled={actionDisabled} onClick={() => void runAction('refresh_lan')} />
        </div>
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-4">
          <p className="text-xs text-muted-foreground">{canManage ? data ? 'The ONT must be reachable by CWMP for immediate execution.' : 'Actions become available after the ACS returns this ONT.' : 'You have view-only access to this ONT.'}</p>
          <ActionButton label="Reboot ONT" icon={<PowerIcon />} destructive busy={busyAction === 'reboot'} disabled={actionDisabled} onClick={() => void runAction('reboot')} />
        </div>
      </CardContent>
    </Card>
  </div>
}

function WlanTab({ data, canManage, busyAction, form, setForm, onSubmit }: { data: ManagementData | null; canManage: boolean; busyAction: string; form: WlanForm; setForm: React.Dispatch<React.SetStateAction<WlanForm>>; onSubmit: (event: React.FormEvent<HTMLFormElement>) => void }) {
  const disabled = !canManage || !data || Boolean(busyAction)
  const updateBand = (key: keyof WlanForm, changes: Partial<WlanBandForm>) => setForm(current => ({ ...current, [key]: { ...current[key], ...changes } }))

  return <Card className="billing-records overflow-hidden">
    <CardHeader className="border-b border-border/60"><CardTitle>WLAN settings</CardTitle><CardDescription>Manage the common TR-069 WLAN parameters exposed by this ONT.</CardDescription></CardHeader>
    <CardContent className="p-4 sm:p-5">
      <form className="flex flex-col gap-5" onSubmit={onSubmit}>
        <div className="grid gap-4 xl:grid-cols-2">
          <WlanBandCard bandKey="band_24" title="2.4 GHz" description="Longer range and broad device compatibility." band={form.band_24} disabled={disabled} onChange={changes => updateBand('band_24', changes)} />
          <WlanBandCard bandKey="band_5" title="5 GHz" description="Higher throughput with shorter range." band={form.band_5} disabled={disabled} onChange={changes => updateBand('band_5', changes)} />
        </div>
        <div className="flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
          <p className="max-w-2xl text-xs text-muted-foreground">Passwords are never read back from GenieACS. Leave a password blank to keep the current value. Parameter availability depends on the ONT model.</p>
          <Button type="submit" className="min-h-11 sm:min-h-9" disabled={disabled}>{busyAction === 'update_wifi' ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving WLAN…</> : <><WifiHighIcon data-icon="inline-start" />Save WLAN settings</>}</Button>
        </div>
      </form>
    </CardContent>
  </Card>
}

function WlanBandCard({ bandKey, title, description, band, disabled, onChange }: { bandKey: string; title: string; description: string; band: WlanBandForm; disabled: boolean; onChange: (changes: Partial<WlanBandForm>) => void }) {
  const unavailable = !band.available
  const formDisabled = disabled || unavailable

  return <section className="rounded-lg border border-border/70 bg-background/40 p-4" aria-labelledby={bandKey + '-heading'}>
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div><h3 id={bandKey + '-heading'} className="text-sm font-semibold">{title}</h3><p className="mt-1 text-xs text-muted-foreground">{unavailable ? 'This ONU did not report a '+title+' WLAN configuration.' : description}</p></div>
      <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-primary" checked={band.enabled} onChange={event => onChange({ enabled: event.target.checked })} disabled={formDisabled} />Enabled</label>
    </div>
    <div className="mt-4 grid gap-4 sm:grid-cols-2">
      <Field className="sm:col-span-2"><FieldLabel htmlFor={bandKey + '-ssid'}>SSID</FieldLabel><Input id={bandKey + '-ssid'} value={band.ssid} onChange={event => onChange({ ssid: event.target.value })} placeholder={title + ' network name'} required={!formDisabled} disabled={formDisabled} /></Field>
      <Field className="sm:col-span-2"><FieldLabel htmlFor={bandKey + '-password'}>Wi-Fi password</FieldLabel><Input id={bandKey + '-password'} type="password" autoComplete="new-password" value={band.password} onChange={event => onChange({ password: event.target.value })} placeholder="Leave blank to keep current password" disabled={formDisabled} /></Field>
      <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-primary" checked={band.hide_ssid} onChange={event => onChange({ hide_ssid: event.target.checked })} disabled={formDisabled} />Hide SSID</label>
      <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-primary" checked={band.auto_channel} onChange={event => onChange({ auto_channel: event.target.checked })} disabled={formDisabled} />Automatic channel</label>
      <Field className="sm:max-w-48"><FieldLabel htmlFor={bandKey + '-channel'}>Channel</FieldLabel><Input id={bandKey + '-channel'} type="number" min="0" max="200" value={band.channel} onChange={event => onChange({ channel: event.target.value })} placeholder="Auto" disabled={formDisabled || band.auto_channel} /></Field>
    </div>
  </section>
}

function FirmwareTab({ data, actionDisabled, busyAction, firmwareUrl, setFirmwareUrl, runAction }: { data: ManagementData | null; actionDisabled: boolean; busyAction: string; firmwareUrl: string; setFirmwareUrl: (value: string) => void; runAction: (action: string, url?: string) => Promise<void> }) {
  return <Card className="billing-records overflow-hidden">
    <CardHeader className="border-b border-border/60"><CardTitle>Firmware upgrade</CardTitle><CardDescription>Queue a firmware download using a URL reachable by the ACS server.</CardDescription></CardHeader>
    <CardContent className="p-4 sm:p-5">
      <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={event => { event.preventDefault(); if (firmwareUrl) void runAction('firmware', firmwareUrl) }}>
        <Field className="min-w-0 flex-1"><FieldLabel htmlFor="ont-firmware-url">Firmware image URL</FieldLabel><Input id="ont-firmware-url" type="url" value={firmwareUrl} onChange={event => setFirmwareUrl(event.target.value)} placeholder="https://firmware.example.com/ont.bin" required disabled={actionDisabled} /></Field>
        <Button type="submit" className="min-h-11 sm:min-h-9" disabled={actionDisabled}>{busyAction === 'firmware' ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Queueing…</> : <><DownloadSimpleIcon data-icon="inline-start" />Queue firmware</>}</Button>
      </form>
      <p className="mt-3 text-xs text-muted-foreground">Confirm the image, model, version, and vendor requirements before sending firmware to a production ONT.</p>
      {!data && <p className="mt-3 text-xs text-muted-foreground">Firmware actions become available after the assigned ACS returns this ONT.</p>}
    </CardContent>
  </Card>
}

function MaintenanceTab({ data, actionDisabled, busyAction, upstreamPort, setUpstreamPort, exportConfiguration, runAction }: { data: ManagementData | null; actionDisabled: boolean; busyAction: string; upstreamPort: string; setUpstreamPort: (value: string) => void; exportConfiguration: () => void; runAction: (action: string, url?: string, extra?: Record<string, unknown>) => Promise<void> }) {
  return <div className="flex flex-col gap-4">
    <Card className="billing-records overflow-hidden">
      <CardHeader className="border-b border-border/60"><CardTitle>Maintenance</CardTitle><CardDescription>Export the current ACS snapshot or change the ONT upstream port.</CardDescription></CardHeader>
      <CardContent className="grid gap-4 p-4 sm:p-5 lg:grid-cols-2">
        <section className="flex flex-col justify-between gap-5 rounded-lg border border-border/70 bg-background/40 p-4" aria-labelledby="configuration-export-heading">
          <div><h3 id="configuration-export-heading" className="text-sm font-semibold">Configuration export</h3><p className="mt-1 text-xs leading-5 text-muted-foreground">Request the device&apos;s actual hw_ctree.xml through the ACS file-upload flow.</p></div>
          <Button type="button" variant="outline" className="min-h-11 justify-start sm:min-h-9" disabled={!data || Boolean(busyAction)} onClick={() => void exportConfiguration()}>{busyAction === 'export_configuration' ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Exporting…</> : <><DownloadSimpleIcon data-icon="inline-start" />Export current configuration</>}</Button>
        </section>
          <form className="flex flex-col gap-5 rounded-lg border border-border/70 bg-background/40 p-4" onSubmit={event => { event.preventDefault(); void runAction('update_upstream_port', undefined, { upstream_port: upstreamPort }) }}>
          <div><h3 className="text-sm font-semibold">Upstream port</h3><p className="mt-1 text-xs leading-5 text-muted-foreground">Choose which physical interface the ONT uses for upstream traffic.</p></div>
          <Field><FieldLabel htmlFor="ont-upstream-port">Port</FieldLabel><select id="ont-upstream-port" className="flex h-9 w-full border border-input bg-background px-3 py-1 text-sm shadow-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50" value={upstreamPort} onChange={event => setUpstreamPort(event.target.value)} required disabled={actionDisabled}><option value="optical">Optical</option><option value="lan1">LAN1</option><option value="lan2">LAN2</option><option value="lan3">LAN3</option><option value="lan4">LAN4</option></select></Field>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p className="text-xs text-muted-foreground">This updates the Huawei X_HW_UpPortMode parameter.</p><Button type="submit" className="min-h-11 sm:min-h-9" disabled={actionDisabled}>{busyAction === 'update_upstream_port' ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Applying…</> : <><WrenchIcon data-icon="inline-start" />Apply upstream port</>}</Button></div>
        </form>
      </CardContent>
    </Card>
  </div>
}

function TabButton({ active, label, onClick }: { active: boolean; label: string; onClick: () => void }) {
  return <button type="button" role="tab" aria-selected={active} className={'min-h-11 rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/40 focus-visible:ring-offset-2 sm:min-h-8 ' + (active ? 'bg-emerald-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:bg-white/65 hover:text-slate-950')} onClick={onClick}>{label}</button>
}

function mergeWlanBand(current: WlanBandForm, next?: WlanBandData): WlanBandForm {
  if (!next) return { ...current, available: false }
  return {
    ...current,
    available: true,
    enabled: readBoolean(next.enabled, current.enabled),
    ssid: displayValue(next.ssid, ''),
    hide_ssid: readBoolean(next.hide_ssid, current.hide_ssid),
    auto_channel: readBoolean(next.auto_channel, current.auto_channel),
    channel: displayValue(next.channel, ''),
  }
}

function normalizeUpstreamPort(value: unknown): string | null {
  const normalized = displayValue(value, '').toLowerCase()
  if (normalized === '0' || normalized === 'optical') return 'optical'
  if (normalized === '1' || normalized === 'lan1') return 'lan1'
  if (normalized === '2' || normalized === 'lan2') return 'lan2'
  if (normalized === '3' || normalized === 'lan3') return 'lan3'
  if (normalized === '4' || normalized === 'lan4') return 'lan4'
  return null
}

function serializeWlan(form: WlanForm) {
  return {
    band_24: serializeBand(form.band_24),
    ...(form.band_5.available ? { band_5: serializeBand(form.band_5) } : {}),
  }
}

function serializeBand(band: WlanBandForm) {
  return { enabled: band.enabled, ssid: band.ssid, password: band.password || null, hide_ssid: band.hide_ssid, auto_channel: band.auto_channel, channel: band.channel === '' ? null : Number(band.channel) }
}

function readBoolean(value: unknown, fallback: boolean): boolean {
  const text = displayValue(value, '')
  if (!text) return fallback
  return text === 'true' || text === '1'
}

function displayValue(value: unknown, fallback = 'Not reported'): string {
  if (value === null || value === undefined || value === '') return fallback
  if (typeof value === 'object' && value !== null && '_value' in value) return displayValue((value as { _value?: unknown })._value, fallback)
  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : fallback
}

function formatDnsServers(value: unknown): string {
  if (Array.isArray(value)) return value.map(item => displayValue(item, '')).filter(Boolean).join(', ') || 'Not reported'
  return displayValue(value)
}

function formatDate(value: unknown): string {
  const text = displayValue(value, '')
  if (!text) return 'Not reported'
  const date = new Date(text)
  return Number.isNaN(date.getTime()) ? text : date.toLocaleString()
}

function Info({ label, value, fallback = 'Not reported', mono = false }: { label: string; value: unknown; fallback?: string; mono?: boolean }) {
  return <div className="min-w-0"><dt className="text-xs text-muted-foreground">{label}</dt><dd className={'mt-1 break-words text-sm font-medium' + (mono ? ' font-mono text-xs' : '')}>{displayValue(value, fallback)}</dd></div>
}

function ActionButton({ label, icon, busy, disabled, destructive, onClick }: { label: string; icon: ReactNode; busy: boolean; disabled: boolean; destructive?: boolean; onClick: () => void }) {
  return <Button type="button" variant={destructive ? 'destructive' : 'outline'} className="min-h-11 justify-start gap-2 sm:min-h-10" disabled={disabled} onClick={onClick}>{busy ? <CircleNotchIcon className="animate-spin" data-icon="inline-start" /> : icon}{busy ? 'Working…' : label}</Button>
}
