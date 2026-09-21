import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ArrowsClockwiseIcon, GearIcon, GlobeIcon, KeyIcon, PencilSimpleIcon, PlusIcon, TrashIcon, WifiHighIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { hasPermission } from '../lib/usersRoles'
import { CrudModal, type CrudField } from './CrudModal'
import { useConfirm } from './ConfirmProvider'

type AcsServer = { id: number; public_id: string; name: string; api_url: string; api_username: string; transport: string; status: string; ssh_username: string; ssh_port: number }
type ModalState = { mode: 'create' | 'edit'; row?: AcsServer } | null
type SettingsModalState = { row: AcsServer; minimumLength: string; loading: boolean; saving: boolean; error: string } | null
type Props = { token: string; permissions?: string[]; isSuperadmin?: boolean }

let inventoryRequest: { token: string; promise: Promise<{ data: AcsServer[] }> } | null = null

function loadInventory(token: string): Promise<{ data: AcsServer[] }> {
  if (inventoryRequest?.token === token) return inventoryRequest.promise
  const promise = apiRequest<{ data: AcsServer[] }>('/acs-servers', {}, token).finally(() => {
    if (inventoryRequest?.promise === promise) inventoryRequest = null
  })
  inventoryRequest = { token, promise }
  return promise
}

const fields = (editing: boolean): CrudField[] => [
  { name: 'name', label: 'ACS server name', required: true, placeholder: 'Primary ACS' },
  { name: 'api_url', label: 'API URL', required: true, placeholder: 'http://acs.example.com:7557' },
  { name: 'api_username', label: 'API username', required: true, placeholder: 'acs-admin' },
  { name: 'api_password', label: 'API password', required: !editing, type: 'password', placeholder: editing ? 'Leave blank to keep current' : 'Enter API password' },
  { name: 'status', label: 'Status', required: true, options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], searchable: false },
  { name: 'transport', label: 'Transport', required: true, options: [{ value: 'cwmp', label: 'CWMP / TR-069' }], searchable: false },
  { name: 'ssh_username', label: 'SSH username', required: true, placeholder: 'root' },
  { name: 'ssh_password', label: 'SSH password', required: !editing, type: 'password', placeholder: editing ? 'Leave blank to keep current' : 'Enter SSH password' },
  { name: 'ssh_port', label: 'SSH port', required: true, type: 'number', placeholder: '22' },
]

const valuesFor = (row?: AcsServer): Record<string, string> => row ? { name: row.name, api_url: row.api_url, api_username: row.api_username, api_password: '', status: row.status, transport: row.transport, ssh_username: row.ssh_username, ssh_password: '', ssh_port: String(row.ssh_port || 22) } : { status: 'active', transport: 'cwmp', ssh_port: '22' }

