import { useEffect, useMemo, useState } from 'react'

export function useTableSelection<T>(rows: T[], getId: (row: T) => string) {
  const ids = useMemo(() => rows.map(getId), [getId, rows])
  const [selected, setSelected] = useState<Set<string>>(new Set())

  useEffect(() => {
    setSelected(current => {
      const allowed = new Set(ids)
      const next = new Set([...current].filter(id => allowed.has(id)))
      return next.size === current.size ? current : next
    })
  }, [ids])

  const toggle = (id: string) => setSelected(current => {
    const next = new Set(current)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    return next
  })
  const toggleAll = () => setSelected(current => current.size === ids.length ? new Set() : new Set(ids))
  const clear = () => setSelected(new Set())

  return { ids, selected, toggle, toggleAll, clear, allSelected: ids.length > 0 && selected.size === ids.length }
}

export function SelectAllCheckbox({ checked, onChange, label = 'Select all rows' }: { checked: boolean; onChange: () => void; label?: string }) {
  return <input type="checkbox" aria-label={label} checked={checked} onChange={onChange} className="size-4 accent-primary" />
}

export function RowCheckbox({ checked, onChange, label }: { checked: boolean; onChange: () => void; label: string }) {
  return <input type="checkbox" aria-label={label} checked={checked} onChange={onChange} className="size-4 accent-primary" />
}
