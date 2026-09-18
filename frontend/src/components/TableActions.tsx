import { ArchiveBoxIcon, EyeIcon, PencilSimpleIcon, PlugIcon, ProhibitIcon, StopIcon, TrashIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { useConfirm } from './ConfirmProvider'

type ActionKind = 'operational' | 'financial'

export function TableActions({ label, kind, onArchive, onDelete, onView, onEdit, onVoid, onManage, connected, onConnect }: {
  label: string
  kind: ActionKind
  onArchive?: () => void
  onDelete?: () => void
  onView?: () => void
  onEdit?: () => void
  onVoid?: () => void
  onManage?: () => void
  connected?: boolean
  onConnect?: () => void
}) {
  const confirm = useConfirm()
  const requestConfirmation = async (action: 'archive' | 'delete' | 'void') => {
    const labelText = action === 'archive' ? 'Archive' : action === 'delete' ? 'Delete permanently' : 'Void'
    const description = action === 'delete'
      ? 'This permanently removes the record and cannot be undone.'
      : 'This action changes the record status and cannot be undone from this menu.'
    const confirmed = await confirm({ title: `${labelText} ${label}?`, description, confirmLabel: labelText, destructive: true })

    if (confirmed) (action === 'archive' ? onArchive : action === 'delete' ? onDelete : onVoid)?.()
  }

  return <div className="inline-flex items-center justify-end gap-1">
    {onManage && <Button type="button" variant="outline" size="sm" onClick={onManage}>Manage</Button>}
    {onConnect && <Button type="button" variant="ghost" size="icon-sm" className={connected ? 'rounded-md text-destructive hover:bg-destructive/10' : 'rounded-md text-muted-foreground hover:bg-primary/10 hover:text-primary'} aria-label={connected ? `Stop connection to ${label}` : `Connect to ${label}`} title={connected ? 'Stop connection' : 'Connect'} onClick={onConnect}>{connected ? <StopIcon /> : <PlugIcon />}</Button>}
    {onView && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`View ${label}`} title="View" onClick={onView}><EyeIcon /></Button>}
    {kind === 'operational' && <>
      {onEdit && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`Edit ${label}`} title="Edit" onClick={onEdit}><PencilSimpleIcon /></Button>}
      {onArchive && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Archive ${label}`} title="Archive" onClick={() => void requestConfirmation('archive')}><ArchiveBoxIcon /></Button>}
    </>}
    {onDelete && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Delete ${label} permanently`} title="Delete permanently" onClick={() => void requestConfirmation('delete')}><TrashIcon /></Button>}
    {kind === 'financial' && onVoid && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Void ${label}`} title="Void" onClick={() => void requestConfirmation('void')}><ProhibitIcon /></Button>}
  </div>
}
