import { useMemo } from 'react'
import type { PermissionGroup } from '@/lib/usersRoles'

const actions = ['view', 'create', 'update', 'delete', 'test', 'export'] as const
const legacyModules = [
  ['Dashboard', 'dashboard'],
  ['Billing', 'billing'],
  ['Network', 'network'],
  ['System', 'system'],
  ['Users', 'users'],
  ['Roles', 'roles'],
  ['Branding', 'branding'],
  ['Audit Logs', 'audit-logs'],
] as const

function normalizeGroups(groups: PermissionGroup[]): PermissionGroup[] {
  const legacySystem = groups.find(group => group.group === 'System')
  const explicitGroups = new Map(groups.filter(group => group.group !== 'System').map(group => [group.group, group.permissions]))
  const dynamicGroups = [...explicitGroups.keys()].filter(group => !legacyModules.some(([label]) => label === group))

  return [...legacyModules.map(([group, prefix]) => [group, prefix] as const), ...dynamicGroups.map(group => [group, group.toLowerCase().replaceAll(' ', '-')] as const)].map(([group, prefix]) => {
    const permissions = [
      ...(explicitGroups.get(group) || []),
      ...(legacySystem?.permissions.filter(permission => permission.name.startsWith(`${prefix}.`)) || []),
    ]
    return { group, permissions: [...new Map(permissions.map(permission => [permission.id, permission])).values()] }
  })
}

export function PermissionMatrix({ groups, selectedIds, onChange }: { groups: PermissionGroup[]; selectedIds: number[]; onChange: (ids: number[]) => void }) {
  const selected = useMemo(() => new Set(selectedIds), [selectedIds])
  const normalizedGroups = useMemo(() => normalizeGroups(groups), [groups])
  const toggle = (id: number) => onChange(selected.has(id) ? selectedIds.filter(selectedId => selectedId !== id) : [...selectedIds, id])
  const toggleGroup = (ids: number[]) => {
    const allSelected = ids.every(id => selected.has(id))
    onChange(allSelected ? selectedIds.filter(id => !ids.includes(id)) : [...new Set([...selectedIds, ...ids])])
  }

  return <div className="billing-modal-scroll max-h-[min(42vh,26rem)] overflow-auto rounded-lg border border-border/70 bg-card">
    <table className="min-w-[720px] w-full table-fixed text-xs">
      <colgroup><col className="w-[40%]" />{actions.map(action => <col className="w-[12%]" key={action} />)}</colgroup>
      <thead><tr className="border-b bg-muted/35"><th className="px-3 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Permission group</th>{actions.map(action => <th className="px-2 py-2.5 text-center font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground" key={action}>{action}</th>)}</tr></thead>
      <tbody>{normalizedGroups.map(group => {
        const ids = group.permissions.map(permission => permission.id)
        const permissionsByAction = new Map(group.permissions.map(permission => [permission.action, permission]))
        return <tr className="border-b last:border-0" key={group.group}>
          <td className="px-3 py-3 font-medium"><label className="flex items-center gap-2"><input type="checkbox" aria-label={`Select all ${group.group} permissions`} checked={ids.length > 0 && ids.every(id => selected.has(id))} onChange={() => toggleGroup(ids)} />{group.group}</label></td>
          {actions.map(action => { const permission = permissionsByAction.get(action); return <td className="px-2 py-3 text-center" key={action}>{permission ? <label className="inline-flex items-center justify-center"><input type="checkbox" aria-label={permission.name} checked={selected.has(permission.id)} onChange={() => toggle(permission.id)} /></label> : <span className="text-muted-foreground/35" aria-hidden="true">—</span>}</td> })}
        </tr>
      })}</tbody>
    </table>
  </div>
}
