import { type ReactNode, useEffect, useMemo, useState } from 'react'
import { ArrowLeftIcon, CircleNotchIcon, PencilSimpleIcon, PlusIcon, TrashIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { hasPermission } from '../lib/usersRoles'
import { useConfirm } from './ConfirmProvider'

type Props = { token: string; publicId: string; permissions?: string[]; isSuperadmin?: boolean; onBack: () => void }
type Vlan = { id: number; vlan_id: number; name: string; service_mode: string; frame?: number | null; slot?: number | null; port_number?: number | null; port?: string | null; status: string }
type Qinq = { id: number; outer_vlan?: number | null; inner_vlan?: number | null; name: string; qinq_type?: string; service_port_id?: number | null; frame?: number | null; slot?: number | null; port_number?: number | null; ont_line_profile?: string | null; profile_id?: number | null; dba_profile_id?: number | null; status: string }
type DbaProfile = { id: number; profile_id: number; bandwidth_mbps: number; profile_name: string; status: string }
type Olt = { name: string; vendor: string; model?: string; management_endpoint?: string; preferred_transport?: string; status: string; dba_profile_start_id?: number; vlan_provisions: Vlan[]; qinq_provisions: Qinq[]; dba_profiles?: DbaProfile[] }
type QinqTab = 's_vlan' | 'c_vlan' | 'tr069_vlan' | 'dba_profile' | 'ont_line_profile'

const emptyQinq = (type: QinqTab = 's_vlan') => ({ outer_vlan: '', inner_vlan: '', name: '', qinq_type: type, service_port_id: '', frame: '0', slot: '3', port_number: '', ont_line_profile: '', profile_id: '', dba_profile_id: '', port: '' })
const emptyTr069Vlan = () => ({ vlan_id: '', name: '', service_mode: 'tr069', frame: '0', slot: '3', port_number: '0', port: '0/3 0' })
const Field = ({ label, children }: { label: string; children: ReactNode }) => <label className="flex w-full max-w-none flex-col gap-1 text-xs [&>input]:bg-background [&>select]:bg-background"><span className="font-medium text-muted-foreground">{label}</span>{children}</label>

export function OltManagementPage({ token, publicId, permissions, isSuperadmin, onBack }: Props) {
  const [olt, setOlt] = useState<Olt | null>(null)
  const [error, setError] = useState('')
  const [vlan, setVlan] = useState({ vlan_id: '', name: '', service_mode: 'internet' })
  const [tr069Vlan, setTr069Vlan] = useState(emptyTr069Vlan())
  const [qinq, setQinq] = useState(emptyQinq())
  const [dbaOpen, setDbaOpen] = useState(false)
  const [dbaSpeed, setDbaSpeed] = useState('')
  const [dbaSettingOpen, setDbaSettingOpen] = useState(false)
  const [dbaStartId, setDbaStartId] = useState('10')
  const [activeTab, setActiveTab] = useState<'vlan' | 'qinq'>('vlan')
  const [qinqTab, setQinqTab] = useState<QinqTab>('s_vlan')
  const [editingVlanId, setEditingVlanId] = useState<number | null>(null)
  const [editingTr069Id, setEditingTr069Id] = useState<number | null>(null)
  const [editingQinqId, setEditingQinqId] = useState<number | null>(null)
  const [provisionModalOpen, setProvisionModalOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const confirm = useConfirm()
  const canProvision = isSuperadmin !== false || hasPermission(permissions, 'olts.provision')

  const load = async () => {
    try {
      const response = await apiRequest<{ data: Olt }>(`/olts/${publicId}/management`, {}, token)
      setOlt(response.data)
      setDbaStartId(String(response.data.dba_profile_start_id || 10))
      setError('')
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to load OLT management data.'))
    }
  }

  useEffect(() => { void load() }, [publicId, token])

  const add = async (path: string, values: object, reset: () => void, message: string, method: 'POST' | 'PUT' = 'POST') => {
    setSaving(true)
    try {
      const response = await apiRequest<{ operation?: { message?: string } }>(path, { method, body: JSON.stringify(values) }, token)
      reset()
      setProvisionModalOpen(false)
      notify.success(response.operation?.message || message)
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to save provisioning record.'))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (path: string) => {
    try {
      await apiRequest(path, { method: 'DELETE' }, token)
      notify.success('Provisioning record deleted.')
      await load()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to delete provisioning record.'))
    }
  }

  const confirmRemove = async (path: string, label: string) => {
    const accepted = await confirm({ title: `Delete ${label}?`, description: 'This will remove the provisioning record and attempt to undo its configuration on the OLT. This action cannot be undone.', confirmLabel: 'Delete permanently', destructive: true })
    if (accepted) await remove(path)
  }

  const visibleVlans = useMemo(() => olt?.vlan_provisions.filter(item => item.service_mode !== 'tr069') || [], [olt])
  const tr069Records = useMemo(() => olt?.vlan_provisions.filter(item => item.service_mode === 'tr069') || [], [olt])
  const cVlans = useMemo(() => olt?.qinq_provisions.filter(item => item.qinq_type === 'c_vlan') || [], [olt])
  const occupiedPorts = useMemo(() => new Set([
    ...(olt?.qinq_provisions.filter(item => item.qinq_type === 's_vlan' && item.slot !== null && item.port_number !== null).map(item => `${item.slot}:${item.port_number}`) || []),
    ...(tr069Records.filter(item => item.slot !== null && item.port_number !== null).map(item => `${item.slot}:${item.port_number}`)),
  ]), [olt, tr069Records])
  const visibleQinq = useMemo(() => olt?.qinq_provisions.filter(item => (item.qinq_type || 's_vlan') === qinqTab) || [], [olt, qinqTab])
  const nextProfileId = useMemo(() => Math.max(9, ...(olt?.qinq_provisions.filter(item => item.qinq_type === 'ont_line_profile').map(item => item.profile_id || 0) || [])) + 1, [olt])
  const nextServicePortId = useMemo(() => Math.min(9000, Math.max(999, ...(olt?.qinq_provisions.filter(item => item.qinq_type === 's_vlan').map(item => item.service_port_id || 0) || [])) + 1), [olt])
  const nextDbaId = useMemo(() => Math.max(Number(dbaStartId) || 10, Math.max(5, ...(olt?.dba_profiles?.map(item => item.profile_id) || [])) + 5), [olt, dbaStartId])
  const dbaName = dbaSpeed ? `DBA_${dbaSpeed}MBPS` : ''
  const saveDbaStartId = async () => {
    const value = Number(dbaStartId)
    if (!Number.isInteger(value) || value < 10 || value > 515 || value % 5 !== 0) {
      notify.error('DBA profile starting ID must be a multiple of 5 between 10 and 515.')
      return
    }
    try {
      await apiRequest(`/olts/${publicId}/dba-profile-settings`, { method: 'PATCH', body: JSON.stringify({ dba_profile_start_id: value }) }, token)
      notify.success('DBA profile starting ID saved.')
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to save DBA profile starting ID.'))
    }
  }
  const profileName = qinq.outer_vlan ? `LP_CVLAN_${qinq.outer_vlan}` : ''
  const portIsAvailable = (slot: string, port: string, currentKey?: string) => !occupiedPorts.has(`${slot}:${port}`) || currentKey === `${slot}:${port}`
  const availablePorts = (slot: string, currentKey?: string) => [0, 1, 2, 3].filter(port => portIsAvailable(slot, String(port), currentKey))

  const setQinqType = (type: QinqTab) => {
    setQinqTab(type)
    setQinq(current => ({ ...current, qinq_type: type }))
    setEditingQinqId(null)
  }

  const qinqPayload = () => {
    const values: Record<string, string | number | null> = {
      qinq_type: qinqTab,
      name: qinqTab === 'ont_line_profile' ? profileName : qinq.name,
      outer_vlan: qinqTab === 's_vlan' || qinqTab === 'ont_line_profile' ? (qinq.outer_vlan || null) : null,
      inner_vlan: qinqTab === 'c_vlan' || qinqTab === 'ont_line_profile' ? (qinq.inner_vlan || null) : null,
    }
    if (qinqTab === 's_vlan') Object.assign(values, { frame: 0, slot: Number(qinq.slot), port_number: Number(qinq.port_number), port: qinq.port })
    if (qinqTab === 'ont_line_profile') values.dba_profile_id = Number(qinq.dba_profile_id)
    return values
  }

  const list = (items: Array<Vlan | Qinq>, kind: 'vlan' | 'qinq') => <div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead><tr className="border-b text-muted-foreground"><th className="px-2 py-2 font-medium">Type</th><th className="px-2 py-2 font-medium">VLAN / profile</th><th className="px-2 py-2 font-medium">Name</th><th className="px-2 py-2 font-medium">Status</th><th className="px-2 py-2 text-right font-medium">Actions</th></tr></thead><tbody>{items.length ? items.map(item => <tr className="border-b last:border-0" key={item.id}><td className="px-2 py-3">{kind === 'vlan' ? ((item as Vlan).service_mode === 'tr069' ? 'TR-069 VLAN' : 'VLAN') : ((item as Qinq).qinq_type === 'c_vlan' ? 'C-VLAN' : (item as Qinq).qinq_type === 'ont_line_profile' ? 'ONT line profile' : 'S-VLAN')}</td><td className="px-2 py-3 text-muted-foreground">{kind === 'vlan' ? `VLAN ${(item as Vlan).vlan_id}` : `${(item as Qinq).outer_vlan ? `S ${(item as Qinq).outer_vlan}` : ''}${(item as Qinq).inner_vlan ? ` C ${(item as Qinq).inner_vlan}` : ''}${(item as Qinq).service_port_id ? ` · SP ${(item as Qinq).service_port_id}` : ''}${(item as Qinq).profile_id ? ` · ID ${(item as Qinq).profile_id}` : ''}${(item as Qinq).ont_line_profile ? ` · ${(item as Qinq).ont_line_profile}` : ''}`}</td><td className="px-2 py-3 font-medium">{item.name}</td><td className="px-2 py-3"><Badge variant="outline">{item.status}</Badge></td><td className="px-2 py-3"><div className="flex justify-end gap-1">{canProvision && <><Button variant="ghost" size="icon-sm" aria-label={`Edit ${kind} ${item.name}`} onClick={() => { if (kind === 'vlan') { const row = item as Vlan; if (row.service_mode === 'tr069') { setTr069Vlan({ vlan_id: String(row.vlan_id), name: row.name, service_mode: 'tr069', frame: String(row.frame ?? 0), slot: String(row.slot ?? 3), port_number: String(row.port_number ?? 0), port: row.port || `0/${row.slot ?? 3} ${row.port_number ?? 0}` }); setEditingTr069Id(row.id); setQinqTab('tr069_vlan'); setActiveTab('qinq'); setProvisionModalOpen(true) } else { setVlan({ vlan_id: String(row.vlan_id), name: row.name, service_mode: row.service_mode }); setEditingVlanId(row.id); setActiveTab('vlan'); setProvisionModalOpen(true) } } else { const row = item as Qinq; setQinq({ ...emptyQinq((row.qinq_type || 's_vlan') as QinqTab), outer_vlan: String(row.outer_vlan || ''), inner_vlan: String(row.inner_vlan || ''), name: row.name, frame: String(row.frame ?? 0), slot: String(row.slot || 0), port_number: String(row.port_number || 0), ont_line_profile: row.ont_line_profile || '', profile_id: String(row.profile_id || ''), dba_profile_id: String(row.dba_profile_id || ''), port: `0/${row.slot || 0} ${row.port_number || 0}` }); setQinqTab((row.qinq_type || 's_vlan') as QinqTab); setEditingQinqId(row.id); setActiveTab('qinq'); setProvisionModalOpen(true) } }}><PencilSimpleIcon /></Button><Button variant="ghost" size="icon-sm" aria-label={`Delete ${kind} ${item.name}`} onClick={() => void confirmRemove(`/olts/${publicId}/${kind === 'vlan' ? 'vlans' : 'qinq'}/${item.id}`, `${kind === 'vlan' ? 'VLAN' : 'QinQ'} ${item.name}`)}><TrashIcon /></Button></>}</div></td></tr>) : <tr><td colSpan={5} className="py-10 text-center text-xs text-muted-foreground">No {kind === 'vlan' ? 'VLAN' : 'QinQ'} provisioning records.</td></tr>}</tbody></table></div>

  if (!olt) return <div className="flex flex-col gap-4"><Button variant="ghost" className="w-fit" onClick={onBack}><ArrowLeftIcon data-icon="inline-start" />Back to OLTs</Button>{error ? <div className="border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{error}</div> : <p className="py-12 text-center text-sm text-muted-foreground">Loading OLT management…</p>}</div>

  return <div className="flex flex-col gap-5">

    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4"><div><p className="billing-eyebrow">Network / OLT management</p><h1 className="mt-2 text-xl font-semibold">{olt.name}</h1><p className="mt-1 text-sm text-muted-foreground">{olt.vendor} {olt.model || 'OLT'} · {olt.management_endpoint || 'No endpoint'} · {olt.preferred_transport || 'SSH'}</p></div><div className="flex items-center gap-2"><Badge variant="outline">{olt.status}</Badge><Button variant="outline" onClick={onBack}><ArrowLeftIcon data-icon="inline-start" />Back to OLTs</Button></div></div>

    <Card className="billing-records overflow-hidden"><CardHeader><div className="flex flex-wrap items-start justify-between gap-3"><div><CardTitle>Provisioning</CardTitle><CardDescription>Configure service VLANs and QinQ mappings for this OLT.</CardDescription></div><span className="billing-eyebrow">{olt.vlan_provisions.length + olt.qinq_provisions.length} records</span></div></CardHeader><CardContent className="p-5">
      <div className="mb-5 flex w-fit items-center gap-1 rounded-lg border border-border/70 bg-muted/40 p-1" role="tablist" aria-label="OLT provisioning types"><button type="button" role="tab" aria-selected={activeTab === 'vlan'} onClick={() => setActiveTab('vlan')} className={`rounded-md px-4 py-2 text-sm font-medium ${activeTab === 'vlan' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>VLAN provisioning <span className="ml-1 text-xs text-muted-foreground">{visibleVlans.length}</span></button><button type="button" role="tab" aria-selected={activeTab === 'qinq'} onClick={() => setActiveTab('qinq')} className={`rounded-md px-4 py-2 text-sm font-medium ${activeTab === 'qinq' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>QinQ provisioning <span className="ml-1 text-xs text-muted-foreground">{olt.qinq_provisions.length}</span></button></div>
      {activeTab === 'vlan' && <section className="rounded-lg border border-border/70 bg-background/40 p-4"><div className="mb-4 flex items-start justify-between gap-3"><div><h2 className="text-sm font-semibold">VLAN provisioning</h2><p className="mt-1 text-xs text-muted-foreground">Define service VLANs for this OLT.</p></div>{canProvision && qinqTab !== 'dba_profile' && <Button type="button" onClick={() => { setEditingVlanId(null); setProvisionModalOpen(true) }}><PlusIcon data-icon="inline-start" />New VLAN</Button>}</div>{list(visibleVlans, 'vlan')}{provisionModalOpen && activeTab === 'vlan' && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="vlan-provisioning-title"><Card className="billing-modal-card relative w-full max-w-lg shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{editingVlanId ? 'Update record' : 'Create record'}</p><CardTitle id="vlan-provisioning-title">{editingVlanId ? 'Edit VLAN' : 'New VLAN'}</CardTitle><CardDescription>Define a service VLAN for this OLT.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={() => setProvisionModalOpen(false)} aria-label="Close VLAN form"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form className="grid gap-3" onSubmit={e => { e.preventDefault(); void add(`/olts/${publicId}/vlans${editingVlanId ? `/${editingVlanId}` : ''}`, vlan, () => { setVlan({ vlan_id: '', name: '', service_mode: 'internet' }); setEditingVlanId(null) }, editingVlanId ? 'VLAN provisioning record updated.' : 'VLAN provisioning record added.', editingVlanId ? 'PUT' : 'POST') }}><Input type="number" min="1" max="4094" placeholder="VLAN ID" aria-label="VLAN ID" value={vlan.vlan_id} onChange={e => setVlan({ ...vlan, vlan_id: e.target.value })} required /><Input placeholder="Name" aria-label="VLAN name" value={vlan.name} onChange={e => setVlan({ ...vlan, name: e.target.value })} required /><Input placeholder="Service mode" aria-label="Service mode" value={vlan.service_mode} onChange={e => setVlan({ ...vlan, service_mode: e.target.value })} required /><div className="mt-3 flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={() => setProvisionModalOpen(false)}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Creating…</> : editingVlanId ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Create VLAN</>}</Button></div></form></CardContent></Card></div>}</section>}
      {activeTab === 'qinq' && <section className="rounded-lg border border-border/70 bg-background/40 p-4"><div className="mb-4"><h2 className="text-sm font-semibold">QinQ provisioning</h2><p className="mt-1 text-xs text-muted-foreground">Define service VLAN mappings and ONT line profiles.</p></div><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div className="flex w-fit flex-wrap items-center gap-1 rounded-lg border border-border/70 bg-muted/40 p-1" role="tablist" aria-label="QinQ provisioning types">{([['s_vlan', 'S-VLAN'], ['c_vlan', 'C-VLAN'], ['tr069_vlan', 'TR-069 VLAN'], ['dba_profile', 'DBA Profiles'], ['ont_line_profile', 'ONT line profile']] as const).map(([value, label]) => <button type="button" role="tab" aria-selected={qinqTab === value} key={value} onClick={() => setQinqType(value)} className={`rounded-md px-3 py-2 text-xs font-medium ${qinqTab === value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>{label}{value === 'tr069_vlan' && <span className="ml-1 text-muted-foreground">{tr069Records.length}</span>}{value === 'dba_profile' && <span className="ml-1 text-muted-foreground">{olt.dba_profiles?.length || 0}</span>}</button>)}</div><div className="flex items-center gap-2">{canProvision && qinqTab === 'dba_profile' && <div className="flex items-center gap-2"><Button type="button" variant="outline" className="h-8 px-3 text-xs" onClick={() => setDbaSettingOpen(current => !current)} aria-expanded={dbaSettingOpen}>Set starting ID</Button>{dbaSettingOpen && <Input className="w-24" type="number" min="10" max="515" step="5" value={dbaStartId} onChange={e => setDbaStartId(e.target.value)} onBlur={() => void saveDbaStartId()} onKeyDown={e => { if (e.key === 'Enter') void saveDbaStartId() }} aria-label="DBA profile starting ID" />}</div>}{canProvision && <Button type="button" onClick={() => { if (qinqTab === 'dba_profile') { setDbaSpeed(''); setDbaOpen(true) } else { setEditingQinqId(null); setEditingTr069Id(null); setProvisionModalOpen(true) } }}><PlusIcon data-icon="inline-start" />New {qinqTab === 's_vlan' ? 'S-VLAN' : qinqTab === 'c_vlan' ? 'C-VLAN' : qinqTab === 'tr069_vlan' ? 'TR-069 VLAN' : qinqTab === 'dba_profile' ? 'DBA profile' : 'line profile'}</Button>}</div></div>
        {qinqTab === 'dba_profile' && <><div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead><tr className="border-b text-muted-foreground"><th className="px-2 py-2 font-normal">Profile ID</th><th className="px-2 py-2 font-normal">Profile name</th><th className="px-2 py-2 font-normal">Bandwidth</th><th className="px-2 py-2 font-normal">Status</th><th className="px-2 py-2 text-right">Actions</th></tr></thead><tbody>{olt.dba_profiles?.length ? olt.dba_profiles.map(profile => <tr className="border-b" key={profile.id}><td className="px-2 py-3">DBA profile</td><td className="px-2 py-3 text-muted-foreground">ID {profile.profile_id}</td><td className="px-2 py-3">{profile.profile_name} · {profile.bandwidth_mbps} Mbps</td><td className="px-2 py-3"><Badge variant="outline">{profile.status}</Badge></td><td className="px-2 py-3 text-right"><Button variant="ghost" size="icon-sm" onClick={() => void confirmRemove(`/olts/${publicId}/dba-profiles/${profile.id}`, `DBA profile ${profile.profile_name}`)}><TrashIcon /></Button></td></tr>) : <tr><td colSpan={5} className="py-8 text-center">No DBA profiles configured.</td></tr>}</tbody></table></div>{dbaOpen && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true"><Card className="billing-modal-card relative w-full max-w-lg"><CardHeader><CardTitle>New DBA profile</CardTitle><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={() => setDbaOpen(false)}><XIcon /></Button></CardHeader><CardContent><form className="grid gap-3 sm:grid-cols-2" onSubmit={e => { e.preventDefault(); void add(`/olts/${publicId}/dba-profiles`, { profile_id: nextDbaId, bandwidth_mbps: Number(dbaSpeed), profile_name: dbaName }, () => setDbaSpeed(''), 'DBA profile created.') }}><Field label="Profile-ID"><Input value={String(nextDbaId)} disabled readOnly /></Field><Field label="Bandwidth speed (Mbps)"><Input type="number" min="1" value={dbaSpeed} onChange={e => setDbaSpeed(e.target.value)} required /></Field><Field label="Profile name"><Input value={dbaName} disabled readOnly /></Field><div className="flex justify-end gap-2 border-t pt-4 sm:col-span-2"><Button type="button" variant="outline" onClick={() => setDbaOpen(false)}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Pushing to OLT…</> : <><PlusIcon data-icon="inline-start" />Create DBA profile</>}</Button></div></form></CardContent></Card></div>}</>}
        {provisionModalOpen && activeTab === 'qinq' && qinqTab !== 'dba_profile' && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="qinq-provisioning-title"><Card className="billing-modal-card relative w-full max-w-5xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{editingQinqId || editingTr069Id ? 'Update record' : 'Create record'}</p><CardTitle id="qinq-provisioning-title">{editingQinqId || editingTr069Id ? 'Edit provisioning record' : `New ${qinqTab === 's_vlan' ? 'S-VLAN' : qinqTab === 'c_vlan' ? 'C-VLAN' : qinqTab === 'tr069_vlan' ? 'TR-069 VLAN' : 'ONT line profile'}`}</CardTitle><CardDescription>Configure this provisioning record for the selected OLT.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={() => setProvisionModalOpen(false)} aria-label="Close provisioning form"><XIcon /></Button></CardHeader><CardContent className="pt-5">
        {qinqTab === 'tr069_vlan' && canProvision && <form className="mb-4 olt-provisioning-form" onSubmit={e => { e.preventDefault(); void add(`/olts/${publicId}/vlans${editingTr069Id ? `/${editingTr069Id}` : ''}`, tr069Vlan, () => { setTr069Vlan(emptyTr069Vlan()); setEditingTr069Id(null) }, editingTr069Id ? 'TR-069 VLAN updated.' : 'TR-069 VLAN added.', editingTr069Id ? 'PUT' : 'POST') }}><Field label="Frame"><Input aria-label="Frame" value="0 - Display board 0" readOnly /></Field><Field label="Slot"><select aria-label="TR-069 VLAN slot ID" value={tr069Vlan.slot} onChange={e => setTr069Vlan({ ...tr069Vlan, slot: e.target.value, port: `0/${e.target.value} ${tr069Vlan.port_number}` })} className="h-9 border border-input bg-background px-3 text-xs"><option value="3">3 - H901MPSA (CPCA) · Active</option><option value="4" disabled>4 - H901MPSA (CPCA) · Offline</option></select></Field><Field label="Port"><select aria-label="TR-069 VLAN GE port" value={tr069Vlan.port_number} onChange={e => setTr069Vlan({ ...tr069Vlan, port_number: e.target.value, port: `0/${tr069Vlan.slot} ${e.target.value}` })} className="h-9 border border-input bg-background px-3 text-xs"><option value="">Select GE port</option>{availablePorts(tr069Vlan.slot, editingTr069Id ? `${tr069Vlan.slot}:${tr069Vlan.port_number}` : undefined).map(port => <option key={port} value={port}>GE{port}</option>)}</select></Field><Field label="TR-069 VLAN"><Input type="number" min="1" max="4094" placeholder="VLAN ID" aria-label="TR-069 VLAN" value={tr069Vlan.vlan_id} onChange={e => setTr069Vlan({ ...tr069Vlan, vlan_id: e.target.value })} required /></Field><Field label="Name"><Input placeholder="Name" aria-label="TR-069 VLAN name" value={tr069Vlan.name} onChange={e => setTr069Vlan({ ...tr069Vlan, name: e.target.value })} required /></Field><Button type="submit" className="w-fit self-end" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : editingTr069Id ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Add</>}</Button></form>}
        {!( ['tr069_vlan', 'dba_profile'] as QinqTab[] ).includes(qinqTab) && canProvision && <form className={`mb-4 olt-provisioning-form ${qinqTab === 's_vlan' ? 'olt-provisioning-form-s-vlan' : ''}`} onSubmit={e => { e.preventDefault(); void add(`/olts/${publicId}/qinq${editingQinqId ? `/${editingQinqId}` : ''}`, qinqPayload(), () => { setQinq(emptyQinq(qinqTab as Exclude<QinqTab, 'dba_profile'>)); setEditingQinqId(null) }, editingQinqId ? 'QinQ provisioning record updated.' : 'QinQ provisioning record added.', editingQinqId ? 'PUT' : 'POST') }}>
          {qinqTab === 's_vlan' && <><Field label="Service-port ID"><Input aria-label="Service-port ID" value={String(qinq.service_port_id || nextServicePortId)} readOnly disabled /></Field><Field label="Frame"><Input aria-label="Frame" value="0 - Display board 0" readOnly /></Field><Field label="Slot"><select aria-label="Slot ID" value={qinq.slot} onChange={e => setQinq({ ...qinq, slot: e.target.value, port: `0/${e.target.value} ${qinq.port_number}` })} className="h-9 border border-input bg-background px-3 text-xs"><option value="3">3 - H901MPSA (CPCA) · Active</option><option value="4" disabled>4 - H901MPSA (CPCA) · Offline</option></select></Field><Field label="Port"><select aria-label="GE port" value={qinq.port_number} onChange={e => setQinq({ ...qinq, port_number: e.target.value, port: `0/${qinq.slot} ${e.target.value}` })} className="h-9 border border-input bg-background px-3 text-xs"><option value="">Select GE port</option>{availablePorts(qinq.slot, editingQinqId ? `${qinq.slot}:${qinq.port_number}` : undefined).map(port => <option key={port} value={port}>GE{port}</option>)}</select></Field><Field label="S-VLAN"><Input type="number" min="1" max="4094" placeholder="VLAN ID" aria-label="Service VLAN" value={qinq.outer_vlan} onChange={e => setQinq({ ...qinq, outer_vlan: e.target.value })} required /></Field></>}
          {qinqTab === 'c_vlan' && <Field label="C-VLAN"><Input type="number" min="1" max="4094" placeholder="VLAN ID" aria-label="Customer VLAN" value={qinq.inner_vlan} onChange={e => setQinq({ ...qinq, inner_vlan: e.target.value })} required /></Field>}
{qinqTab === 'ont_line_profile' && <><Field label="C-VLAN"><select aria-label="C-VLAN for ONT line profile" value={qinq.outer_vlan} onChange={e => setQinq({ ...qinq, outer_vlan: e.target.value })} className="h-9 border border-input bg-background px-3 text-xs" required><option value="">Select C-VLAN</option>{cVlans.map(row => <option key={row.id} value={row.inner_vlan || ''}>C-VLAN {row.inner_vlan} · {row.name}</option>)}</select></Field><Field label="Profile ID"><Input aria-label="ONT line profile ID" value={qinq.profile_id || String(nextProfileId)} readOnly disabled /></Field><Field label="Profile name"><Input aria-label="ONT line profile name" value={profileName} readOnly disabled /></Field><Field label="TR-069 VLAN"><select aria-label="TR-069 VLAN" value={qinq.inner_vlan} onChange={e => setQinq({ ...qinq, inner_vlan: e.target.value })} className="h-9 border border-input bg-background px-3 text-xs" required><option value="">Select TR-069 VLAN</option>{tr069Records.map(row => <option key={row.id} value={row.vlan_id}>VLAN {row.vlan_id} · {row.name}</option>)}</select></Field><Field label="DBA profile"><select aria-label="DBA profile" value={qinq.dba_profile_id} onChange={e => setQinq({ ...qinq, dba_profile_id: e.target.value })} className="h-9 border border-input bg-background px-3 text-xs" required><option value="">Select DBA profile</option>{(olt.dba_profiles || []).map(profile => <option key={profile.id} value={profile.profile_id}>{profile.profile_name} · {profile.bandwidth_mbps} Mbps</option>)}</select></Field></>}
          {qinqTab !== 'ont_line_profile' && <Field label="Name"><Input placeholder="Name" aria-label="QinQ name" value={qinq.name} onChange={e => setQinq({ ...qinq, name: e.target.value })} required /></Field>}
          <Button type="submit" className="w-full max-w-[196px]" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : editingQinqId ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Add</>}</Button>
        </form>}</CardContent></Card></div>}{qinqTab === 'dba_profile' ? null : qinqTab === 'tr069_vlan' ? list(tr069Records, 'vlan') : list(visibleQinq, 'qinq')}
      </section>}
    </CardContent></Card>
  </div>
}
