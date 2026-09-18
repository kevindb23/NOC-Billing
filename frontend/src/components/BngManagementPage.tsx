import { useEffect, useState } from 'react'
import { ArrowLeftIcon, CircleNotchIcon, EyeIcon, FloppyDiskIcon, PencilSimpleIcon, PlugIcon, PlusIcon, StopIcon, TrashIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { apiRequest } from '@/lib/api'
import { getErrorMessage, notify } from '@/lib/notifications'
import { bngDrivers, type BngServiceTab } from '@/lib/bngDrivers'

type Bng = { public_id: string; name: string; vendor: string; model?: string | null; management_endpoint?: string | null; preferred_transport?: string | null; status: string; parent_interface?: string | null; egress_interface?: string | null }
type ManagementTab = 'ssh' | BngServiceTab
type AccelValues = { name: string; bras_name: string; gateway_address: string; pool_name: string; pool_start: string; pool_end: string; nas_ip: string; nas_identifier: string; radius_gateway_address: string; radius_server: string; radius_secret: string; auth_port: string; acct_port: string; dae_server: string; dae_port: string; dae_secret: string; primary_dns: string; secondary_dns: string }
type CgnatPolicy = { public_id: string; name: string; subscriber_network: string; public_ip_mode: 'single' | 'range' | 'masquerade'; public_ip_start?: string | null; public_ip_end?: string | null; local_bypass_network?: string | null; status?: string | null; notes?: string | null }
type CgnatForm = { name: string; subscriber_network: string; public_ip_mode: 'single' | 'range' | 'masquerade'; public_ip_start: string; public_ip_end: string; local_bypass_network: string; notes: string }
type ForwardingRule = { public_id: string; name: string; customer_interface: string; internet_interface: string; status?: string | null; notes?: string | null }
type ForwardingForm = { name: string; customer_interface: string; internet_interface: string; notes: string }
type RadiusServer = { public_id: string; name: string; server_address: string; secret?: string; database_name: string; database_username: string; auth_port: number; accounting_port: number; status?: string | null; notes?: string | null }
type RadiusForm = { name: string; server_address: string; secret: string; database_name: string; database_username: string; database_password: string; auth_port: string; accounting_port: string; notes: string }

const emptyAccel = (): AccelValues => ({ name: 'BNG', bras_name: '', gateway_address: '', pool_name: '', pool_start: '', pool_end: '', nas_ip: '', nas_identifier: '', radius_gateway_address: '', radius_server: '', radius_secret: '', auth_port: '1812', acct_port: '1813', dae_server: '', dae_port: '3799', dae_secret: '', primary_dns: '', secondary_dns: '' })
const emptyCgnat = (): CgnatForm => ({ name: '', subscriber_network: '', public_ip_mode: 'range', public_ip_start: '', public_ip_end: '', local_bypass_network: '', notes: '' })
const emptyForwarding = (): ForwardingForm => ({ name: '', customer_interface: '', internet_interface: '', notes: '' })
const emptyRadius = (): RadiusForm => ({ name: '', server_address: '', secret: '', database_name: '', database_username: '', database_password: '', auth_port: '1812', accounting_port: '1813', notes: '' })

export function BngManagementPage({ token, publicId, onBack }: { token: string; publicId: string; onBack: () => void }) {
  const [bng, setBng] = useState<Bng | null>(null)
  const [sessionStatus, setSessionStatus] = useState('stopped')
  const [activeTab, setActiveTab] = useState<ManagementTab>('ssh')
  const [loading, setLoading] = useState(true)
  const [working, setWorking] = useState(false)
  const [savingInterfaces, setSavingInterfaces] = useState(false)
  const [parentInterface, setParentInterface] = useState('')
  const [egressInterface, setEgressInterface] = useState('')
  const [accel, setAccel] = useState<AccelValues>(emptyAccel())
  const [accelLoading, setAccelLoading] = useState(false)
  const [accelLoaded, setAccelLoaded] = useState(false)
  const [accelSaving, setAccelSaving] = useState(false)
  const [preview, setPreview] = useState<string | null>(null)
  const [previewTitle, setPreviewTitle] = useState('Configuration preview')
  const [cgnatRows, setCgnatRows] = useState<CgnatPolicy[]>([])
  const [cgnatLoading, setCgnatLoading] = useState(false)
  const [cgnatSaving, setCgnatSaving] = useState(false)
  const [cgnatModalOpen, setCgnatModalOpen] = useState(false)
  const [cgnatForm, setCgnatForm] = useState<CgnatForm>(emptyCgnat())
  const [forwardingRows, setForwardingRows] = useState<ForwardingRule[]>([])
  const [forwardingLoading, setForwardingLoading] = useState(false)
  const [forwardingSaving, setForwardingSaving] = useState(false)
  const [forwardingModalOpen, setForwardingModalOpen] = useState(false)
  const [forwardingForm, setForwardingForm] = useState<ForwardingForm>(emptyForwarding())
  const [radiusRows, setRadiusRows] = useState<RadiusServer[]>([])
  const [radiusLoading, setRadiusLoading] = useState(false)
  const [radiusSaving, setRadiusSaving] = useState(false)
  const [radiusTesting, setRadiusTesting] = useState(false)
  const [radiusModalOpen, setRadiusModalOpen] = useState(false)
  const [editingRadius, setEditingRadius] = useState<string | null>(null)
  const [radiusForm, setRadiusForm] = useState<RadiusForm>(emptyRadius())

  const updateAccel = (key: keyof AccelValues, value: string) => setAccel(current => ({ ...current, [key]: value }))

  const loadAccel = async () => {
    setAccelLoading(true)
    try {
      const response = await apiRequest<{ data: { values: AccelValues } }>(`/bngs/${publicId}/accel-ppp-config`, {}, token)
      setAccel({ ...emptyAccel(), ...response.data.values, name: 'BNG' })
      setAccelLoaded(true)
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load Accel-PPP configuration.')) }
    finally { setAccelLoading(false) }
  }

  const loadCgnat = async () => {
    setCgnatLoading(true)
    try {
      const response = await apiRequest<{ data: CgnatPolicy[] }>(`/bngs/${publicId}/cgnat-policies`, {}, token)
      setCgnatRows(response.data)
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load CGNAT policies.')) }
    finally { setCgnatLoading(false) }
  }

  const loadForwarding = async () => {
    setForwardingLoading(true)
    try {
      const response = await apiRequest<{ data: ForwardingRule[] }>(`/bngs/${publicId}/forwarding-rules`, {}, token)
      setForwardingRows(response.data)
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load forwarding rules.')) }
    finally { setForwardingLoading(false) }
  }

  const loadRadius = async () => { setRadiusLoading(true); try { const response = await apiRequest<{ data: RadiusServer[] }>(`/bngs/${publicId}/radius-servers`, {}, token); setRadiusRows(response.data) } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load RADIUS servers.')) } finally { setRadiusLoading(false) } }

  const load = async () => {
    try {
      const response = await apiRequest<{ data: Bng }>(`/bngs/${publicId}`, {}, token)
      setBng(response.data)
      setParentInterface(response.data.parent_interface || 'ens17')
      setEgressInterface(response.data.egress_interface || 'ens16')
      const session = await apiRequest<{ data: { status: string } }>(`/bngs/${publicId}/session-status`, {}, token)
      setSessionStatus(session.data.status)
      if (response.data.vendor === 'linux' && session.data.status === 'connected') { void loadAccel(); void loadRadius() }
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load BNG management data.')) }
    finally { setLoading(false) }
  }

  const toggleSession = async () => {
    const active = ['connecting', 'connected', 'disconnected'].includes(sessionStatus)
    setWorking(true)
    try {
      const response = await apiRequest<{ data: { status: string } }>(`/bngs/${publicId}/${active ? 'disconnect' : 'connect'}`, { method: 'POST' }, token)
      setSessionStatus(response.data.status)
      notify.info(active ? 'BNG SSH connection stopped.' : 'BNG SSH connection is starting.')
    } catch (exception) { notify.error(getErrorMessage(exception, active ? 'Unable to stop the BNG session.' : 'Unable to connect to the BNG.')) }
    finally { setWorking(false) }
  }

  const saveInterfaces = async () => {
    setSavingInterfaces(true)
    try { await notify.promise(apiRequest(`/bngs/${publicId}/interfaces`, { method: 'PATCH', body: JSON.stringify({ parent_interface: parentInterface.trim(), egress_interface: egressInterface.trim() }) }, token), { loading: 'Saving BNG interfaces…', success: 'BNG interfaces saved.', error: 'Unable to save BNG interfaces.' }) }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save BNG interfaces.')) }
    finally { setSavingInterfaces(false) }
  }

  const previewAccel = async () => {
    try { const response = await notify.promise(apiRequest<{ data: { content: string } }>(`/bngs/${publicId}/accel-ppp-config/preview`, { method: 'POST', body: JSON.stringify(accel) }, token), { loading: 'Generating configuration preview…', success: 'Preview generated.', error: 'Unable to generate preview.' }); setPreviewTitle('Accel-PPP configuration preview'); setPreview(response.data.content) }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to generate preview.')) }
  }

  const previewIptables = async (title: string, values: Record<string, string>) => {
    try { const response = await notify.promise(apiRequest<{ data: { content: string } }>(`/bngs/${publicId}/iptables/preview`, { method: 'POST', body: JSON.stringify(values) }, token), { loading: 'Reading current iptables rules…', success: 'Preview generated.', error: 'Unable to read the BNG rules file.' }); setPreviewTitle(title); setPreview(response.data.content) }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to generate iptables preview.')) }
  }

  const previewWholeIptables = () => void previewIptables('iptables rules.v4 preview', { kind: 'file' })
  const previewRecord = (title: string, values: Record<string, string>) => {
    const lines: string[] = []
    if (values.kind === 'forwarding' && values.customer_interface && values.internet_interface) {
      lines.push(`-A FORWARD -i ${values.customer_interface} -o ${values.internet_interface} -j ACCEPT`)
      lines.push(`-A FORWARD -i ${values.internet_interface} -o ${values.customer_interface} -j ACCEPT`)
    } else if (values.kind === 'cgnat') {
      if (values.subscriber_network && values.local_bypass_network) lines.push(`-A POSTROUTING -s ${values.subscriber_network} -d ${values.local_bypass_network} -j ACCEPT`)
      if (values.subscriber_network && values.public_ip_mode === 'single' && values.public_ip_start) lines.push(`-A POSTROUTING -s ${values.subscriber_network} -j SNAT --to-source ${values.public_ip_start}`)
      if (values.subscriber_network && values.public_ip_mode === 'range' && values.public_ip_start && values.public_ip_end) lines.push(`-A POSTROUTING -s ${values.subscriber_network} -j SNAT --to-source ${values.public_ip_start}-${values.public_ip_end}`)
      if (values.subscriber_network && values.public_ip_mode === 'masquerade') lines.push(`-A POSTROUTING -s ${values.subscriber_network} -j MASQUERADE`)
    }
    setPreviewTitle(title)
    setPreview(lines.length ? lines.join('\n') : '')
  }
  const previewCgnat = () => previewRecord('New CGNAT rule preview', { ...cgnatForm, kind: 'cgnat' })
  const previewForwarding = () => previewRecord('New forwarding rule preview', { ...forwardingForm, kind: 'forwarding' })

  const saveAccel = async () => {
    setAccelSaving(true)
    try { const response = await notify.promise(apiRequest<{ data: { values: AccelValues } }>(`/bngs/${publicId}/accel-ppp-config`, { method: 'PUT', body: JSON.stringify(accel) }, token), { loading: 'Saving Accel-PPP configuration…', success: 'Accel-PPP configuration saved.', error: 'Unable to save Accel-PPP configuration.' }); setAccel({ ...emptyAccel(), ...response.data.values, name: 'BNG' }) }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save Accel-PPP configuration.')) }
    finally { setAccelSaving(false) }
  }

  const saveCgnat = async () => {
    setCgnatSaving(true)
    const payload = { ...cgnatForm, status: 'draft', public_ip_end: cgnatForm.public_ip_mode === 'range' ? cgnatForm.public_ip_end : '', public_ip_start: cgnatForm.public_ip_mode === 'masquerade' ? '' : cgnatForm.public_ip_start }
    try {
      await notify.promise(apiRequest(`/bngs/${publicId}/cgnat-policies`, { method: 'POST', body: JSON.stringify(payload) }, token), { loading: 'Saving CGNAT policy...', success: 'CGNAT policy added.', error: 'Unable to save CGNAT policy.' })
      await notify.promise(apiRequest(`/bngs/${publicId}/iptables/save`, { method: 'POST', body: JSON.stringify({ ...payload, kind: 'cgnat', egress_interface: egressInterface }) }, token), { loading: 'Pushing rules.v4 to the BNG...', success: 'iptables rules saved to the BNG.', error: 'Policy saved, but the BNG rules file could not be updated.' })
      setCgnatModalOpen(false)
      setCgnatForm(emptyCgnat())
      await loadCgnat()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save CGNAT policy.')) }
    finally { setCgnatSaving(false) }
  }

  const openForwardingModal = () => {
    setForwardingForm(emptyForwarding())
    setForwardingModalOpen(true)
    void loadForwarding()
  }

  const saveForwarding = async () => {
    setForwardingSaving(true)
    try {
      await notify.promise(apiRequest(`/bngs/${publicId}/forwarding-rules`, { method: 'POST', body: JSON.stringify({ ...forwardingForm, status: 'draft' }) }, token), { loading: 'Saving forwarding rule...', success: 'Forwarding rule added.', error: 'Unable to save forwarding rule.' })
      await notify.promise(apiRequest(`/bngs/${publicId}/iptables/save`, { method: 'POST', body: JSON.stringify({ ...forwardingForm, kind: 'forwarding' }) }, token), { loading: 'Pushing rules.v4 to the BNG...', success: 'iptables rules saved to the BNG.', error: 'Rule saved, but the BNG rules file could not be updated.' })
      setForwardingForm(emptyForwarding())
      await loadForwarding()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save forwarding rule.')) }
    finally { setForwardingSaving(false) }
  }

  const removeForwarding = async (rule: ForwardingRule) => {
    try {
      await notify.promise(apiRequest(`/bngs/${publicId}/forwarding-rules/${rule.public_id}`, { method: 'DELETE' }, token), { loading: 'Removing forwarding rule...', success: 'Forwarding rule removed.', error: 'Unable to remove forwarding rule.' })
      await loadForwarding()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to remove forwarding rule.')) }
  }

  const testRadiusDatabase = async () => { setRadiusTesting(true); try { await notify.promise(apiRequest(`/bngs/${publicId}/radius-servers/test-connection`, { method: 'POST', body: JSON.stringify({ server_address: radiusForm.server_address, database_name: radiusForm.database_name, database_username: radiusForm.database_username, database_password: radiusForm.database_password }) }, token), { loading: 'Testing database connection...', success: 'Database connection successful.', error: 'Database connection failed.' }) } catch (exception) { notify.error(getErrorMessage(exception, 'Database connection failed.')) } finally { setRadiusTesting(false) } }
  const openRadius = (server?: RadiusServer) => { setEditingRadius(server?.public_id || null); setRadiusForm(server ? { name: server.name, server_address: server.server_address, secret: '', database_name: server.database_name, database_username: server.database_username, database_password: '', auth_port: String(server.auth_port), accounting_port: String(server.accounting_port), notes: server.notes || '' } : emptyRadius()); setRadiusModalOpen(true) }
  const saveRadius = async () => { setRadiusSaving(true); try { const path = editingRadius ? `/bngs/${publicId}/radius-servers/${editingRadius}` : `/bngs/${publicId}/radius-servers`; const method = editingRadius ? 'PATCH' : 'POST'; await notify.promise(apiRequest(path, { method, body: JSON.stringify({ ...radiusForm, status: 'draft', auth_port: Number(radiusForm.auth_port), accounting_port: Number(radiusForm.accounting_port) }) }, token), { loading: editingRadius ? 'Updating RADIUS server...' : 'Saving RADIUS server...', success: editingRadius ? 'RADIUS server updated.' : 'RADIUS server added.', error: 'Unable to save RADIUS server.' }); setRadiusModalOpen(false); setEditingRadius(null); setRadiusForm(emptyRadius()); await loadRadius() } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save RADIUS server.')) } finally { setRadiusSaving(false) } }
  const removeRadius = async (server: RadiusServer) => { try { await notify.promise(apiRequest(`/bngs/${publicId}/radius-servers/${server.public_id}`, { method: 'DELETE' }, token), { loading: 'Removing RADIUS server...', success: 'RADIUS server removed.', error: 'Unable to remove RADIUS server.' }); await loadRadius() } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to remove RADIUS server.')) } }

  const removeCgnat = async (policy: CgnatPolicy) => {
    try {
      await notify.promise(apiRequest(`/bngs/${publicId}/cgnat-policies/${policy.public_id}`, { method: 'DELETE' }, token), { loading: 'Removing CGNAT policy...', success: 'CGNAT policy removed.', error: 'Unable to remove CGNAT policy.' })
      await loadCgnat()
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to remove CGNAT policy.')) }
  }

  useEffect(() => { void load() }, [publicId, token])

  if (loading) return <div className="grid min-h-56 place-items-center text-sm text-muted-foreground">Loading BNG management…</div>
  if (!bng) return <div className="flex flex-col gap-4"><Button variant="ghost" className="w-fit" onClick={onBack}><ArrowLeftIcon data-icon="inline-start" />Back to BNGs</Button><p className="py-12 text-center text-sm text-muted-foreground">BNG not found.</p></div>

  const active = ['connecting', 'connected', 'disconnected'].includes(sessionStatus)
  const selectTab = (tab: ManagementTab) => { setActiveTab(tab); if (tab === 'accel_ppp' && !accelLoaded && sessionStatus === 'connected') void loadAccel(); if (tab === 'cgnat') void loadCgnat(); if (tab === 'radius') void loadRadius() }
  const driver = bngDrivers[bng.vendor] || { label: `${bng.vendor} BNG driver`, serviceTabs: [] }
  const tabContent = activeTab === 'ssh' ? <div className="grid gap-4 sm:grid-cols-2"><div className="border border-border/70 bg-background/40 p-4"><p className="text-xs text-muted-foreground">Management endpoint</p><p className="mt-1 text-sm font-medium">{bng.management_endpoint || '-'}</p></div><div className="border border-border/70 bg-background/40 p-4"><p className="text-xs text-muted-foreground">Preferred transport</p><p className="mt-1 text-sm font-medium uppercase">{bng.preferred_transport || 'ssh'}</p></div><div className="flex items-center justify-between border border-border/70 bg-background/40 p-4 sm:col-span-2"><div><p className="text-sm font-medium">Persistent SSH session</p><p className="mt-1 text-xs text-muted-foreground">The session automatically reconnects until it is stopped manually.</p></div><Button type="button" variant={active ? 'destructive' : 'default'} disabled={working} onClick={() => void toggleSession}>{working ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Working…</> : active ? <><StopIcon data-icon="inline-start" />Stop</> : <><PlugIcon data-icon="inline-start" />Connect</>}</Button></div></div>
    : activeTab === 'accel_ppp' ? <AccelPppForm values={accel} radiusServers={radiusRows} loading={accelLoading} saving={accelSaving} onChange={updateAccel} onRadiusSelect={server => setAccel(current => ({ ...current, radius_server: server?.server_address || '', radius_secret: server?.secret || '', auth_port: server ? String(server.auth_port) : '', acct_port: server ? String(server.accounting_port) : '' }))} onPreview={() => void previewAccel()} onSave={() => void saveAccel()} />
      : activeTab === 'cgnat' ? <CgnatPolicies policies={cgnatRows} loading={cgnatLoading} onAdd={() => setCgnatModalOpen(true)} onPreview={previewWholeIptables} onForwarding={openForwardingModal} onRemove={policy => void removeCgnat(policy)} />
      : activeTab === 'radius' ? <RadiusServers servers={radiusRows} loading={radiusLoading} onAdd={() => openRadius()} onEdit={openRadius} onRemove={server => void removeRadius(server)} />
      : activeTab === 'bng_interfaces' ? <div className="grid gap-3 border border-border/70 bg-background/40 p-4 sm:grid-cols-2"><label className="grid gap-1.5 text-xs font-medium" htmlFor="bng-parent-interface">BNG Parent Interface<Input id="bng-parent-interface" value={parentInterface} onChange={event => setParentInterface(event.target.value)} placeholder="ens17" /></label><label className="grid gap-1.5 text-xs font-medium" htmlFor="preferred-egress-interface">Preferred Egress Interface<Input id="preferred-egress-interface" value={egressInterface} onChange={event => setEgressInterface(event.target.value)} placeholder="ens16" /></label><div className="flex justify-end border-t border-border/70 pt-4 sm:col-span-2"><Button type="button" disabled={savingInterfaces || !parentInterface.trim() || !egressInterface.trim()} onClick={() => void saveInterfaces}>{savingInterfaces ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : 'Save interfaces'}</Button></div></div>
      : <div className="border border-border/70 bg-background/40 p-5"><p className="text-sm font-medium">{driver.serviceTabs.find(tab => tab.value === activeTab)?.label} configuration</p><p className="mt-1 text-xs text-muted-foreground">{driver.serviceTabs.find(tab => tab.value === activeTab)?.description || 'This service is not available for the selected BNG driver.'}</p></div>

  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex flex-wrap items-end justify-between gap-4"><div><p className="billing-eyebrow">Network / BNG management</p><h1 className="mt-2 text-xl font-semibold">{bng.name}</h1><p className="mt-1 text-sm text-muted-foreground">{bng.vendor} {bng.model || 'BNG'} · {bng.management_endpoint || 'No endpoint'} · {bng.preferred_transport || 'SSH'}</p></div><div className="flex items-center gap-2"><Badge variant="outline">{sessionStatus}</Badge><Button variant="outline" onClick={onBack}><ArrowLeftIcon data-icon="inline-start" />Back to BNGs</Button></div></div><Card className="billing-records overflow-hidden"><CardHeader><CardTitle>BNG management</CardTitle><CardDescription>{driver.label}. Manage the persistent SSH session and broadband access services.</CardDescription></CardHeader><CardContent><div className="mb-5 flex w-fit flex-wrap items-center gap-1 rounded-lg border border-border/70 bg-muted/40 p-1" role="tablist" aria-label="BNG management sections"><button type="button" role="tab" aria-selected={activeTab === 'ssh'} onClick={() => selectTab('ssh')} className={`rounded-md px-4 py-2 text-xs font-medium transition-colors ${activeTab === 'ssh' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>SSH session</button>{driver.serviceTabs.map(tab => <button type="button" role="tab" aria-selected={activeTab === tab.value} key={tab.value} onClick={() => selectTab(tab.value)} className={`rounded-md px-4 py-2 text-xs font-medium transition-colors ${activeTab === tab.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>{tab.label}</button>)}</div>{tabContent}</CardContent></Card>{cgnatModalOpen && <CgnatModal values={cgnatForm} saving={cgnatSaving} onChange={(key, value) => setCgnatForm(current => ({ ...current, [key]: value }))} onClose={() => setCgnatModalOpen(false)} onPreview={previewCgnat} onSubmit={() => void saveCgnat()} />}{forwardingModalOpen && <ForwardingModal values={forwardingForm} rows={forwardingRows} loading={forwardingLoading} saving={forwardingSaving} onChange={(key, value) => setForwardingForm(current => ({ ...current, [key]: value }))} onRemove={rule => void removeForwarding(rule)} onClose={() => setForwardingModalOpen(false)} onPreview={previewForwarding} onSubmit={() => void saveForwarding()} />}{radiusModalOpen && <RadiusModal values={radiusForm} editing={Boolean(editingRadius)} saving={radiusSaving} testing={radiusTesting} onChange={(key, value) => setRadiusForm(current => ({ ...current, [key]: value }))} onClose={() => setRadiusModalOpen(false)} onTest={() => void testRadiusDatabase()} onSubmit={() => void saveRadius()} />}{preview !== null && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true"><Card className="billing-modal-card relative w-full max-w-5xl shadow-2xl"><CardHeader className="border-b pr-14"><CardTitle>{previewTitle}</CardTitle><CardDescription>Review the commands or configuration before sending it to the BNG server.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={() => setPreview(null)} aria-label="Close preview"><XIcon /></Button></CardHeader><CardContent className="pt-5"><pre className="max-h-[60vh] overflow-auto border border-border/70 bg-muted/30 p-4 font-mono text-xs leading-5">{preview}</pre><div className="mt-4 flex justify-end"><Button type="button" onClick={() => setPreview(null)}>Close preview</Button></div></CardContent></Card></div>}</div>
}

function AccelPppForm({ values, radiusServers, loading, saving, onChange, onRadiusSelect, onPreview, onSave }: { values: AccelValues; radiusServers: RadiusServer[]; loading: boolean; saving: boolean; onChange: (key: keyof AccelValues, value: string) => void; onRadiusSelect: (server?: RadiusServer) => void; onPreview: () => void; onSave: () => void }) {
  const [section, setSection] = useState<'pppoe' | 'ip_pool' | 'radius' | 'dns'>('pppoe')
  const field = (key: keyof AccelValues, label: string, placeholder?: string, type = 'text', readOnly = false) => <label className="grid gap-1.5 text-xs font-medium" htmlFor={`accel-${key}`}>{label}<Input id={`accel-${key}`} type={type} value={values[key]} onChange={event => onChange(key, event.target.value)} placeholder={placeholder} readOnly={readOnly} /></label>
  if (loading) return <div className="grid min-h-40 place-items-center text-sm text-muted-foreground">Loading Accel-PPP configuration…</div>
  const radiusSelection = radiusServers.find(server => server.server_address === values.radius_server)?.public_id || ''
  const radiusSelect = <label className="grid gap-1.5 text-xs font-medium" htmlFor="accel-radius-server">RADIUS server<select id="accel-radius-server" className="h-10 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:border-ring" value={radiusSelection} onChange={event => onRadiusSelect(radiusServers.find(server => server.public_id === event.target.value))}><option value="">Select RADIUS server</option>{radiusServers.map(server => <option key={server.public_id} value={server.public_id}>{server.name} · {server.server_address}</option>)}</select></label>
  const content = section === 'pppoe' ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{field('name', 'Name', undefined, 'text', true)}{field('bras_name', 'BRAS Name', 'ISP-in-a-Box')}</div> : section === 'ip_pool' ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{field('gateway_address', 'Gateway Address', 'IP Address')}{field('pool_name', 'Pool Name', 'Pool1')}{field('pool_start', 'Pool range start', 'start-ip')}{field('pool_end', 'Pool range end', 'end-ip')}</div> : section === 'radius' ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{field('nas_ip', 'NAS IP', 'IP Address')}{field('nas_identifier', 'NAS Identifier', 'ISPinaBox')}{field('radius_gateway_address', 'Gateway Address', 'IP Address')}{radiusSelect}{field('dae_server', 'DAE Server', 'Select BNG IP')}{field('dae_port', 'DAE Port', undefined, 'number')}{field('dae_secret', 'DAE Secret')}</div> : <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{field('primary_dns', 'Primary DNS', 'IP Address')}{field('secondary_dns', 'Secondary DNS', 'IP Address')}</div>
  return <div className="grid gap-5"><div className="flex w-fit flex-wrap items-center gap-1 rounded-lg border border-border/70 bg-muted/40 p-1 sm:ml-[280px]" role="tablist" aria-label="Accel-PPP configuration sections">{([['pppoe', 'PPPoE'], ['ip_pool', 'IP pool'], ['radius', 'RADIUS'], ['dns', 'DNS']] as const).map(([value, label]) => <button type="button" role="tab" aria-selected={section === value} key={value} onClick={() => setSection(value)} className={`rounded-md px-4 py-2 text-xs font-medium transition-colors ${section === value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>{label}</button>)}</div><div>{content}</div><div className="flex justify-end gap-2 border-t border-border/70 pt-4"><Button type="button" variant="outline" disabled={saving} onClick={onPreview}><EyeIcon data-icon="inline-start" />Preview</Button><Button type="button" disabled={saving} onClick={onSave}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : <><FloppyDiskIcon data-icon="inline-start" />Save</>}</Button></div></div>
}

function CgnatPolicies({ policies, loading, onAdd, onPreview, onForwarding, onRemove }: { policies: CgnatPolicy[]; loading: boolean; onAdd: () => void; onPreview: () => void; onForwarding: () => void; onRemove: (policy: CgnatPolicy) => void }) {
  const publicPool = (policy: CgnatPolicy) => policy.public_ip_mode === 'masquerade' ? 'Use interface IP' : policy.public_ip_mode === 'range' ? `${policy.public_ip_start || '-'} to ${policy.public_ip_end || '-'}` : policy.public_ip_start || '-'

  return <section className="border border-border/70 bg-background/40 p-4">
    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2 className="text-sm font-semibold">CGNAT policies</h2>
        <p className="mt-1 text-xs text-muted-foreground">Store customer NAT pools and public address translation rules for this BNG.</p>
      </div>
      <div className="grid gap-2 sm:flex sm:items-center sm:justify-end">
        <Button type="button" variant="outline" onClick={onPreview}><EyeIcon data-icon="inline-start" />Preview rules.v4</Button>
        <Button type="button" variant="outline" onClick={onForwarding}>Forwarding rules</Button>
        <Button type="button" onClick={onAdd}><PlusIcon data-icon="inline-start" />Add CGNat</Button>
      </div>
    </div>
    <div className="overflow-x-auto border border-border/60">
      <table className="w-full min-w-[760px] text-left text-xs">
        <thead className="bg-muted/30 text-muted-foreground">
          <tr>
            <th className="px-3 py-3 font-normal">Policy name</th>
            <th className="px-3 py-3 font-normal">Customer IP range</th>
            <th className="px-3 py-3 font-normal">Public IPs</th>
            <th className="px-3 py-3 font-normal">Local bypass</th>
            <th className="px-3 py-3 text-right font-normal">Actions</th>
          </tr>
        </thead>
        <tbody>
          {loading ? <tr><td colSpan={5} className="h-32 text-center text-muted-foreground">Loading CGNAT policies...</td></tr> : policies.length ? policies.map(policy => <tr className="border-t border-border/60" key={policy.public_id}>
            <td className="px-3 py-3 font-medium">{policy.name}</td>
            <td className="px-3 py-3 font-mono text-[11px]">{policy.subscriber_network}</td>
            <td className="px-3 py-3 font-mono text-[11px]">{publicPool(policy)}</td>
            <td className="px-3 py-3 font-mono text-[11px]">{policy.local_bypass_network || '-'}</td>
            <td className="px-3 py-3 text-right"><Button type="button" variant="ghost" size="icon-sm" onClick={() => onRemove(policy)} aria-label={`Remove ${policy.name}`}><TrashIcon /></Button></td>
          </tr>) : <tr><td colSpan={5} className="h-36 text-center text-muted-foreground">No CGNAT policies yet. Add a policy to record the customer NAT pool and public IP mapping.</td></tr>}
        </tbody>
      </table>
    </div>
  </section>
}

function RadiusServers({ servers, loading, onAdd, onEdit, onRemove }: { servers: RadiusServer[]; loading: boolean; onAdd: () => void; onEdit: (server: RadiusServer) => void; onRemove: (server: RadiusServer) => void }) {
  return <section className="border border-border/70 bg-background/40 p-4">
    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 className="text-sm font-semibold">RADIUS servers</h2><p className="mt-1 text-xs text-muted-foreground">Configure authentication and accounting servers for this BNG.</p></div><Button type="button" onClick={onAdd}><PlusIcon data-icon="inline-start" />Add Radius</Button></div>
    <div className="overflow-x-auto border border-border/60"><table className="w-full min-w-[720px] text-left text-xs"><thead className="bg-muted/30 text-muted-foreground"><tr><th className="px-3 py-3 font-normal">Server name</th><th className="px-3 py-3 font-normal">Server address</th><th className="px-3 py-3 font-normal">Auth port</th><th className="px-3 py-3 font-normal">Accounting port</th><th className="px-3 py-3 font-normal">Status</th><th className="px-3 py-3 text-right font-normal">Actions</th></tr></thead><tbody>{loading ? <tr><td colSpan={6} className="h-32 text-center text-muted-foreground">Loading RADIUS servers...</td></tr> : servers.length ? servers.map(server => <tr className="border-t border-border/60" key={server.public_id}><td className="px-3 py-3 font-medium">{server.name}</td><td className="px-3 py-3 font-mono text-[11px]">{server.server_address}</td><td className="px-3 py-3">{server.auth_port}</td><td className="px-3 py-3">{server.accounting_port}</td><td className="px-3 py-3">{server.status || 'draft'}</td><td className="px-3 py-3 text-right"><Button type="button" variant="ghost" size="icon-sm" onClick={() => onEdit(server)} aria-label={`Edit ${server.name}`}><PencilSimpleIcon /></Button><Button type="button" variant="ghost" size="icon-sm" onClick={() => onRemove(server)} aria-label={`Remove ${server.name}`}><TrashIcon /></Button></td></tr>) : <tr><td colSpan={6} className="h-36 text-center text-muted-foreground">No RADIUS servers yet. Add a server to configure BNG authentication.</td></tr>}</tbody></table></div>
  </section>
}

function RadiusModal({ values, editing, saving, testing, onChange, onClose, onTest, onSubmit }: { values: RadiusForm; editing: boolean; saving: boolean; testing: boolean; onChange: (key: keyof RadiusForm, value: string) => void; onClose: () => void; onTest: () => void; onSubmit: () => void }) {
  const input = (key: keyof RadiusForm, label: string, placeholder: string, type = 'text') => <label className="grid gap-1.5 text-xs font-medium" htmlFor={`radius-${key}`}>{label}<Input id={`radius-${key}`} type={type} value={values[key]} placeholder={placeholder} required={editing ? !['secret', 'database_password', 'notes'].includes(key) : key !== 'notes'} onChange={event => onChange(key, event.target.value)} /></label>
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="radius-title"><Card className="billing-modal-card relative w-full max-w-4xl shadow-2xl"><CardHeader className="billing-modal-header border-b pr-14"><p className="billing-modal-eyebrow">{editing ? 'Update record' : 'Create record'}</p><CardTitle id="radius-title">{editing ? 'Edit RADIUS server' : 'New RADIUS server'}</CardTitle><CardDescription>Configure the authentication endpoint and its database connection.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={onClose} aria-label="Close RADIUS form"><XIcon /></Button></CardHeader><CardContent className="billing-modal-content pt-5"><form className="grid gap-4" onSubmit={event => { event.preventDefault(); onSubmit() }}><div className="grid gap-4 sm:grid-cols-2">{input('name', 'Server name', 'Primary RADIUS')}{input('server_address', 'Server address', '10.0.10.192')}{input('secret', 'Shared secret', 'Leave blank to keep current', 'password')}{input('database_name', 'Database name', 'radius')}{input('database_username', 'Database username', 'radius')}{input('database_password', 'Database password', 'Leave blank to keep current', 'password')}{input('auth_port', 'Authentication port', '1812', 'number')}{input('accounting_port', 'Accounting port', '1813', 'number')}<label className="grid gap-1.5 text-xs font-medium sm:col-span-2" htmlFor="radius-notes">Notes<textarea id="radius-notes" className="min-h-20 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values.notes} placeholder="Optional context for this server" onChange={event => onChange('notes', event.target.value)} /></label></div><div className="flex flex-wrap justify-end gap-2 border-t border-border/70 pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="button" variant="outline" disabled={testing || saving} onClick={onTest}>{testing ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Testing...</> : 'Test database'}</Button><Button type="submit" disabled={saving || testing}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving...</> : <><FloppyDiskIcon data-icon="inline-start" />{editing ? 'Save changes' : 'Add Radius'}</>}</Button></div></form></CardContent></Card></div>
}

function CgnatModal({ values, saving, onChange, onClose, onPreview, onSubmit }: { values: CgnatForm; saving: boolean; onChange: (key: keyof CgnatForm, value: string) => void; onClose: () => void; onPreview: () => void; onSubmit: () => void }) {
  const input = (key: keyof CgnatForm, label: string, placeholder: string, type = 'text', required = true) => <label className="grid gap-1.5 text-xs font-medium" htmlFor={`cgnat-${key}`}>{label}<Input id={`cgnat-${key}`} type={type} value={values[key]} placeholder={placeholder} required={required} onChange={event => onChange(key, event.target.value)} /></label>

  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="cgnat-title">
    <Card className="billing-modal-card relative w-full max-w-4xl shadow-2xl">
      <CardHeader className="billing-modal-header border-b pr-14">
        <p className="billing-modal-eyebrow">Create record</p>
        <CardTitle id="cgnat-title">New CGNAT policy</CardTitle>
        <CardDescription>Save the customer NAT range and public address pool.</CardDescription>
        <Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={onClose} aria-label="Close CGNAT form"><XIcon /></Button>
      </CardHeader>
      <CardContent className="billing-modal-content pt-5">
        <form className="grid gap-4" onSubmit={event => { event.preventDefault(); onSubmit() }}>
          <div className="grid gap-4 md:grid-cols-2">
            {input('name', 'Policy name', 'Residential pool')}
            {input('subscriber_network', 'Customer IP range', '100.64.0.0/24')}
            <label className="grid gap-1.5 text-xs font-medium" htmlFor="cgnat-public-ip-mode">Public IP option<select id="cgnat-public-ip-mode" className="h-10 w-full border border-input bg-background px-2.5 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values.public_ip_mode} onChange={event => onChange('public_ip_mode', event.target.value)}><option value="range">Use public IP range</option><option value="single">Use one public IP</option><option value="masquerade">Use internet interface IP</option></select></label>
            {values.public_ip_mode !== 'masquerade' && input('public_ip_start', values.public_ip_mode === 'range' ? 'First public IP' : 'Public IP', '126.209.31.170')}
            {values.public_ip_mode === 'range' && input('public_ip_end', 'Last public IP', '126.209.31.174')}
            {input('local_bypass_network', 'Local network bypass', '10.10.10.0/24', 'text', false)}
            <label className="grid gap-1.5 text-xs font-medium md:col-span-2" htmlFor="cgnat-notes">Notes<textarea id="cgnat-notes" className="min-h-24 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values.notes} placeholder="Optional context for this rule" onChange={event => onChange('notes', event.target.value)} /></label>
          </div>
          <div className="flex justify-end gap-2 border-t border-border/70 pt-4">
            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
            <Button type="button" variant="outline" onClick={onPreview}><EyeIcon data-icon="inline-start" />Preview</Button>
            <Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving...</> : <><PlusIcon data-icon="inline-start" />Add CGNat</>}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
}

function ForwardingModal({ values, rows, loading, saving, onChange, onRemove, onClose, onPreview, onSubmit }: { values: ForwardingForm; rows: ForwardingRule[]; loading: boolean; saving: boolean; onChange: (key: keyof ForwardingForm, value: string) => void; onRemove: (rule: ForwardingRule) => void; onClose: () => void; onPreview: () => void; onSubmit: () => void }) {
  const input = (key: keyof ForwardingForm, label: string, placeholder: string, required = true) => <label className="grid gap-1.5 text-xs font-medium" htmlFor={`forwarding-${key}`}>{label}<Input id={`forwarding-${key}`} value={values[key]} placeholder={placeholder} required={required} onChange={event => onChange(key, event.target.value)} /></label>

  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="forwarding-title">
    <Card className="billing-modal-card relative w-full max-w-4xl shadow-2xl">
      <CardHeader className="billing-modal-header border-b pr-14">
        <p className="billing-modal-eyebrow">Forwarding rules</p>
        <CardTitle id="forwarding-title">Interface forwarding</CardTitle>
        <CardDescription>Allow traffic between the customer-facing interface and the internet-facing interface.</CardDescription>
        <Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={onClose} aria-label="Close forwarding rules"><XIcon /></Button>
      </CardHeader>
      <CardContent className="billing-modal-content grid gap-5 pt-5">
        <form className="grid gap-4" onSubmit={event => { event.preventDefault(); onSubmit() }}>
          <div className="grid gap-4 md:grid-cols-2">
            {input('name', 'Rule name', 'Customer to internet')}
            {input('customer_interface', 'Customer side interface', 'Enter customer-side interface')}
            {input('internet_interface', 'Internet side interface', 'Enter internet-side interface')}
            <label className="grid gap-1.5 text-xs font-medium md:col-span-2" htmlFor="forwarding-notes">Notes<textarea id="forwarding-notes" className="min-h-20 w-full border border-input bg-background px-2.5 py-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50" value={values.notes} placeholder="Optional context for this forwarding pair" onChange={event => onChange('notes', event.target.value)} /></label>
          </div>
          <div className="flex justify-end gap-2 border-t border-border/70 pt-4">
            <Button type="button" variant="outline" onClick={onClose}>Close</Button>
            <Button type="button" variant="outline" onClick={onPreview}><EyeIcon data-icon="inline-start" />Preview</Button>
            <Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving...</> : <><PlusIcon data-icon="inline-start" />Add forwarding rule</>}</Button>
          </div>
        </form>
        <div className="border border-border/60">
          <div className="border-b border-border/60 bg-muted/20 px-3 py-2">
            <p className="text-xs font-medium">Saved forwarding rules</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[620px] text-left text-xs">
              <thead className="text-muted-foreground">
                <tr>
                  <th className="px-3 py-3 font-normal">Rule name</th>
                  <th className="px-3 py-3 font-normal">Customer side</th>
                  <th className="px-3 py-3 font-normal">Internet side</th>
                  <th className="px-3 py-3 text-right font-normal">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? <tr><td colSpan={4} className="h-24 text-center text-muted-foreground">Loading forwarding rules...</td></tr> : rows.length ? rows.map(rule => <tr className="border-t border-border/60" key={rule.public_id}>
                  <td className="px-3 py-3 font-medium">{rule.name}</td>
                  <td className="px-3 py-3 font-mono text-[11px]">{rule.customer_interface}</td>
                  <td className="px-3 py-3 font-mono text-[11px]">{rule.internet_interface}</td>
                  <td className="px-3 py-3 text-right"><Button type="button" variant="ghost" size="icon-sm" onClick={() => onRemove(rule)} aria-label={`Remove ${rule.name}`}><TrashIcon /></Button></td>
                </tr>) : <tr><td colSpan={4} className="h-24 text-center text-muted-foreground">No forwarding rules yet. Add the interface pair used by the BNG forwarding commands.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
}
