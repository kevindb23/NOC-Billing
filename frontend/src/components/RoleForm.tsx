import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { XIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { PermissionMatrix } from './PermissionMatrix'
import type { PermissionGroup, Role, RoleRequest } from '@/lib/usersRoles'

export function RoleForm({ open, mode, role, groups, error, loading, onClose, onSubmit }: { open: boolean; mode: 'create' | 'edit'; role?: Role; groups: PermissionGroup[]; error?: string; loading?: boolean; onClose: () => void; onSubmit: (payload: RoleRequest) => Promise<void> }) {
  const [name, setName] = useState(role?.name || ''); const [selectedIds, setSelectedIds] = useState<number[]>(role?.permission_ids || Object.values(role?.permissions || {}).flat())
  useEffect(() => { if (!open) return; setName(role?.name || ''); setSelectedIds(role?.permission_ids || Object.values(role?.permissions || {}).flat()) }, [mode, open, role])
  if (!open) return null
  const submit = async (event: FormEvent) => { event.preventDefault(); await onSubmit({ name, permission_ids: selectedIds }) }
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="role-form-title"><Card className="billing-modal-card relative max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto shadow-2xl"><CardHeader className="border-b pr-14"><p className="billing-modal-eyebrow">{mode === 'create' ? 'Create role' : 'Update role'}</p><CardTitle id="role-form-title">{mode === 'create' ? 'New role' : 'Edit role'}</CardTitle><CardDescription>Set a clear name and the permissions this role can use.</CardDescription><Button type="button" variant="ghost" size="icon-sm" className="absolute right-4 top-4" onClick={onClose} aria-label="Close role form"><XIcon /></Button></CardHeader><CardContent className="pt-5"><form onSubmit={submit}><FieldGroup><Field><FieldLabel htmlFor="role-name">Role name</FieldLabel><Input id="role-name" value={name} onChange={event => setName(event.target.value)} required /></Field><Field><FieldLabel>Permissions</FieldLabel><PermissionMatrix groups={groups} selectedIds={selectedIds} onChange={setSelectedIds} /></Field></FieldGroup>{error && <p className="mt-4 text-xs text-destructive">{error}</p>}<div className="mt-6 flex items-center justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={loading}>{loading ? 'Saving…' : mode === 'create' ? 'Create role' : 'Save changes'}</Button></div></form></CardContent></Card></div>
}
