import { useCallback, useEffect, useState } from 'react'
import { ArrowClockwiseIcon, PencilSimpleIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { createRole, deleteRole, getRole, hasPermission, listPermissions, listRoles, updateRole, type PermissionGroup, type Role, type RoleRequest } from '@/lib/usersRoles'
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
  const load = useCallback(async () => { if (!canView) return; setLoading(true); setError(''); try { const [roleResponse, permissionResponse] = await Promise.all([listRoles(token, page), listPermissions(token)]); setRoles(roleResponse.data.data); setLastPage(roleResponse.data.last_page); setGroups(permissionResponse.data) } catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to load roles.') } finally { setLoading(false) } }, [canView, page, token])
  useEffect(() => { void load() }, [load])
  const openEdit = async (role: Role) => { if (role.scope !== 'organization' || !canUpdate) return; try { const response = await getRole(role.id, token); setForm({ mode: 'edit', role: response.data }) } catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to load role details.') } }
  const close = () => { setForm(null); setFormError('') }
  const submit = async (payload: RoleRequest) => { setSaving(true); setFormError(''); try { if (form?.mode === 'create') await createRole(payload, token); else if (form?.role) await updateRole(form.role.id, payload, token); close(); await load() } catch (exception) { setFormError(exception instanceof Error ? exception.message : 'Unable to save role.') } finally { setSaving(false) } }
  const remove = async (role: Role) => { if (!canDelete || !window.confirm(`Delete ${role.name}?`)) return; try { await deleteRole(role.id, token); await load() } catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to delete role.') } }
  return <div className="flex flex-col gap-4"><div className="billing-page-heading flex items-center justify-between gap-4"><div><h1 className="text-sm font-semibold">Roles</h1><p className="mt-1 text-xs text-muted-foreground">Define reusable organization access policies.</p></div>{canCreate && <Button onClick={() => setForm({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New role</Button>}</div>{error && <Alert variant="destructive"><AlertTitle>Could not load roles</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<Card className="billing-records overflow-hidden"><CardHeader className="flex flex-row items-center justify-between border-b"><CardTitle>Access roles <span className="ml-2 rounded-sm bg-muted px-1.5 py-0.5 font-mono text-[10px] font-normal text-muted-foreground">{roles.length}</span></CardTitle><Button variant="outline" size="sm" onClick={() => void load()}><ArrowClockwiseIcon data-icon="inline-start" />Refresh</Button></CardHeader><CardContent className="p-0"><Table className="min-w-[720px]"><TableHeader><TableRow><TableHead>Role</TableHead><TableHead>Scope</TableHead><TableHead>Assignments</TableHead><TableHead>Permissions</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{loading ? <TableRow><TableCell colSpan={5} className="h-36 text-center text-muted-foreground">Loading roles…</TableCell></TableRow> : roles.length ? roles.map(role => <TableRow key={role.id}><TableCell className="font-medium">{role.name}</TableCell><TableCell><span className="rounded-sm bg-muted px-1.5 py-1 text-[10px]">{role.scope}</span></TableCell><TableCell>{role.assignment_count ?? 0}</TableCell><TableCell>{role.permission_count ?? 0}</TableCell><TableCell className="text-right"><div className="flex justify-end gap-1">{role.scope === 'organization' && canUpdate && <Button variant="ghost" size="icon-sm" aria-label={`Edit ${role.name}`} onClick={() => void openEdit(role)}><PencilSimpleIcon /></Button>}{role.scope === 'organization' && canDelete && <Button variant="ghost" size="icon-sm" aria-label={`Delete ${role.name}`} onClick={() => void remove(role)}><TrashIcon /></Button>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={5} className="h-36 text-center text-muted-foreground">No roles found.</TableCell></TableRow>}</TableBody></Table>{!loading && lastPage > 1 && <div className="flex items-center justify-between border-t px-3 py-2 text-xs text-muted-foreground"><span>Page {page} of {lastPage}</span><div className="flex gap-2"><Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(current => current - 1)}>Previous</Button><Button variant="outline" size="sm" disabled={page === lastPage} onClick={() => setPage(current => current + 1)}>Next</Button></div></div>}</CardContent></Card><RoleForm open={form !== null} mode={form?.mode || 'create'} role={form?.role} groups={groups} error={formError} loading={saving} onClose={close} onSubmit={submit} /></div>
}
