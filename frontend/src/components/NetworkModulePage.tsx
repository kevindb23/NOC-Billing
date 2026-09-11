import { WifiHighIcon } from '@phosphor-icons/react'

export function NetworkModulePage({ module, section = 'Network' }: { module: string; section?: string }) {
  return (
    <div className="flex flex-col gap-8">
      <div className="billing-module-header">
        <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">{section} operations</p>
        <h2 className="mt-2 font-heading text-2xl font-semibold tracking-tight sm:text-3xl">{module}</h2>
        <p className="mt-2 text-sm text-muted-foreground">Review configuration readiness for this {section.toLowerCase()} module.</p>
      </div>
      <div className="billing-records border-y border-border/60 py-14">
        <div className="mx-auto flex max-w-md flex-col items-center text-center">
          <WifiHighIcon size={20} className="text-muted-foreground/50" aria-hidden="true" />
          <p className="mt-4 text-sm font-semibold">Ready for configuration</p>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">Configuration controls and records will appear here when this module is enabled.</p>
        </div>
      </div>
    </div>
  )
}
