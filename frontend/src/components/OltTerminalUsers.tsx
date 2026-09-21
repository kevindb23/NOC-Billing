import { useState, type ReactNode } from 'react'
import { CircleNotchIcon, PencilSimpleIcon, PlusIcon, TrashIcon, XIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { apiRequest } from '../lib/api'
import { getErrorMessage, notify } from '../lib/notifications'
import { useConfirm } from './ConfirmProvider'

export type OltTerminalUser = {
  id: number
  username: string
  profile_name: 'root' | 'ispadmin'
  privilege_level: number
  reenter_limit: number
  appended_info?: string | null
  status: string
  notes?: string | null
}

type FormState = {
  username: string
  password: string
  profile_name: 'root' | 'ispadmin'
  privilege_level: string
  reenter_limit: string
  appended_info: string
  notes: string
}

const emptyForm = (): FormState => ({ username: '', password: '', profile_name: 'root', privilege_level: '3', reenter_limit: '1', appended_info: '', notes: '' })
const UserField = ({ label, children, className = '' }: { label: string; children: ReactNode; className?: string }) => <label className={`flex min-w-0 w-full flex-col gap-1 text-xs ${className}`}><span className="font-medium text-muted-foreground">{label}</span>{children}</label>

function privilegeLabel(level: number): string {
  if (level >= 3) return 'Administrator'
  if (level === 2) return 'Operator'
  return 'Common user'
}

export function OltTerminalUsers({ token, publicId, users, canProvision, onRefresh, securityEnabled = true, securityLength = 12 }: { token: string; publicId: string; users: OltTerminalUser[]; canProvision: boolean; onRefresh: () => Promise<void>; securityEnabled?: boolean; securityLength?: number }) {
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<OltTerminalUser | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm())
  const [saving, setSaving] = useState(false)
  const [policyEnabled, setPolicyEnabled] = useState(securityEnabled)
  const [policyLength, setPolicyLength] = useState(String(securityLength))
  const [savingPolicy, setSavingPolicy] = useState(false)
  const confirm = useConfirm()

  const openForm = (user?: OltTerminalUser) => {
    setEditing(user || null)
    setForm(user ? {
      username: user.username,
      password: '',
      profile_name: user.profile_name,
      privilege_level: String(user.privilege_level),
      reenter_limit: String(user.reenter_limit),
      appended_info: user.appended_info || '',
      notes: user.notes || '',
    } : emptyForm())
    setOpen(true)
  }

  const save = async () => {
    setSaving(true)
    try {
      const payload: Record<string, unknown> = {
        username: form.username,
        profile_name: form.profile_name,
        privilege_level: Number(form.privilege_level),
        reenter_limit: Number(form.reenter_limit),
        appended_info: form.appended_info || null,
        notes: form.notes || null,
      }
      if (form.password) payload.password = form.password
      const path = editing ? `/olts/${publicId}/terminal-users/${editing.id}` : `/olts/${publicId}/terminal-users`
      await apiRequest(path, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) }, token)
      notify.success(editing ? 'Terminal user updated.' : 'Terminal user created.')
      setOpen(false)
      setEditing(null)
      await onRefresh()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to save terminal user.'))
    } finally {
      setSaving(false)
    }
  }

  const remove = async (user: OltTerminalUser) => {
    if (!await confirm({ title: `Delete ${user.username}?`, description: 'This removes the user from the OLT and from this management record.', confirmLabel: 'Delete permanently', destructive: true })) return
    try {
      await apiRequest(`/olts/${publicId}/terminal-users/${user.id}`, { method: 'DELETE' }, token)
      notify.success('Terminal user deleted.')
      await onRefresh()
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to delete terminal user.'))
    }
  }

  const savePolicy = async () => {
    setSavingPolicy(true)
    try {
      await apiRequest(`/olts/${publicId}/terminal-user-policy`, { method: 'PATCH', body: JSON.stringify({ security_enabled: policyEnabled, security_length: Number(policyLength) }) }, token)
      notify.success('Terminal user password policy updated.')
    } catch (exception) {
      notify.error(getErrorMessage(exception, 'Unable to update terminal user password policy.'))
    } finally {
      setSavingPolicy(false)
    }
  }

  return <section className="rounded-lg border border-border/70 bg-background/40 p-4" aria-labelledby="olt-users-title">
    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div><h2 id="olt-users-title" className="text-sm font-semibold">Terminal users</h2><p className="mt-1 max-w-2xl text-xs text-muted-foreground">Manage the local accounts used to access this OLT. Passwords are never displayed after saving.</p></div>
      {canProvision && <Button type="button" className="min-h-11 sm:min-h-8" onClick={() => openForm()}><PlusIcon data-icon="inline-start" />New terminal user</Button>}
    </div>

    {canProvision && <div className="mb-4 grid gap-3 rounded-md border border-border/70 bg-muted/20 p-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
      <label className="flex min-h-11 cursor-pointer items-center gap-3 text-xs sm:min-h-8"><input type="checkbox" aria-label="Enhanced password security" className="size-4 accent-primary" checked={policyEnabled} onChange={event => setPolicyEnabled(event.target.checked)} /><span><span className="block font-medium text-foreground">Enhanced password security</span><span className="text-muted-foreground">Require the OLT’s stronger terminal-password policy.</span></span></label>
      <label className="flex min-w-0 flex-col gap-1 text-xs"><span className="font-medium text-muted-foreground">Minimum password length</span><Input type="number" aria-label="Minimum password length" min="6" max="128" value={policyLength} onChange={event => setPolicyLength(event.target.value)} className="min-h-11 w-full sm:min-h-8 sm:w-28" /></label>
      <Button type="button" variant="outline" className="min-h-11 sm:min-h-8" disabled={savingPolicy} onClick={() => void savePolicy()}>{savingPolicy ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : 'Save policy'}</Button>
    </div>}

    {users.length === 0 ? <div className="rounded-md border border-dashed border-border px-4 py-12 text-center text-sm text-muted-foreground"><p className="font-medium text-foreground">No terminal users configured.</p><p className="mt-1 text-xs">Add the first local OLT account to manage device access.</p></div> : <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[720px] text-left text-xs"><thead><tr className="border-b text-muted-foreground"><th className="px-2 py-2 font-medium">Username</th><th className="px-2 py-2 font-medium">Profile</th><th className="px-2 py-2 font-medium">Privilege</th><th className="px-2 py-2 font-medium">Re-entry</th><th className="px-2 py-2 font-medium">Status</th><th className="px-2 py-2 text-right font-medium">Actions</th></tr></thead><tbody>{users.map(user => <tr key={user.id} className="border-b last:border-0"><td className="px-2 py-3 font-medium">{user.username}</td><td className="px-2 py-3">{user.profile_name}</td><td className="px-2 py-3">{privilegeLabel(user.privilege_level)} <span className="text-muted-foreground">({user.privilege_level})</span></td><td className="px-2 py-3">{user.reenter_limit}</td><td className="px-2 py-3"><Badge variant="outline">{user.status}</Badge></td><td className="px-2 py-2"><div className="flex justify-end gap-1">{canProvision && <><Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 md:min-h-8 md:min-w-8" aria-label={`Edit ${user.username}`} onClick={() => openForm(user)}><PencilSimpleIcon /></Button><Button type="button" variant="ghost" size="icon-sm" className="min-h-11 min-w-11 md:min-h-8 md:min-w-8" aria-label={`Delete ${user.username}`} onClick={() => void remove(user)}><TrashIcon /></Button></>}</div></td></tr>)}</tbody></table>
      </div>
      <div className="grid gap-3 md:hidden">{users.map(user => <article key={user.id} className="rounded-md border border-border/70 bg-background p-4"><div className="flex items-start justify-between gap-3"><div><h3 className="font-medium">{user.username}</h3><p className="mt-1 text-xs text-muted-foreground">{user.profile_name} · {privilegeLabel(user.privilege_level)} ({user.privilege_level})</p></div><Badge variant="outline">{user.status}</Badge></div><dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs"><div><dt className="text-muted-foreground">Re-entry limit</dt><dd className="mt-0.5">{user.reenter_limit}</dd></div><div><dt className="text-muted-foreground">Appended info</dt><dd className="mt-0.5 break-words">{user.appended_info || '—'}</dd></div></dl>{canProvision && <div className="mt-4 flex gap-2 border-t border-border/70 pt-3"><Button type="button" variant="outline" className="min-h-11 flex-1" onClick={() => openForm(user)}><PencilSimpleIcon data-icon="inline-start" />Edit</Button><Button type="button" variant="outline" className="min-h-11 flex-1" onClick={() => void remove(user)}><TrashIcon data-icon="inline-start" />Delete</Button></div>}</article>)}</div>
    </>}

    {open && <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="terminal-user-form-title"><Card className="billing-modal-card relative w-full max-w-2xl shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{editing ? 'Update record' : 'Create record'}</p><CardTitle id="terminal-user-form-title">{editing ? 'Edit terminal user' : 'New terminal user'}</CardTitle><CardDescription>Configure the account profile and access level for this OLT.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4 min-h-11 min-w-11" onClick={() => setOpen(false)} aria-label="Close terminal user form"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={event => { event.preventDefault(); void save() }} aria-busy={saving}><UserField label="Username"><Input aria-label="Username" value={form.username} onChange={event => setForm({ ...form, username: event.target.value })} minLength={6} maxLength={64} pattern="[A-Za-z][A-Za-z0-9_-]*" required /></UserField><UserField label="Password"><Input type="password" aria-label="Password" autoComplete={editing ? 'new-password' : 'current-password'} minLength={12} maxLength={128} placeholder={editing ? 'Leave blank to keep current' : 'Minimum 12 characters'} value={form.password} onChange={event => setForm({ ...form, password: event.target.value })} required={!editing} /></UserField><UserField label="User profile"><select aria-label="User profile" className="h-9 w-full border border-input bg-background px-3 text-xs" value={form.profile_name} onChange={event => setForm({ ...form, profile_name: event.target.value as FormState['profile_name'] })}><option value="root">root</option><option value="ispadmin">ispadmin</option></select></UserField><UserField label="Privilege level"><Input type="number" aria-label="Privilege level" min="0" max="15" value={form.privilege_level} onChange={event => setForm({ ...form, privilege_level: event.target.value })} required /></UserField><UserField label="Permitted re-entry count"><Input type="number" aria-label="Permitted re-entry count" min="0" max="20" value={form.reenter_limit} onChange={event => setForm({ ...form, reenter_limit: event.target.value })} required /></UserField><UserField label="Appended info"><Input aria-label="Appended info" maxLength={30} placeholder="Optional device note" value={form.appended_info} onChange={event => setForm({ ...form, appended_info: event.target.value })} /></UserField><UserField label="Notes" className="sm:col-span-2"><textarea aria-label="Notes" className="min-h-20 w-full border border-input bg-background px-3 py-2 text-xs" placeholder="Optional management note" value={form.notes} onChange={event => setForm({ ...form, notes: event.target.value })} /></UserField><div className="flex flex-col-reverse gap-2 border-t pt-4 sm:col-span-2 sm:flex-row sm:justify-end"><Button type="button" variant="outline" className="min-h-11 sm:min-h-8" onClick={() => setOpen(false)}>Cancel</Button><Button type="submit" className="min-h-11 sm:min-h-8" disabled={saving}>{saving ? <><CircleNotchIcon className="animate-spin" data-icon="inline-start" />Saving…</> : editing ? 'Save changes' : <><PlusIcon data-icon="inline-start" />Create terminal user</>}</Button></div></form></CardContent></Card></div>}
  </section>
}
