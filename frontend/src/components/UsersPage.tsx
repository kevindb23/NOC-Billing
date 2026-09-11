import { useCallback, useEffect, useState } from 'react'
import { ArrowClockwiseIcon, PencilSimpleIcon, PlusIcon, UserMinusIcon, UserPlusIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { createUser, deactivateUser, hasPermission, listRoles, listUsers, reactivateUser, updateUser, type Role, type User, type UserRequest } from '@/lib/usersRoles'
import { UserForm } from './UserForm'

type UserStatusFilter = 'all' | 'active' | 'inactive'

export function UsersPage({ token, permissions }: { token?: string; permissions?: string[] }) {
  const [users, setUsers] = useState<User[]>([])
  const [roles, setRoles] = useState<Role[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<UserStatusFilter>('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<{ mode: 'create' | 'edit'; user?: User } | null>(null)
  const canView = hasPermission(permissions, 'users.view')
  const canViewRoles = hasPermission(permissions, 'roles.view')
  const canCreate = hasPermission(permissions, 'users.create')
  const canUpdate = hasPermission(permissions, 'users.update')
  const canDelete = hasPermission(permissions, 'users.delete')

  const load = useCallback(async () => {
    if (!canView) return
    setLoading(true)
    setError('')
    try {
      const userResponse = await listUsers(token, page, search, status)
      setUsers(userResponse.data.data)
      setLastPage(userResponse.data.last_page)
      if (canViewRoles) {
        try {
          const roleResponse = await listRoles(token, 1)
          setRoles(roleResponse.data.data)
        } catch {
          setRoles([])
        }
      } else {
        setRoles([])
      }
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Unable to load users.')
    } finally {
      setLoading(false)
    }
  }, [canView, canViewRoles, page, search, status, token])

  useEffect(() => { void load() }, [load])
  const close = () => { setForm(null); setFormError('') }
  const submit = async (payload: UserRequest) => {
    setSaving(true); setFormError('')
    try {
      if (form?.mode === 'create') await createUser(payload, token)
      else if (form?.user) await updateUser(form.user.public_id, payload, token)
      close(); await load()
    } catch (exception) { setFormError(exception instanceof Error ? exception.message : 'Unable to save user.') }
    finally { setSaving(false) }
  }
  const deactivate = async (user: User) => {
    if (!window.confirm(`Deactivate ${user.name}?`)) return
    try { await deactivateUser(user.public_id, token); await load() }
    catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to deactivate user.') }
  }
  const reactivate = async (user: User) => {
    if (!window.confirm(`Reactivate ${user.name}?`)) return
    try { await reactivateUser(user.public_id, token); await load() }
    catch (exception) { setError(exception instanceof Error ? exception.message : 'Unable to reactivate user.') }
  }
  const changeStatus = (next: UserStatusFilter) => { setPage(1); setStatus(next) }
  const changeSearch = (next: string) => { setPage(1); setSearch(next) }

  return <div className="flex flex-col gap-4">
    <div className="billing-page-heading flex items-center justify-between gap-4"><div><h1 className="text-sm font-semibold">Users</h1><p className="mt-1 text-xs text-muted-foreground">Manage organization accounts and access.</p></div>{canCreate && <Button onClick={() => setForm({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New user</Button>}</div>
    {error && <Alert variant="destructive"><AlertTitle>Could not load users</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    <Card className="billing-records overflow-hidden"><CardHeader className="flex flex-col gap-3 border-b sm:flex-row sm:items-center sm:justify-between"><CardTitle>Organization users <span className="ml-2 rounded-sm bg-muted px-1.5 py-0.5 font-mono text-[10px] font-normal text-muted-foreground">{users.length}</span></CardTitle><div className="flex flex-wrap gap-2"><Input aria-label="Search users" className="h-8 w-56" placeholder="Search name or email" value={search} onChange={event => changeSearch(event.target.value)} /><select aria-label="Filter users by status" className="h-8 border border-input bg-transparent px-2 text-xs" value={status} onChange={event => changeStatus(event.target.value as UserStatusFilter)}><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select><Button variant="outline" size="sm" onClick={() => void load()}><ArrowClockwiseIcon data-icon="inline-start" />Refresh</Button></div></CardHeader>
      <CardContent className="p-0"><Table className="min-w-[820px]"><TableHeader><TableRow><TableHead>User</TableHead><TableHead>Roles</TableHead><TableHead>Status</TableHead><TableHead>Created</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>
        {loading ? <TableRow><TableCell colSpan={5} className="h-36 text-center text-muted-foreground">Loading users…</TableCell></TableRow> : users.length ? users.map(user => <TableRow key={user.public_id}><TableCell><div className="font-medium">{user.name}</div><div className="text-[10px] text-muted-foreground">{user.email}</div></TableCell><TableCell>{user.roles.length ? user.roles.map(role => role.name).join(', ') : 'No roles'}</TableCell><TableCell><span className={user.membership_status === 'inactive' ? 'rounded-sm bg-amber-500/10 px-1.5 py-1 text-[10px] font-medium text-amber-700 dark:text-amber-300' : 'rounded-sm bg-emerald-500/10 px-1.5 py-1 text-[10px] font-medium text-emerald-700 dark:text-emerald-300'}>{user.membership_status || user.status}</span></TableCell><TableCell>{user.created_at ? new Date(user.created_at).toLocaleDateString() : '—'}</TableCell><TableCell className="text-right"><div className="flex justify-end gap-1">{canUpdate && <Button variant="ghost" size="icon-sm" aria-label={`Edit ${user.name}`} onClick={() => setForm({ mode: 'edit', user })}><PencilSimpleIcon /></Button>}{user.membership_status === 'inactive' ? canUpdate && <Button variant="ghost" size="sm" onClick={() => void reactivate(user)}><UserPlusIcon data-icon="inline-start" />Reactivate</Button> : canDelete && <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => void deactivate(user)}><UserMinusIcon data-icon="inline-start" />Deactivate</Button>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={5} className="h-36 text-center text-muted-foreground">No users found.</TableCell></TableRow>}
      </TableBody></Table>{!loading && lastPage > 1 && <div className="flex items-center justify-between border-t px-3 py-2 text-xs text-muted-foreground"><span>Page {page} of {lastPage}</span><div className="flex gap-2"><Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(current => current - 1)}>Previous</Button><Button variant="outline" size="sm" disabled={page === lastPage} onClick={() => setPage(current => current + 1)}>Next</Button></div></div>}</CardContent>
    </Card><UserForm open={form !== null} mode={form?.mode || 'create'} user={form?.user} roles={roles} error={formError} loading={saving} onClose={close} onSubmit={submit} />
  </div>
}
