import { ArchiveBoxIcon, EyeIcon, PencilSimpleIcon, ProhibitIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { useConfirm } from './ConfirmProvider'

type ActionKind = 'operational' | 'financial'

export function TableActions({ label, kind, onArchive, onView, onEdit, onVoid }: {
  label: string
  kind: ActionKind
  onArchive?: () => void
  onView?: () => void
  onEdit?: () => void
  onVoid?: () => void
}) {
  const confirm = useConfirm()
  const requestConfirmation = async (action: 'archive' | 'void') => {
    const labelText = action === 'archive' ? 'Archive' : 'Void'
    const confirmed = await confirm({ title: `${labelText} ${label}?`, description: 'This action changes the record status and cannot be undone from this menu.', confirmLabel: labelText, destructive: true })
    if (confirmed) (action === 'archive' ? onArchive : onVoid)?.()
  }

  return <div className="inline-flex items-center justify-end gap-1">
    <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`View ${label}`} title="View" onClick={onView}><EyeIcon /></Button>
    {kind === 'operational' && <>
      <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={`Edit ${label}`} title="Edit" onClick={onEdit}><PencilSimpleIcon /></Button>
      <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Archive ${label}`} title="Archive" onClick={() => void requestConfirmation('archive')}><ArchiveBoxIcon /></Button>
    </>}
    {kind === 'financial' && <Button type="button" variant="ghost" size="icon-sm" className="rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label={`Void ${label}`} title="Void" onClick={() => void requestConfirmation('void')}><ProhibitIcon /></Button>}
  </div>
}
