import { ArrowsClockwiseIcon, BroadcastIcon, GearSixIcon, PulseIcon } from '@phosphor-icons/react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { hasPermission } from '@/lib/usersRoles'

export type RouterOperation = {
  operation: string
  status: string
  correlation_id?: string | null
  result?: unknown
  error_message?: string | null
}

export type RouterOperationRouter = {
  name: string
  public_id: string
}

type RouterOperationPanelProps = {
  router: RouterOperationRouter
  capabilities: string[]
  permissions?: string[]
  isSuperadmin?: boolean
  lastOperation?: RouterOperation | null
  loadingOperation?: string | null
  onOperation: (operation: string) => void
}

const monitoringOperations = [
  ['test_connection', 'Test connection'],
  ['get_system_info', 'System information'],
  ['get_device_facts', 'Device facts'],
  ['get_interfaces', 'Interfaces'],
  ['get_interface_status', 'Interface status'],
  ['get_routes', 'Routes'],
  ['get_bgp_neighbors', 'BGP neighbors'],
  ['get_traffic_counters', 'Traffic counters'],
] as const

const configurationOperations = [
  ['validate_configuration', 'Validate'],
  ['preview_configuration', 'Preview'],
  ['apply_configuration', 'Apply'],
  ['commit_configuration', 'Commit'],
  ['rollback_configuration', 'Rollback'],
] as const

function normalizedCapabilities(capabilities: string[]) {
  return new Set(capabilities.map(capability => capability.toLowerCase().replaceAll('-', '_')))
}

function supports(capabilities: Set<string>, operation: string) {
  const aliases: Record<string, string[]> = {
    test_connection: ['test_connection', 'connection_test'],
    get_system_info: ['get_system_info', 'system_info'],
  }
  return (aliases[operation] || [operation]).some(capability => capabilities.has(capability))
}

function operationPermission(operation: string) {
  if (operation === 'test_connection' || operation === 'get_system_info') return ['routers.monitor', 'routers.test']
  if (operation.startsWith('get_')) return ['routers.monitor']
  if (operation === 'validate_configuration' || operation === 'preview_configuration') return ['routers.configuration.preview']
  return [`routers.configuration.${operation.replace('_configuration', '')}`]
}

function safeForDisplay(value: unknown): unknown {
  if (Array.isArray(value)) return value.slice(0, 50).map(item => safeForDisplay(item))
  if (typeof value !== 'object' || value === null) return typeof value === 'string' && value.length > 300 ? `${value.slice(0, 300)}…` : value
  return Object.fromEntries(Object.entries(value).slice(0, 50).filter(([key]) => !/(password|passphrase|token|secret|community|private.?key|authorization|cookie|credential)/i.test(key)).map(([key, item]) => [key, safeForDisplay(item)]))
}

function statusBadge(status: string) {
  const variant = status === 'succeeded' ? 'default' : status === 'failed' ? 'destructive' : 'secondary'
  return <Badge variant={variant}>{status.replaceAll('_', ' ')}</Badge>
}

export function RouterOperationPanel({ router, capabilities, permissions, isSuperadmin, lastOperation, loadingOperation = null, onOperation }: RouterOperationPanelProps) {
  const allowed = (operation: string) => isSuperadmin === true || operationPermission(operation).some(permission => hasPermission(permissions, permission))
  const available = normalizedCapabilities(capabilities)
  const monitoring = monitoringOperations.filter(([operation]) => supports(available, operation) && allowed(operation))
  const configuration = configurationOperations.filter(([operation]) => supports(available, operation) && allowed(operation))
  if (monitoring.length === 0 && configuration.length === 0 && !lastOperation) return null

  return <section className="rounded-md border bg-muted/20 p-4" aria-label={`Router operations for ${router.name}`}>
    <div className="flex items-start gap-3">
      <div className="grid size-8 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><PulseIcon size={16} /></div>
      <div><h3 className="text-sm font-semibold">Router operations</h3><p className="mt-1 text-xs text-muted-foreground">Run vendor-neutral actions through the configured driver and transport.</p></div>
    </div>
    {monitoring.length > 0 && <div className="mt-4"><div className="mb-2 flex items-center gap-2 text-xs font-medium"><BroadcastIcon size={14} />Monitoring</div><div className="flex flex-wrap gap-2">{monitoring.map(([operation, label]) => <Button type="button" variant="outline" size="sm" key={operation} onClick={() => onOperation(operation)} disabled={loadingOperation !== null}>{loadingOperation === operation && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{label}</Button>)}</div></div>}
    {configuration.length > 0 && <div className="mt-4"><div className="mb-2 flex items-center gap-2 text-xs font-medium"><GearSixIcon size={14} />Configuration</div><div className="flex flex-wrap gap-2">{configuration.map(([operation, label]) => <Button type="button" variant="outline" size="sm" key={operation} onClick={() => onOperation(operation)} disabled={loadingOperation !== null}>{loadingOperation === operation && <ArrowsClockwiseIcon className="animate-spin" data-icon="inline-start" />}{label}</Button>)}</div></div>}
    {lastOperation && <div className="mt-4 rounded-sm border bg-background/70 p-3" aria-live="polite"><div className="flex flex-wrap items-center gap-2"><span className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Latest operation</span>{statusBadge(lastOperation.status)}<span className="text-xs">{lastOperation.operation.replaceAll('_', ' ')}</span></div>{lastOperation.correlation_id && <p className="mt-2 break-all font-mono text-[10px] text-muted-foreground">Correlation ID: {lastOperation.correlation_id}</p>}{lastOperation.error_message && <p className="mt-2 text-xs text-destructive">The operation failed. {lastOperation.error_message}</p>}{lastOperation.result !== undefined && lastOperation.result !== null && <pre className="mt-2 max-h-48 overflow-auto rounded-sm border bg-muted/30 p-2 text-[10px] leading-4">{JSON.stringify(safeForDisplay(lastOperation.result), null, 2)}</pre>}</div>}
  </section>
}
