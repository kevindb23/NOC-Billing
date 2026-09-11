import { useMemo } from 'react'
import type { PermissionGroup } from '@/lib/usersRoles'

export function PermissionMatrix({ groups, selectedIds, onChange }: { groups: PermissionGroup[]; selectedIds: number[]; onChange: (ids: number[]) => void }) {
  const selected = useMemo(() => new Set(selectedIds), [selectedIds])
  const toggle = (id: number) => onChange(selected.has(id) ? selectedIds.filter(selectedId => selectedId !== id) : [...selectedIds, id])
  const toggleGroup = (ids: number[]) => {
    const allSelected = ids.every(id => selected.has(id))
    onChange(allSelected ? selectedIds.filter(id => !ids.includes(id)) : [...new Set([...selectedIds, ...ids])])
  }

  return <div className="overflow-x-auto rounded-sm border border-border/70">
    <table className="min-w-[620px] w-full text-xs">
      <thead><tr className="border-b bg-muted/35"><th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Permission group</th><th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Actions</th></tr></thead>
      <tbody>{groups.map(group => {
        const ids = group.permissions.map(permission => permission.id)
        return <tr className="border-b last:border-0" key={group.group}>
          <td className="w-40 px-3 py-3 align-top font-medium"><label className="flex items-center gap-2"><input type="checkbox" aria-label={`Select all ${group.group} permissions`} checked={ids.length > 0 && ids.every(id => selected.has(id))} onChange={() => toggleGroup(ids)} />{group.group}</label></td>
          <td className="px-3 py-3"><div className="flex flex-wrap gap-x-5 gap-y-2">{group.permissions.map(permission => <label className="flex items-center gap-2 text-muted-foreground" key={permission.id}><input type="checkbox" aria-label={permission.name} checked={selected.has(permission.id)} onChange={() => toggle(permission.id)} />{permission.action}</label>)}</div></td>
        </tr>
      })}</tbody>
    </table>
  </div>
}
