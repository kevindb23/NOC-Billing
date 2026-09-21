import { useEffect, useState } from 'react'
import { EyeIcon, FloppyDiskIcon, PencilSimpleIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { CrudModal, type CrudField } from './CrudModal'
import { apiRequest } from '@/lib/api'
import { getErrorMessage, notify } from '@/lib/notifications'

type RadiusServer = { public_id: string; name: string; server_address: string }
type Plan = { id: number; name: string }
type SpeedBoost = { public_id: string; download_kbps: number; upload_kbps: number; status: string; synced_user_count: number; last_applied_at?: string | null; radius_server: RadiusServer; plan: Plan }
type Preview = { rate_value: string | null; usernames: string[]; user_count: number; missing: string[]; sql: string[]; plan?: Plan | null; radius_server?: RadiusServer | null }

const fieldLabels: Record<string, string> = { radius_server_id: 'RADIUS server', plan_id: 'Plan', download_mbps: 'Download speed', upload_mbps: 'Upload speed' }

export function SpeedBoostPanel({ token, publicId }: { token: string; publicId: string }) {
  const [rows, setRows] = useState<SpeedBoost[]>([])
  const [radiusServers, setRadiusServers] = useState<RadiusServer[]>([])
  const [plans, setPlans] = useState<Plan[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<SpeedBoost | null>(null)
  const [formKey, setFormKey] = useState(0)
  const [preview, setPreview] = useState<Preview | null>(null)
  const [saving, setSaving] = useState(false)

  const load = async () => {
    setLoading(true)
    try {
      const [boosts, radius, planResponse] = await Promise.all([
        apiRequest<{ data: SpeedBoost[] }>(`/bngs/${publicId}/speed-boosts`, {}, token),
        apiRequest<{ data: RadiusServer[] }>(`/bngs/${publicId}/radius-servers`, {}, token),
        apiRequest<{ data: { data: Plan[] } }>('/plans?per_page=100', {}, token),
      ])
      setRows(boosts.data)
      setRadiusServers(radius.data)
      setPlans(planResponse.data.data)
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to load Speed Boost configuration.')) }
    finally { setLoading(false) }
  }

  useEffect(() => { void load() }, [publicId, token])

  const openCreate = () => { setEditing(null); setFormKey(current => current + 1); setModalOpen(true) }
  const openEdit = (row: SpeedBoost) => { setEditing(row); setFormKey(current => current + 1); setModalOpen(true) }
  const close = () => { setModalOpen(false); setEditing(null) }
  const filterValue = (row: SpeedBoost) => `${row.download_kbps}/${row.upload_kbps}`
  const valuesFor = (row?: SpeedBoost | null) => row ? { radius_server_id: row.radius_server.public_id, plan_id: String(row.plan.id), download_mbps: String(row.download_kbps / 1000), upload_mbps: String(row.upload_kbps / 1000) } : { radius_server_id: '', plan_id: '', download_mbps: '', upload_mbps: '' }
  const fields: CrudField[] = [
    { name: 'radius_server_id', label: fieldLabels.radius_server_id, required: true, searchable: false, options: radiusServers.map(server => ({ value: server.public_id, label: `${server.name} · ${server.server_address}` })) },
    { name: 'plan_id', label: fieldLabels.plan_id, required: true, searchable: false, options: plans.map(plan => ({ value: String(plan.id), label: plan.name })) },
    { name: 'download_mbps', label: `${fieldLabels.download_mbps} (Mbps)`, required: true, type: 'number', placeholder: '20', },
    { name: 'upload_mbps', label: `${fieldLabels.upload_mbps} (Mbps)`, required: true, type: 'number', placeholder: '20', },
  ]

  const previewForm = async (values: Record<string, string>) => {
    try {
      const response = await apiRequest<{ data: Preview }>(`/bngs/${publicId}/speed-boosts/preview`, { method: 'POST', body: JSON.stringify({ ...values, plan_id: values.plan_id ? Number(values.plan_id) : undefined }) }, token)
      setPreview(response.data)
    } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to generate Speed Boost preview.')) }
  }

  const save = async (values: Record<string, string>, apply: boolean) => {
    setSaving(true)
    try {
      const payload = { radius_server_id: values.radius_server_id, plan_id: Number(values.plan_id), download_mbps: Number(values.download_mbps), upload_mbps: Number(values.upload_mbps) }
      const path = editing ? `/bngs/${publicId}/speed-boosts/${editing.public_id}` : `/bngs/${publicId}/speed-boosts`
      const response = await apiRequest<{ data: SpeedBoost }>(path, { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(payload) }, token)
      if (apply) {
        await notify.promise(apiRequest(`/bngs/${publicId}/speed-boosts/${response.data.public_id}/apply`, { method: 'POST' }, token), { loading: 'Saving and applying Speed Boost…', success: 'Speed Boost applied.', error: 'Unable to apply Speed Boost.' })
      } else {
        notify.success('Speed Boost draft saved.')
      }
      close()
      await load()
    } catch (exception) { notify.error(getErrorMessage(exception, apply ? 'Unable to apply Speed Boost.' : 'Unable to save Speed Boost draft.')) }
    finally { setSaving(false) }
  }

  const apply = async (row: SpeedBoost) => {
    try { await notify.promise(apiRequest(`/bngs/${publicId}/speed-boosts/${row.public_id}/apply`, { method: 'POST' }, token), { loading: 'Applying Speed Boost to subscribers…', success: 'Speed Boost applied.', error: 'Unable to apply Speed Boost.' }); await load() }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to apply Speed Boost.')) }
  }

  const remove = async (row: SpeedBoost) => {
    if (!window.confirm(`Remove Speed Boost for ${row.plan.name}?`)) return
    try { await notify.promise(apiRequest(`/bngs/${publicId}/speed-boosts/${row.public_id}`, { method: 'DELETE' }, token), { loading: 'Removing Speed Boost…', success: 'Speed Boost removed.', error: 'Unable to remove Speed Boost.' }); await load() }
    catch (exception) { notify.error(getErrorMessage(exception, 'Unable to remove Speed Boost.')) }
  }

  return <section className="border border-border/70 bg-background/40 p-4">
    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div><h2 className="text-sm font-semibold">Speed Boost</h2><p className="mt-1 text-xs text-muted-foreground">Set native Accel-PPP download and upload rates for every subscriber on a Plan.</p></div>
      <Button type="button" onClick={openCreate}><PlusIcon data-icon="inline-start" />Add Speed Boost</Button>
    </div>
    <div className="overflow-x-auto border border-border/60">
      <table className="w-full min-w-[900px] text-left text-xs">
        <thead className="bg-muted/30 text-muted-foreground"><tr><th className="px-3 py-3 font-normal">RADIUS server</th><th className="px-3 py-3 font-normal">Plan</th><th className="px-3 py-3 font-normal">Download / upload</th><th className="px-3 py-3 font-normal">Filter-Id</th><th className="px-3 py-3 font-normal">Status</th><th className="px-3 py-3 font-normal">Synced users</th><th className="px-3 py-3 font-normal">Last applied</th><th className="px-3 py-3 text-right font-normal">Actions</th></tr></thead>
        <tbody>{loading ? <tr><td colSpan={8} className="h-32 text-center text-muted-foreground">Loading Speed Boost profiles...</td></tr> : rows.length ? rows.map(row => <tr className="border-t border-border/60" key={row.public_id}><td className="px-3 py-3 font-medium">{row.radius_server.name}</td><td className="px-3 py-3">{row.plan.name}</td><td className="px-3 py-3">{row.download_kbps / 1000} / {row.upload_kbps / 1000} Mbps</td><td className="px-3 py-3 font-mono text-[11px]">{filterValue(row)}</td><td className="px-3 py-3"><Badge variant="outline">{row.status}</Badge></td><td className="px-3 py-3">{row.synced_user_count}</td><td className="px-3 py-3">{row.last_applied_at ? new Date(row.last_applied_at).toLocaleString() : '—'}</td><td className="px-3 py-3 text-right"><Button type="button" variant="ghost" size="icon-sm" aria-label={`Apply ${row.plan.name} Speed Boost`} title="Apply Speed Boost" onClick={() => void apply(row)}><FloppyDiskIcon /></Button><Button type="button" variant="ghost" size="icon-sm" aria-label={`Edit ${row.plan.name} Speed Boost`} title="Edit Speed Boost" onClick={() => openEdit(row)}><PencilSimpleIcon /></Button><Button type="button" variant="ghost" size="icon-sm" aria-label={`Remove ${row.plan.name} Speed Boost`} title="Remove Speed Boost" onClick={() => void remove(row)}><TrashIcon /></Button></td></tr>) : <tr><td colSpan={8} className="h-36 text-center text-muted-foreground">No Speed Boost profiles yet. Add a profile to assign native Accel-PPP rates by Plan.</td></tr>}</tbody>
      </table>
    </div>
    <CrudModal key={formKey} open={modalOpen} mode={editing ? 'edit' : 'create'} title={editing ? 'Edit Speed Boost' : 'New Speed Boost'} description="Use Accel-PPP native Filter-Id rates. Values are entered in Mbps and written to RADIUS in Kbit/s." fields={fields} initialValues={valuesFor(editing)} onClose={close} onSubmit={values => save(values, false)} submitLabel="Save draft" loading={saving} renderActions={values => <><Button type="button" variant="outline" onClick={() => void previewForm(values)}><EyeIcon data-icon="inline-start" />Preview</Button><Button type="button" onClick={() => void save(values, true)} disabled={saving}><FloppyDiskIcon data-icon="inline-start" />Save &amp; Apply</Button></>} />
    {preview && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="speed-boost-preview-title"><Card className="billing-modal-card relative w-full max-w-5xl shadow-2xl"><CardHeader className="border-b pr-14"><CardTitle id="speed-boost-preview-title">Speed Boost preview</CardTitle><CardDescription>Review the native Filter-Id rows before writing them to the RADIUS database.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={() => setPreview(null)} aria-label="Close Speed Boost preview">×</Button></CardHeader><CardContent className="grid gap-4 pt-5"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">RADIUS server</p><p className="mt-1 text-sm">{preview.radius_server?.name || 'Not selected'}</p></div><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Plan</p><p className="mt-1 text-sm">{preview.plan?.name || 'Not selected'}</p></div><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Users found</p><p className="mt-1 text-sm">{preview.user_count}</p></div><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Filter-Id</p><p className="mt-1 font-mono text-sm">{preview.rate_value || 'Incomplete'}</p></div></div>{preview.missing.length > 0 && <p className="text-xs text-muted-foreground">Missing: {preview.missing.map(key => fieldLabels[key] || key).join(', ')}.</p>}<div className="border border-border/70 bg-muted/30 p-4"><p className="mb-2 text-xs font-medium">Resolved PPP usernames</p><p className="font-mono text-xs">{preview.usernames.length ? preview.usernames.join(', ') : 'No active PPP usernames found.'}</p></div><pre className="max-h-[42vh] overflow-auto border border-border/70 bg-muted/30 p-4 font-mono text-xs leading-5">{preview.sql.length ? preview.sql.join('\n') : '-- No complete SQL rows to preview.'}</pre><div className="flex justify-end"><Button type="button" onClick={() => setPreview(null)}>Close preview</Button></div></CardContent></Card></div>}
  </section>
}
