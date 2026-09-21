import { useEffect, useState } from 'react'

type CrudOperationDetail = { id: number; method: string; path: string; title: string; description: string }

export function GlobalCrudLoader() {
  const [operations, setOperations] = useState<CrudOperationDetail[]>([])

  useEffect(() => {
    const start = (event: Event) => {
      const detail = (event as CustomEvent<CrudOperationDetail>).detail
      if (detail) setOperations(current => [...current, detail])
    }
    const end = (event: Event) => {
      const detail = (event as CustomEvent<CrudOperationDetail>).detail
      if (detail) setOperations(current => current.filter(operation => operation.id !== detail.id))
    }
    window.addEventListener('crud-operation:start', start)
    window.addEventListener('crud-operation:end', end)
    return () => {
      window.removeEventListener('crud-operation:start', start)
      window.removeEventListener('crud-operation:end', end)
    }
  }, [])

  const operation = operations[operations.length - 1]
  if (!operation) return null

  return <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/35 p-4" role="status" aria-live="polite" aria-busy="true">
    <div className="olt-operation-loader w-full max-w-xs border border-border bg-background px-5 py-4 shadow-xl">
      <div className="flex items-center gap-3">
        <div className="olt-operation-loader__mark" aria-hidden="true"><span className="olt-operation-loader__orbit" /><span className="olt-operation-loader__bars"><span className="olt-operation-loader__bar" /><span className="olt-operation-loader__bar" /><span className="olt-operation-loader__bar" /><span className="olt-operation-loader__bar" /></span></div>
        <div className="min-w-0"><p className="text-sm font-semibold">{operation.title}</p><p className="mt-1 text-xs text-muted-foreground">{operation.description}</p></div>
      </div>
      <div className="olt-operation-loader__track mt-3" aria-hidden="true" />
    </div>
  </div>
}