export function AcsServerPage({ token, permissions, isSuperadmin }: Props) {
  const [rows, setRows] = useState<AcsServer[]>([])
  const [modal, setModal] = useState<ModalState>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState<'ssh' | 'api' | null>(null)
  const [settingsModal, setSettingsModal] = useState<SettingsModalState>(null)
  const [error, setError] = useState('')
  const confirm = useConfirm()
  const canCreate = isSuperadmin !== false || hasPermission(permissions, 'acs.create')
  const canUpdate = isSuperadmin !== false || hasPermission(permissions, 'acs.update')
  const canDelete = isSuperadmin !== false || hasPermission(permissions, 'acs.delete')
  const canTest = isSuperadmin !== false || hasPermission(permissions, 'acs.test')

  const load = async () => { setLoading(true); try { const response = await loadInventory(token); setRows(response.data || []); setError('') } catch (exception) { setError(getErrorMessage(exception, 'Unable to load ACS servers.')) } finally { setLoading(false) } }
  useEffect(() => { void load() }, [token])

  const save = async (values: Record<string, string>) => { setSaving(true); try { const path = modal?.mode === 'edit' && modal.row ? `/acs-servers/${modal.row.public_id}` : '/acs-servers'; await apiRequest(path, { method: modal?.mode === 'edit' ? 'PUT' : 'POST', body: JSON.stringify(values) }, token); notify.success(modal?.mode === 'edit' ? 'ACS server updated.' : 'ACS server created.'); setModal(null); await load() } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to save ACS server.')) } finally { setSaving(false) } }

  const test = async (kind: 'ssh' | 'api', values?: Record<string, string>, row?: AcsServer) => { if (!canTest) return; setTesting(kind); try { const path = row ? `/acs-servers/${row.public_id}/test-${kind}` : `/acs-servers/test-${kind}`; const response = await apiRequest<{ data: { message: string } }>(path, { method: 'POST', ...(row ? {} : { body: JSON.stringify(values) }) }, token); notify.success(response.data.message || (kind === 'ssh' ? 'SSH connection successful.' : 'ACS API test successful.')) } catch (exception) { notify.error(getErrorMessage(exception, 'Connection test failed.')) } finally { setTesting(null) } }

  const openSettings = async (row: AcsServer) => {
    setSettingsModal({ row, minimumLength: '6', loading: true, saving: false, error: '' })
    try {
      const response = await apiRequest<{ data: { minimum_password_length: number } }>(`/acs-servers/${row.public_id}/settings/password-complexity`, {}, token)
      setSettingsModal(current => current?.row.public_id === row.public_id ? { ...current, minimumLength: String(response.data.minimum_password_length), loading: false } : current)
    } catch (exception) {
      setSettingsModal(current => current?.row.public_id === row.public_id ? { ...current, loading: false, error: getErrorMessage(exception, 'Unable to load ACS settings.') } : current)
    }
  }

  const savePasswordComplexity = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!settingsModal || settingsModal.loading || settingsModal.saving) return
    setSettingsModal(current => current ? { ...current, saving: true, error: '' } : current)
    try {
      const response = await apiRequest<{ data: { message: string } }>(`/acs-servers/${settingsModal.row.public_id}/settings/password-complexity`, {
        method: 'PATCH',
        body: JSON.stringify({ minimum_password_length: Number(settingsModal.minimumLength) }),
      }, token)
      notify.success(response.data.message)
      setSettingsModal(null)
    } catch (exception) {
      const message = getErrorMessage(exception, 'Unable to update password complexity.')
      setSettingsModal(current => current ? { ...current, saving: false, error: message } : current)
      notify.error(message)
    }
  }

  const remove = async (row: AcsServer) => { if (!canDelete || !await confirm({ title: `Delete ${row.name}?`, description: 'This removes the ACS server record and its saved credentials.', confirmLabel: 'Delete permanently', destructive: true })) return; try { await apiRequest(`/acs-servers/${row.public_id}?permanent=1`, { method: 'DELETE' }, token); notify.success('ACS server deleted.'); await load() } catch (exception) { notify.error(getErrorMessage(exception, 'Unable to delete ACS server.')) } }

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex flex-wrap items-end justify-between gap-4"><div><p className="billing-eyebrow">Network / access</p><h1 className="mt-2 text-lg font-semibold tracking-tight">ACS Server</h1><p className="mt-1 max-w-2xl text-xs text-muted-foreground">Manage TR-069 and CWMP auto-configuration servers.</p></div>{canCreate && <Button type="button" onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New ACS server</Button>}</div>
    {error && <div className="border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive" role="alert"><strong>ACS inventory unavailable</strong><p className="mt-1">{error}</p></div>}
    <Card className="billing-records overflow-hidden"><CardHeader className="gap-2 border-b border-border/60"><CardTitle className="flex items-center gap-2">ACS server inventory <span className="bg-muted px-2 py-1 text-[10px] font-normal text-muted-foreground">{rows.length} records</span></CardTitle><CardDescription>Auto-configuration servers ready for device management operations.</CardDescription></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><table className="w-full min-w-[900px] text-left text-xs"><thead><tr className="border-b text-muted-foreground"><th className="px-4 py-3 font-normal">Server</th><th className="px-4 py-3 font-normal">API URL</th><th className="px-4 py-3 font-normal">SSH</th><th className="px-4 py-3 font-normal">Transport</th><th className="px-4 py-3 font-normal">Status</th><th className="px-4 py-3 text-right font-normal">Actions</th></tr></thead><tbody>{loading ? <tr><td colSpan={6} className="h-40 text-center text-muted-foreground">Loading ACS servers…</td></tr> : rows.length ? rows.map(row => <tr className="border-b last:border-0" key={row.public_id}><td className="px-4 py-3 font-medium">{row.name}</td><td className="px-4 py-3 font-mono text-[11px]">{row.api_url}</td><td className="px-4 py-3">{row.ssh_username}:{row.ssh_port}</td><td className="px-4 py-3 uppercase">{row.transport}</td><td className="px-4 py-3"><Badge variant="outline">{row.status}</Badge></td><td className="px-4 py-3"><div className="flex justify-end gap-1">{canTest && <><Button type="button" variant="ghost" size="icon-sm" title="Test SSH connection" aria-label={`Test SSH connection for ${row.name}`} disabled={testing !== null} onClick={() => void test('ssh', undefined, row)}><WifiHighIcon /></Button><Button type="button" variant="ghost" size="icon-sm" title="Test ACS API" aria-label={`Test ACS API for ${row.name}`} disabled={testing !== null} onClick={() => void test('api', undefined, row)}><GlobeIcon /></Button></>}{canUpdate && <Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 sm:min-h-7 sm:min-w-7" title="ACS settings" aria-label={`Open settings for ${row.name}`} onClick={() => void openSettings(row)}><GearIcon /></Button>}{canUpdate && <Button type="button" variant="ghost" size="icon-sm" title="Edit ACS server" aria-label={`Edit ${row.name}`} onClick={() => setModal({ mode: 'edit', row })}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" title="Remove ACS server" aria-label={`Delete ${row.name}`} onClick={() => void remove(row)}><TrashIcon /></Button>}</div></td></tr>) : <tr><td colSpan={6} className="h-48 text-center"><p className="text-sm font-medium">No ACS servers configured.</p><p className="mt-1 text-xs text-muted-foreground">Add an ACS server to begin managing auto-configuration endpoints.</p></td></tr>}</tbody></table></div></CardContent></Card>
    {modal && <CrudModal key={`${modal.mode}-${modal.row?.public_id || 'new'}`} open mode={modal.mode} title={modal.mode === 'create' ? 'New ACS server' : `Edit ${modal.row?.name || 'ACS server'}`} description="Register an auto-configuration server and its SSH access details." fields={fields(modal.mode === 'edit')} initialValues={valuesFor(modal.row)} loading={saving} onClose={() => setModal(null)} onSubmit={save} renderActions={canTest ? values => <><Button type="button" variant="outline" size="icon-sm" title="Test SSH connection" aria-label="Test SSH connection" disabled={testing !== null} onClick={() => void test('ssh', values)}>{testing === 'ssh' ? <ArrowsClockwiseIcon className="animate-spin" /> : <WifiHighIcon />}</Button><Button type="button" variant="outline" size="icon-sm" title="Test ACS API" aria-label="Test ACS API" disabled={testing !== null} onClick={() => void test('api', values)}>{testing === 'api' ? <ArrowsClockwiseIcon className="animate-spin" /> : <GlobeIcon />}</Button></> : undefined} />}
    {settingsModal && <AcsServerSettingsModal state={settingsModal} onClose={() => setSettingsModal(null)} onChange={minimumLength => setSettingsModal(current => current ? { ...current, minimumLength, error: '' } : current)} onSubmit={savePasswordComplexity} />}
  </div>
}

