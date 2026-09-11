import { useState } from 'react'
import { ArchiveBoxIcon, EyeIcon, PencilSimpleIcon, ProhibitIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'

type ActionKind = 'operational' | 'financial'

export function TableActions({ label, kind, onArchive, onView, onEdit, onVoid }: {
  label: string
  kind: ActionKind
  onArchive?: () => void
  onView?: () => void
  onEdit?: () => void
  onVoid?: () => void
}) {
  const [confirming, setConfirming] = useState<'archive' | 'void' | null>(null)
  const confirm = confirming === 'archive' ? onArchive : onVoid

  return <div className="inline-flex items-center justify-end gap-1">
    <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`View ${label}`} title="View" onClick={onView}><EyeIcon /></Button>
    {kind === 'operational' && <>
      <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`Edit ${label}`} title="Edit" onClick={onEdit}><PencilSimpleIcon /></Button>
      <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Archive ${label}`} title="Archive" onClick={() => setConfirming('archive')}><ArchiveBoxIcon /></Button>
    </>}
    {kind === 'financial' && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Void ${label}`} title="Void" onClick={() => setConfirming('void')}><ProhibitIcon /></Button>}
    {confirming && <div className="fixed inset-0 z-50 grid place-items-center bg-foreground/20 p-4" role="dialog" aria-modal="true" aria-labelledby="table-action-confirmation"><div className="w-full max-w-sm rounded-md bg-card p-5 shadow-2xl"><h2 id="table-action-confirmation" className="font-heading text-base font-semibold">{confirming === 'archive' ? `Archive ${label}?` : `Void ${label}?`}</h2><p className="mt-2 text-xs leading-5 text-muted-foreground">This action changes the record status and cannot be undone from this menu.</p><div className="mt-5 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setConfirming(null)}>Cancel</Button><Button type="button" variant="destructive" aria-label={confirming === 'archive' ? 'Confirm archive' : 'Confirm void'} onClick={() => { confirm?.(); setConfirming(null) }}>{confirming === 'archive' ? 'Archive' : 'Void'}</Button></div></div></div>}
  </div>
}
