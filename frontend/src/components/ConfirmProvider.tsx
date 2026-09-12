import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import { XIcon } from '@phosphor-icons/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export type ConfirmOptions = {
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  destructive?: boolean
}

type PendingConfirmation = {
  resolve: (confirmed: boolean) => void
}

const ConfirmContext = createContext<((options: ConfirmOptions) => Promise<boolean>) | null>(null)

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [options, setOptions] = useState<ConfirmOptions | null>(null)
  const pendingRef = useRef<PendingConfirmation | null>(null)

  const confirm = useCallback((nextOptions: ConfirmOptions) => new Promise<boolean>(resolve => {
    pendingRef.current = { resolve }
    setOptions(nextOptions)
  }), [])

  const settle = useCallback((confirmed: boolean) => {
    pendingRef.current?.resolve(confirmed)
    pendingRef.current = null
    setOptions(null)
  }, [])

  useEffect(() => {
    if (!options) return
    const focusTarget = document.getElementById('confirm-dialog-action')
    focusTarget?.focus()
  }, [options])

  return <ConfirmContext.Provider value={confirm}>
    {children}
    {options && <div className="billing-modal-backdrop fixed inset-0 z-[60] grid place-items-center overflow-y-auto p-4" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) settle(false) }}>
      <Card className="billing-modal-card relative w-full max-w-md shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-description">
        <CardHeader className="billing-modal-header border-b pr-14">
          <p className="billing-modal-eyebrow">Please confirm</p>
          <CardTitle id="confirm-dialog-title">{options.title}</CardTitle>
          <p id="confirm-dialog-description" className="text-xs text-muted-foreground">{options.description}</p>
          <Button type="button" variant="ghost" size="icon-sm" className="billing-modal-close absolute right-4 top-4" onClick={() => settle(false)} aria-label="Close confirmation"><XIcon /></Button>
        </CardHeader>
        <CardContent className="billing-modal-content flex justify-end gap-2 border-t-0 pt-5">
          <Button type="button" variant="outline" onClick={() => settle(false)}>{options.cancelLabel || 'Cancel'}</Button>
          <Button id="confirm-dialog-action" type="button" variant={options.destructive ? 'destructive' : 'default'} onClick={() => settle(true)}>{options.confirmLabel || 'Confirm'}</Button>
        </CardContent>
      </Card>
    </div>}
  </ConfirmContext.Provider>
}

export function useConfirm() {
  const confirm = useContext(ConfirmContext)
  return confirm || (async () => false)
}