function AcsServerSettingsModal({ state, onClose, onChange, onSubmit }: { state: NonNullable<SettingsModalState>; onClose: () => void; onChange: (value: string) => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  const disabled = state.loading || state.saving

  return <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="acs-settings-title">
    <Card className="billing-modal-card relative flex max-h-[min(38rem,calc(100dvh-1.5rem))] w-full max-w-lg flex-col shadow-2xl sm:max-h-[min(38rem,calc(100dvh-2rem))]">
      <CardHeader className="billing-modal-header shrink-0 border-b pr-14">
        <p className="billing-modal-eyebrow">ACS settings</p>
        <CardTitle id="acs-settings-title">{state.row.name}</CardTitle>
        <CardDescription>Manage the GenieACS UI password policy for this server.</CardDescription>
        <Button type="button" variant="ghost" size="icon-sm" className="absolute right-3 top-3 min-h-11 min-w-11 sm:right-4 sm:top-4 sm:min-h-7 sm:min-w-7" onClick={onClose} aria-label="Close ACS settings"><XIcon /></Button>
      </CardHeader>
      <div className="min-h-0 flex-1 overflow-y-auto">
        <CardContent className="space-y-5 pt-4 sm:pt-5">
          <div className="flex items-start gap-3 border-b border-border/70 pb-4" aria-label="ACS settings section">
            <div className="grid size-9 shrink-0 place-items-center border border-emerald-900/10 bg-secondary text-secondary-foreground" aria-hidden="true"><KeyIcon size={17} weight="bold" /></div>
            <div className="min-w-0">
              <h3 className="text-sm font-semibold">Password Complexity</h3>
              <p className="mt-1 text-xs leading-5 text-muted-foreground">Set the minimum password length required by the GenieACS UI.</p>
            </div>
          </div>
          <form id="acs-password-complexity-form" className="space-y-4" onSubmit={onSubmit}>
            <Field>
              <FieldLabel htmlFor="acs-minimum-password-length">Minimum Password Length</FieldLabel>
              <Input id="acs-minimum-password-length" type="number" min="1" max="255" value={state.minimumLength} onChange={event => onChange(event.target.value)} disabled={disabled} required />
              <p className="text-xs leading-5 text-muted-foreground">Updates <code>GENIEACS_UI_MIN_PASSWORD_LENGTH</code> and restarts the GenieACS UI service.</p>
            </Field>
            {state.loading && <p className="text-xs text-muted-foreground" role="status">Reading the current value from the ACS server…</p>}
            {state.error && <p className="text-xs text-destructive" role="alert">{state.error}</p>}
          </form>
        </CardContent>
      </div>
      <div className="billing-modal-footer flex shrink-0 flex-wrap justify-end gap-2 border-t bg-card px-4 py-4 sm:px-5">
        <Button type="button" variant="outline" className="min-h-11 sm:min-h-9" onClick={onClose}>Cancel</Button>
        <Button type="submit" form="acs-password-complexity-form" className="min-h-11 sm:min-h-9" disabled={disabled}>{state.saving ? 'Saving…' : 'Save password policy'}</Button>
      </div>
    </Card>
  </div>
}
