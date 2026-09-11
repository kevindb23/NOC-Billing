import { WifiHighIcon } from '@phosphor-icons/react'
import { AuditLogsPage } from './AuditLogsPage'

export function NetworkModulePage({ module, section = 'Network' }: { module: string; section?: string }) {
  if (module === 'Audit Logs') return <AuditLogsPage />
  return (
    <div className="flex flex-col gap-5">
      <div className="billing-page-heading flex items-center justify-between gap-4">
        <h1 className="text-sm font-semibold">{module}</h1>
      </div>
      <div className="grid min-h-56 place-items-center rounded-lg border border-dashed bg-background/72">
        <div className="flex flex-col items-center gap-2 text-center">
          <WifiHighIcon size={24} className="text-muted-foreground/50" aria-hidden="true" />
          <div>
            <p className="text-sm font-semibold">{module}</p>
            <p className="mt-1 text-xs text-muted-foreground">{section} module ready for configuration.</p>
          </div>
        </div>
      </div>
    </div>
  )
}
