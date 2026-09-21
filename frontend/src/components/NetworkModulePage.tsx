import { useState } from 'react'
import { WifiHighIcon } from '@phosphor-icons/react'
import { AuditLogsPage } from './AuditLogsPage'
import { RoutersPage } from './RoutersPage'
import { OltPage } from './OltPage'
import { OltManagementPage } from './OltManagementPage'
import { BngPage } from './BngPage'
import { BngManagementPage } from './BngManagementPage'
import { AcsServerPage } from './AcsServerPage'
import { OntPage } from './OntPage'
import { OntManagementPage } from './OntManagementPage'
import { hasPermission } from '../lib/usersRoles'

export function NetworkModulePage({ module, section = 'Network', token, permissions, isSuperadmin }: { module: string; section?: string; token?: string; permissions?: string[]; isSuperadmin?: boolean }) {
  const [managedOlt, setManagedOlt] = useState<string | null>(null)
  const [managedBng, setManagedBng] = useState<string | null>(() => module === 'BNG' ? window.location.pathname.match(/^\/bngs\/([^/]+)/)?.[1] || null : null)
  const [managedOnt, setManagedOnt] = useState<string | null>(() => module === 'ONT' ? window.location.pathname.match(/^\/ont\/([^/]+)\/manage$/i)?.[1] || null : null)
  if (module === 'Audit Logs') return <AuditLogsPage />
  if (module === 'ACS Server' && token) return <AcsServerPage token={token} permissions={permissions} isSuperadmin={isSuperadmin} />
  if (module === 'ONT' && token) {
    if (managedOnt) {
      const canManage = isSuperadmin !== false || hasPermission(permissions, 'onts.update')
      return <OntManagementPage token={token} publicId={managedOnt} canManage={canManage} onBack={() => { setManagedOnt(null); window.history.pushState({}, '', '/ont') }} />
    }
    return <OntPage token={token} permissions={permissions} isSuperadmin={isSuperadmin} onManage={publicId => { setManagedOnt(publicId); window.history.pushState({}, '', '/ont/' + publicId + '/manage') }} />
  }
  if (module === 'Routers' && token) return <RoutersPage token={token} permissions={permissions} isSuperadmin={isSuperadmin} />
  if (module === 'BNG' && token) return managedBng ? <BngManagementPage token={token} publicId={managedBng} onBack={() => { setManagedBng(null); window.history.pushState({}, '', '/bng') }} /> : <BngPage token={token} onManage={publicId => { setManagedBng(publicId); window.history.pushState({}, '', `/bngs/${publicId}`) }} />
  if (module === 'OLT' && token) return managedOlt ? <OltManagementPage token={token} publicId={managedOlt} permissions={permissions} isSuperadmin={isSuperadmin} onBack={() => setManagedOlt(null)} /> : <OltPage token={token} permissions={permissions} isSuperadmin={isSuperadmin} onManage={row => setManagedOlt(row.public_id)} />
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
