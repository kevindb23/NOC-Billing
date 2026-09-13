import { useCallback, useEffect, useState } from 'react'
import { ArrowClockwiseIcon, PencilSimpleIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage, notify } from '@/lib/notifications'
import { createRole, deleteRole, getRole, hasPermission, listPermissions, listRoles, updateRole, type PermissionGroup, type Role, type RoleRequest } from '@/lib/usersRoles'
import { useConfirm } from './ConfirmProvider'
import { RoleForm } from './RoleForm'

export function RolesPage({ token, permissions }: { token?: string; permissions?: string[] }) {
  const [roles, setRoles] = useState<Role[]>([])
  const [groups, setGroups] = useState<PermissionGroup[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<{ mode: 'create' | 'edit'; role?: Role } | null>(null)
  const canView = hasPermission(permissions, 'roles.view')
  const canCreate = hasPermission(permissions, 'roles.create')
  const canUpdate = hasPermission(permissions, 'roles.update')
  const canDelete = hasPermission(permissions, 'roles.delete')
  const confirm = useConfirm()
  const load = useCallback(async () => { if (!canView) return; setLoading(true); setError(''); try { const [roleResponse, permissionResponse] = await Promise.all([listRoles(token, page), listPermissions(token)]); setRoles(roleResponse.data.data); setLastPage(roleResponse.data.last_page); setGroups(permissionResponse.data) } catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to load roles.') } finally { setLoading(false) } }, [canView, page, token])
  useEffect(() => { void load() }, [load])
  const openEdit = async (role: Role) => { if (!canUpdate) return; try { const response = await getRole(role.id, token); setForm({ mode: 'edit', role: response.data }) } catch (exception) { const message = getErrorMessage(exception, 'Unable to load role details.'); setError(message); notify.error(message) } }
  const close = () => { setForm(null); setFormError('') }
  const submit = async (payload: RoleRequest) => { setSaving(true); setFormError(''); try { const operation = form?.mode === 'create' ? createRole(payload, token) : form?.role ? updateRole(form.role.id, payload, token) : Promise.reject(new Error('No role selected.')); await notify.promise(operation, { loading: form?.mode === 'create' ? 'Creating role…' : 'Saving role…', success: form?.mode === 'create' ? 'Role created.' : 'Role updated.', error: 'Unable to save role.' }); close(); await load() } catch (exception) { setFormError(getErrorMessage(exception, 'Unable to save role.')) } finally { setSaving(false) } }
  const remove = async (role: Role) => { if (!canDelete) return; const confirmed = await confirm({ title: `Delete ${role.name}?`, description: 'This will remove the installation role and its access policy.', confirmLabel: 'Delete', destructive: true }); if (!confirmed) return; try { await notify.promise(deleteRole(role.id, token), { loading: 'Deleting role…', success: 'Role deleted.', error: 'Unable to delete role.' }); await load() } catch (exception) { const message = getErrorMessage(exception, 'Unable to delete role.'); setError(message) } }
  return <div className="flex flex-col gap-4"><div className="billing-page-heading flex items-center justify-between gap-4"><div><h1 className="text-sm font-semibold">Roles</h1><p className="mt-1 text-xs text-muted-foreground">Define reusable installation access policies.</p></div>{canCreate && <Button onClick={() => setForm({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New role</Button>}</div>{error && <Alert variant="destructive"><AlertTitle>Could not load roles</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<Card className="billing-records overflow-hidden"><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle>Access roles <span className="ml-2 rounded-sm bg-muted px-1.5 py-0.5 font-mono text-[10px] font-normal text-muted-foreground">{roles.length}</span></CardTitle><Button variant="outline" size="sm" onClick={() => void load()}><ArrowClockwiseIcon data-icon="inline-start" />Refresh</Button></CardHeader><CardContent className="p-0"><Table className="min-w-[720px]"><TableHeader><TableRow><TableHead>Role</TableHead><TableHead>Assignments</TableHead><TableHead>Permissions</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{loading ? <TableRow><TableCell colSpan={4} className="h-36 text-center text-muted-foreground">Loading roles…</TableCell></TableRow> : roles.length ? roles.map(role => <TableRow key={role.id}><TableCell className="font-medium">{role.name}</TableCell><TableCell>{role.assignment_count ?? 0}</TableCell><TableCell>{role.permission_count ?? 0}</TableCell><TableCell className="text-right"><div className="flex justify-end gap-1">{canUpdate && <Button type="button" variant="ghost" size="icon-sm" aria-label={`Edit ${role.name}`} onClick={() => void openEdit(role)}><PencilSimpleIcon /></Button>}{canDelete && <Button type="button" variant="ghost" size="icon-sm" aria-label={`Delete ${role.name}`} onClick={() => void remove(role)}><TrashIcon /></Button>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={4} className="h-36 text-center text-muted-foreground">No roles found.</TableCell></TableRow>}</TableBody></Table>{!loading && lastPage > 1 && <div className="flex items-center justify-between border-t px-3 py-2 text-xs text-muted-foreground"><span>Page {page} of {lastPage}</span><div className="flex gap-2"><Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(current => current - 1)}>Previous</Button><Button variant="outline" size="sm" disabled={page === lastPage} onClick={() => setPage(current => current + 1)}>Next</Button></div></div>}</CardContent></Card><RoleForm open={form !== null} mode={form?.mode || 'create'} role={form?.role} groups={groups} error={formError} loading={saving} onClose={close} onSubmit={submit} /></div>
}
