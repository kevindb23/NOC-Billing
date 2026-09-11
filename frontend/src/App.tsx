import { useEffect, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import {
  BellIcon,
  BellRingingIcon,
  ArrowsLeftRightIcon,
  CaretRightIcon,
  ClipboardTextIcon,
  CloudIcon,
  ChartLineUpIcon,
  CheckCircleIcon,
  CreditCardIcon,
  CurrencyDollarIcon,
  DesktopTowerIcon,
  GearIcon,
  HardDriveIcon,
  HouseIcon,
  InvoiceIcon,
  MagnifyingGlassIcon,
  EnvelopeSimpleIcon,
  KeyIcon,
  PaintBrushIcon,
  ReceiptIcon,
  NetworkIcon,
  PackageIcon,
  SignOutIcon,
  ShareNetworkIcon,
  SidebarSimpleIcon,
  ShieldCheckIcon,
  TagIcon,
  UserGearIcon,
  UsersThreeIcon,
  WifiHighIcon,
} from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { apiRequest } from './lib/api'
import { readStoredView, type View } from './lib/viewState'
import { NetworkModulePage } from './components/NetworkModulePage'
import { ResourceTablePage } from './components/ResourceTablePage'
import { SubscribersPage } from './components/SubscribersPage'
import { networkModules, type NetworkModule } from './lib/networkModules'
import { systemModules, type SystemModule } from './lib/systemModules'

type Session = { token: string; user: { name: string; email: string } }
type AppView = View | NetworkModule | SystemModule
type NavSection = 'Dashboard' | 'Billing' | 'Network' | 'System'
type NavItem = { key: AppView; label: string; section: NavSection }
type IconName = AppView

const BRAND_NAME = import.meta.env.VITE_BRAND_NAME || '1wan - Horizon Gateway'
const BRAND_MARK = import.meta.env.VITE_BRAND_MARK || '1W'

const nav: NavItem[] = [
  { key: 'overview', label: 'Overview', section: 'Dashboard' },
  { key: 'subscribers', label: 'Subscribers', section: 'Dashboard' },
  { key: 'accounts', label: 'Billing accounts', section: 'Billing' },
  { key: 'plans', label: 'Plans', section: 'Billing' },
  { key: 'services', label: 'Subscriber services', section: 'Billing' },
  { key: 'subscriptions', label: 'Subscriptions', section: 'Billing' },
  { key: 'invoices', label: 'Invoices', section: 'Billing' },
  { key: 'payments', label: 'Payments', section: 'Billing' },
  ...networkModules.map(module => ({ key: module, label: module, section: 'Network' as const })),
  ...systemModules.map(module => ({ key: module, label: module, section: 'System' as const })),
]

function readStoredAppView(): AppView {
  const stored = localStorage.getItem('isp-view')
  return isModuleView(stored as AppView) ? stored as AppView : readStoredView()
}

function isNetworkModule(view: AppView): view is NetworkModule {
  return networkModules.includes(view as NetworkModule)
}

function isModuleView(view: AppView): view is NetworkModule | SystemModule {
  return isNetworkModule(view) || systemModules.includes(view as SystemModule)
}

const navSections = ['Dashboard', 'Billing', 'Network', 'System'] as const

function openOnlySection(section: NavSection): Record<NavSection, boolean> {
  return {
    Dashboard: section === 'Dashboard',
    Billing: section === 'Billing',
    Network: section === 'Network',
    System: section === 'System',
  }
}

function App() {
  const [session, setSession] = useState<Session | null>(() => JSON.parse(localStorage.getItem('isp-session') || 'null'))
  const [view, setView] = useState<AppView>(readStoredAppView)
  const [error, setError] = useState('')
  useEffect(() => { document.title = BRAND_NAME }, [])
  useEffect(() => { localStorage.setItem('isp-view', view) }, [view])
  const signIn = (next: Session) => { localStorage.setItem('isp-session', JSON.stringify(next)); setSession(next); setError('') }
  const signOut = async () => { if (session) await apiRequest('/auth/logout', { method: 'POST' }, session.token).catch(() => undefined); localStorage.removeItem('isp-session'); setSession(null) }

  if (!session) return <Login onSignedIn={signIn} error={error} setError={setError} />
  return <Shell session={session} view={view} setView={setView} signOut={signOut} />
}

function Login({ onSignedIn, error, setError }: { onSignedIn: (s: Session) => void; error: string; setError: (s: string) => void }) {
  const [email, setEmail] = useState('admin@example.com')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setLoading(true); setError('')
    try { const response = await apiRequest<{ data: Session }>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }); onSignedIn(response.data) }
    catch (e) { setError(e instanceof Error ? e.message : 'Unable to sign in.') }
    finally { setLoading(false) }
  }

  return <main className="min-h-screen bg-background p-0 text-foreground sm:p-3">
    <div className="grid min-h-screen overflow-hidden bg-card shadow-[0_30px_90px_-48px_rgb(24_35_54_/_65%)] sm:min-h-[calc(100vh-1.5rem)] sm:rounded-[2rem] lg:grid-cols-[minmax(26rem,0.95fr)_1.05fr]">
      <section className="flex flex-col bg-foreground px-6 py-7 text-background sm:px-10 lg:px-14 lg:py-9">
        <div className="flex items-center gap-3"><BrandMark className="rounded-md bg-background text-foreground" /><div><p className="font-heading text-sm font-semibold tracking-tight">{BRAND_NAME}</p><p className="text-xs text-background/55">Billing operations</p></div></div>
        <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12 lg:py-8">
          <div className="mb-8 text-center"><h1 className="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">Sign in</h1><p className="mt-3 text-sm text-background/55">Enter your email and password to continue.</p></div>
          <form onSubmit={submit}><FieldGroup>
            <Field><FieldLabel className="text-background/75" htmlFor="email">Email</FieldLabel><Input className="h-11 border-background/15 bg-background px-4 text-foreground placeholder:text-muted-foreground focus-visible:border-background/50 focus-visible:ring-background/20" id="email" value={email} onChange={e => setEmail(e.target.value)} type="email" autoComplete="email" placeholder="admin@example.com" required /></Field>
            <Field><div className="flex items-center justify-between"><FieldLabel className="text-background/75" htmlFor="password">Password</FieldLabel><button className="text-xs text-background/70 underline-offset-4 hover:text-background hover:underline" type="button" onClick={() => setError('Ask your administrator to reset the account password.')}>Forgot your password?</button></div><Input className="h-11 border-background/15 bg-background px-4 text-foreground placeholder:text-muted-foreground focus-visible:border-background/50 focus-visible:ring-background/20" id="password" value={password} onChange={e => setPassword(e.target.value)} type="password" autoComplete="current-password" required placeholder="Enter password" /><FieldDescription className="px-1 text-background/45">Use the admin password configured on the server.</FieldDescription></Field>
            {error && <Alert variant="destructive" className="border-destructive/60 bg-destructive/10 text-background"><AlertTitle>Unable to sign in</AlertTitle><AlertDescription className="text-background/75">{error}</AlertDescription></Alert>}
            <Button type="submit" className="h-11 w-full bg-background text-foreground hover:bg-background/90" size="lg" disabled={loading}>{loading ? 'Authenticating…' : 'Sign in with email'}</Button>
          </FieldGroup></form>
          <p className="mt-7 text-center text-xs text-background/60">Need an account? <span className="text-background/85 underline underline-offset-4">Contact your administrator</span></p>
          <div className="my-7 flex items-center gap-3 text-[10px] uppercase tracking-[0.16em] text-background/35"><Separator className="flex-1 bg-background/15" /><span>Account access</span><Separator className="flex-1 bg-background/15" /></div>
          <p className="text-center text-[10px] leading-5 text-background/35">Billing records are available to authorized staff only.</p>
        </div>
        <p className="text-center text-[10px] text-background/35">{BRAND_NAME} · Billing milestone 01</p>
      </section>
      <section className="relative hidden overflow-hidden bg-muted lg:flex lg:flex-col lg:justify-between lg:px-16 lg:py-14 xl:px-24">
        <div className="relative z-10 max-w-xl"><Badge variant="outline" className="border-foreground/15 bg-background/50 text-[10px] uppercase tracking-[0.18em]">Billing operations hub</Badge><h2 className="mt-8 font-heading text-5xl font-semibold leading-[0.95] tracking-tight xl:text-7xl">Billing for<br /><span className="text-primary/65">your network.</span></h2><p className="mt-7 max-w-md text-sm leading-6 text-muted-foreground">View subscribers, plans, invoices, and payments in one place.</p><div className="mt-7 flex items-center gap-3"><div className="grid size-10 place-items-center rounded-full bg-foreground text-xs font-semibold text-background">{BRAND_MARK}</div><div><p className="text-xs font-medium">{BRAND_NAME} operations</p><p className="text-[10px] text-muted-foreground">Billing administration</p></div></div></div>
        <NetworkIllustration />
        <p className="relative z-10 text-xs text-muted-foreground/60">Billing records for authorized staff.</p>
      </section>
    </div>
  </main>
}

function BrandMark({ className }: { className?: string }) { return <div className={cn('grid size-9 shrink-0 place-items-center bg-primary text-xs font-bold text-primary-foreground', className)}>{BRAND_MARK}</div> }

function NetworkIllustration() {
  return <div className="pointer-events-none absolute inset-x-0 bottom-0 h-[56%] min-h-80 text-foreground/75" aria-hidden="true">
    <svg className="absolute inset-0 size-full" viewBox="0 0 800 560" fill="none" preserveAspectRatio="xMidYMax slice">
      <g className="text-foreground/15" stroke="currentColor" strokeWidth="2">
        <path d="M0 510 170 360 280 430 420 250 560 330 800 150" />
        <path d="M60 560 205 390 330 470 485 305 650 390 800 270" />
        <path d="M160 560 285 440 380 505 535 370 690 445 800 380" />
      </g>
      <g className="text-foreground/55" stroke="currentColor" strokeWidth="3">
        <path d="M72 560V445l74-60 74 60v115" />
        <path d="M270 560V310l88-62 88 62v250" />
        <path d="M500 560V185l104-74 104 74v375" />
        <path d="M675 560V350l62-44 63 44v210" />
      </g>
      <g className="text-foreground/30" stroke="currentColor" strokeWidth="2">
        <path d="M112 475v38m27-60v38m27-60v38m-54 70v38m27-60v38m27-60v38" />
        <path d="M315 350v45m30-66v45m30-66v45m-60 82v45m30-66v45m30-66v45m-60 82v45m30-66v45m30-66v45" />
        <path d="M550 235v52m34-76v52m34-76v52m-68 82v52m34-76v52m34-76v52m-68 82v52m34-76v52m34-76v52m-68 82v52m34-76v52m34-76v52" />
        <path d="M708 400v36m24-53v36m24-53v36m-48 46v36m24-53v36m24-53v36m-48 46v36m24-53v36m24-53v36" />
      </g>
      <g className="text-primary/70" stroke="currentColor" strokeWidth="2">
        <path d="M145 385 270 310 358 248 500 185 604 111" strokeDasharray="5 10" />
        <circle cx="145" cy="385" r="7" fill="currentColor" />
        <circle cx="358" cy="248" r="7" fill="currentColor" />
        <circle cx="604" cy="111" r="7" fill="currentColor" />
      </g>
    </svg>
  </div>
}

function Shell({ session, view, setView, signOut }: { session: Session; view: AppView; setView: (v: AppView) => void; signOut: () => void }) {
  const current = nav.find(item => item.key === view)
  const activeSection = current?.section ?? 'Dashboard'
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => localStorage.getItem('isp-sidebar-collapsed') === 'true')
  const [sidebarSearch, setSidebarSearch] = useState('')
  const [expandedSections, setExpandedSections] = useState<Record<NavSection, boolean>>(() => {
    const stored = localStorage.getItem('isp-expanded-sections')
    if (stored) {
      try {
        const parsed = JSON.parse(stored) as Partial<Record<NavSection, boolean>>
        const storedOpenSection = navSections.find(section => parsed[section])
        return openOnlySection(storedOpenSection ?? activeSection)
      } catch {
        return openOnlySection(activeSection)
      }
    }
    return openOnlySection(activeSection)
  })
  const toggleSection = (section: NavSection) => setExpandedSections(() => {
    const next = openOnlySection(section)
    localStorage.setItem('isp-expanded-sections', JSON.stringify(next))
    return next
  })
  const selectNavItem = (item: NavItem) => {
    setView(item.key)
    const next = openOnlySection(item.section)
    setExpandedSections(next)
    localStorage.setItem('isp-expanded-sections', JSON.stringify(next))
  }
  const toggleSidebar = () => setSidebarCollapsed(current => { const next = !current; localStorage.setItem('isp-sidebar-collapsed', String(next)); return next })
  const normalizedSidebarSearch = sidebarSearch.trim().toLowerCase()
  const matchingNav = (section: NavSection) => nav.filter(item => item.section === section && (!normalizedSidebarSearch || item.label.toLowerCase().includes(normalizedSidebarSearch)))
  return <div className={cn('billing-app min-h-screen bg-muted/30 text-foreground md:grid', sidebarCollapsed ? 'md:grid-cols-[4.5rem_1fr]' : 'md:grid-cols-[15rem_1fr]')}>
    <aside className={cn('billing-sidebar relative hidden bg-sidebar text-sidebar-foreground md:flex md:flex-col', sidebarCollapsed && 'items-center')}>
      <div className={cn('relative flex h-14 w-full items-center border-b border-sidebar-border/70 px-4', sidebarCollapsed ? 'justify-center' : 'gap-2.5')}><BrandMark className="size-8 rounded-md" />{!sidebarCollapsed && <div className="min-w-0"><p className="truncate font-heading text-xs font-semibold tracking-tight">{BRAND_NAME}</p><p className="text-[9px] uppercase tracking-[0.14em] text-sidebar-foreground/45">Billing operations</p></div>}<Button variant="ghost" size="icon-sm" className="absolute right-1.5 top-3 text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground" onClick={toggleSidebar} aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'} title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}><SidebarSimpleIcon /></Button></div>
      {!sidebarCollapsed && <div className="space-y-2 border-b border-sidebar-border/70 px-3 py-3"><div className="flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/35 px-2.5 py-2" aria-label="Current workspace"><span className="grid size-5 place-items-center rounded bg-sidebar-primary text-[9px] font-bold text-sidebar-primary-foreground">{BRAND_MARK}</span><span className="min-w-0 flex-1"><span className="block truncate text-[11px] font-medium">This installation</span><span className="block text-[9px] text-sidebar-foreground/45">Operator workspace</span></span></div><label className="flex h-8 items-center gap-2 rounded-lg bg-sidebar-accent/55 px-2 text-sidebar-foreground/60 focus-within:ring-1 focus-within:ring-sidebar-ring"><MagnifyingGlassIcon size={14} aria-hidden="true" /><span className="sr-only">Search navigation</span><Input value={sidebarSearch} onChange={event => setSidebarSearch(event.target.value)} className="h-7 min-w-0 border-0 bg-transparent px-0 text-[11px] text-sidebar-foreground placeholder:text-sidebar-foreground/40 focus-visible:ring-0" placeholder="Search navigation" type="search" /></label></div>}
      <nav className={cn("flex flex-1 flex-col gap-3 overflow-y-auto py-3", sidebarCollapsed ? "w-full px-2" : "px-3")} aria-label="Primary navigation">{navSections.map(section => { const items = matchingNav(section); const showItems = section === "Dashboard" || expandedSections[section] || Boolean(normalizedSidebarSearch); return <div className="flex flex-col gap-1" key={section}>{section === "Dashboard" ? <p className={cn("px-2 pb-1 text-[9px] font-medium uppercase tracking-[0.16em] text-sidebar-foreground/45", sidebarCollapsed && "sr-only")}>Dashboard</p> : <button type="button" className={cn("flex items-center justify-between px-2 pb-1 text-left text-[9px] font-medium uppercase tracking-[0.16em] text-sidebar-foreground/45 transition-colors hover:text-sidebar-foreground/75", sidebarCollapsed && "sr-only")} onClick={() => toggleSection(section)} aria-expanded={expandedSections[section]}><span>{section}</span><CaretRightIcon size={12} className={cn("transition-transform", expandedSections[section] && "rotate-90")} aria-hidden="true" /></button>}{showItems && items.map(item => <Button key={item.key} variant="ghost" size="sm" className={cn("h-8 w-full justify-start gap-2 rounded-lg px-2 text-[11px] text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground", sidebarCollapsed && "justify-center px-2", view === item.key && "bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground")} onClick={() => selectNavItem(item)} aria-current={view === item.key ? "page" : undefined} title={sidebarCollapsed ? item.label : undefined}><NavIcon name={item.key} /><span className={sidebarCollapsed ? "sr-only" : undefined}>{item.label}</span></Button>)}</div> })}{!sidebarCollapsed && normalizedSidebarSearch && !nav.some(item => item.label.toLowerCase().includes(normalizedSidebarSearch)) && <p className="px-2 pt-1 text-[10px] text-sidebar-foreground/45">No navigation matches.</p>}</nav>
      <div className={cn('w-full border-t border-sidebar-border/70 p-3', sidebarCollapsed && 'px-2')}><Button variant="ghost" size="sm" className={cn('h-8 w-full justify-start gap-2 rounded-lg px-2 text-[11px] text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground', sidebarCollapsed && 'justify-center px-2')} onClick={signOut} title={sidebarCollapsed ? 'Sign out' : undefined}><SignOutIcon data-icon="inline-start" /><span className={sidebarCollapsed ? 'sr-only' : undefined}>Sign out</span></Button>{!sidebarCollapsed && <p className="px-2 pt-2 text-[9px] text-sidebar-foreground/40">v0.2 billing foundation</p>}</div>
    </aside>
    <main className="min-w-0"><header className="billing-header sticky top-0 z-10 flex min-h-14 items-center justify-between border-b border-border/70 px-5 backdrop-blur sm:px-7"><div className="flex min-w-0 items-center gap-3"><div className="md:hidden"><BrandMark className="size-8 rounded-md" /></div><Separator orientation="vertical" className="hidden h-5 md:block" /><div className="min-w-0"><p className="truncate font-mono text-[9px] font-medium uppercase tracking-[0.18em] text-muted-foreground">ISP billing / {current?.section}</p><h1 className="truncate font-heading text-sm font-semibold">{current?.label}</h1></div></div><div className="flex shrink-0 items-center gap-2"><Button variant="ghost" size="icon-sm" className="rounded-lg" aria-label="Notifications"><BellIcon /></Button><Separator orientation="vertical" className="h-5" /><div className="flex items-center gap-2"><Avatar size="sm"><AvatarFallback>{session.user.name.slice(0, 1).toUpperCase()}</AvatarFallback></Avatar><div className="hidden text-right sm:block"><p className="text-xs font-medium">{session.user.name}</p><p className="text-[10px] text-muted-foreground">Administrator</p></div></div></div></header><nav className="flex gap-1 overflow-x-auto border-b border-sidebar-border/70 bg-sidebar px-2 py-1.5 text-sidebar-foreground md:hidden" aria-label="Mobile navigation">{nav.map(item => <Button key={item.key} variant="ghost" size="sm" className={cn('h-8 shrink-0 gap-2 rounded-lg px-2 text-[11px] text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground', view === item.key && 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground')} onClick={() => selectNavItem(item)} aria-current={view === item.key ? 'page' : undefined}><NavIcon name={item.key} />{item.label}</Button>)}</nav><div className="billing-content billing-canvas mx-auto w-full max-w-[1680px] p-5 sm:p-7"><section className="min-w-0" aria-label={`${current?.label ?? 'Application'} content`}>{view === 'overview' ? <Overview session={session} /> : view === 'subscribers' ? <SubscribersPage session={session} /> : isModuleView(view) ? <NetworkModulePage module={view} section={current?.section} /> : <ResourceTablePage session={session} view={view} />}</section></div></main>
  </div>
}

function NavIcon({ name }: { name: IconName }) {
  const props = { 'aria-hidden': true, size: 16, weight: 'regular' as const }
  if (name === 'overview') return <HouseIcon {...props} data-icon="inline-start" />
  if (name === 'subscribers') return <UsersThreeIcon {...props} data-icon="inline-start" />
  if (name === 'accounts') return <CreditCardIcon {...props} data-icon="inline-start" />
  if (name === 'plans') return <PackageIcon {...props} data-icon="inline-start" />
  if (name === 'services') return <WifiHighIcon {...props} data-icon="inline-start" />
  if (name === 'subscriptions') return <ChartLineUpIcon {...props} data-icon="inline-start" />
  if (name === 'invoices') return <InvoiceIcon {...props} data-icon="inline-start" />
  if (name === 'BNG') return <ShareNetworkIcon {...props} data-icon="inline-start" />
  if (name === 'ACS Server') return <CloudIcon {...props} data-icon="inline-start" />
  if (name === 'Routers') return <NetworkIcon {...props} data-icon="inline-start" />
  if (name === 'CGNAT') return <ArrowsLeftRightIcon {...props} data-icon="inline-start" />
  if (name === 'VLAN') return <TagIcon {...props} data-icon="inline-start" />
  if (name === 'RADIUS') return <ShieldCheckIcon {...props} data-icon="inline-start" />
  if (name === 'OLT') return <HardDriveIcon {...props} data-icon="inline-start" />
  if (name === 'ONT') return <DesktopTowerIcon {...props} data-icon="inline-start" />
  if (name === 'Users') return <UsersThreeIcon {...props} data-icon="inline-start" />
  if (name === 'Roles') return <UserGearIcon {...props} data-icon="inline-start" />
  if (name === 'Branding') return <PaintBrushIcon {...props} data-icon="inline-start" />
  if (name === 'API Tokens') return <KeyIcon {...props} data-icon="inline-start" />
  if (name === 'Email') return <EnvelopeSimpleIcon {...props} data-icon="inline-start" />
  if (name === 'Notifications') return <BellRingingIcon {...props} data-icon="inline-start" />
  if (name === 'Audit Logs') return <ClipboardTextIcon {...props} data-icon="inline-start" />
  return <ReceiptIcon {...props} data-icon="inline-start" />
}

export function PageHeader({ eyebrow, title, description, action }: { eyebrow: string; title: string; description: string; action?: ReactNode }) { return <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">{eyebrow}</p><h2 className="mt-2 font-heading text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h2><p className="mt-2 text-sm text-muted-foreground">{description}</p></div>{action}</div> }

function Overview({ session }: { session: Session }) {
  const [stats, setStats] = useState({ subscribers: 0, invoices: 0, payments: 0, services: 0 }); const [loading, setLoading] = useState(true); const [error, setError] = useState('')
  useEffect(() => { Promise.all(['subscribers', 'invoices', 'payments', 'subscriber-services'].map(endpoint => apiRequest<any>(`/${endpoint}?per_page=1`, {}, session.token))).then(([subscribers, invoices, payments, services]) => setStats({ subscribers: subscribers.data.total, invoices: invoices.data.total, payments: payments.data.total, services: services.data.total })).catch(e => setError(e.message)).finally(() => setLoading(false)) }, [session.token])
  const cards = [{ label: 'Subscribers', value: stats.subscribers, note: 'Active subscriber records', icon: UsersThreeIcon }, { label: 'Subscriber services', value: stats.services, note: 'Provisioning-ready services', icon: WifiHighIcon }, { label: 'Invoices', value: stats.invoices, note: 'Issued billing documents', icon: InvoiceIcon }, { label: 'Payments', value: stats.payments, note: 'Recorded receipts', icon: CurrencyDollarIcon }]
  return <div className="flex flex-col gap-8"><PageHeader eyebrow="Control room / live ledger" title="Billing overview" description={`Live financial and subscriber activity for ${'this installation'}.`} action={<Button variant="outline" size="sm" className="rounded-xl shadow-[0_10px_18px_-15px_rgb(24_35_54_/_55%)]"><GearIcon data-icon="inline-start" />Workspace settings</Button>} />{error && <Alert variant="destructive"><AlertTitle>Live data unavailable</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{cards.map(card => <StatCard key={card.label} {...card} loading={loading} />)}</div><div className="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]"><Card><CardHeader className="pb-4"><div><CardDescription className="font-mono uppercase tracking-[0.16em]">Operating rhythm</CardDescription><CardTitle className="mt-2 text-base">Billing workflow</CardTitle></div><CardAction><Badge variant="secondary" className="rounded-full"><CheckCircleIcon data-icon="inline-start" />Operational</Badge></CardAction></CardHeader><CardContent className="grid gap-3 pt-1 sm:grid-cols-2">{[['01', 'Subscriber records', 'Establish the account holder and contact record.', UsersThreeIcon], ['02', 'Service & plan', 'Connect the service to a versioned commercial plan.', WifiHighIcon], ['03', 'Invoice issuance', 'Generate an immutable charge with line items.', InvoiceIcon], ['04', 'Payment allocation', 'Allocate receipts and preserve the balance ledger.', CreditCardIcon]].map(([number, title, body, Icon]) => <div className="billing-workflow-step flex gap-3 rounded-2xl bg-background/80 p-4" key={number as string}><div className="grid size-9 shrink-0 place-items-center rounded-xl bg-secondary text-secondary-foreground shadow-[0_8px_16px_-13px_rgb(24_35_54_/_45%)]"><Icon size={16} weight="bold" aria-hidden="true" /></div><div><p className="text-xs font-semibold"><span className="billing-step-number text-primary">{number as string}</span> · {title as string}</p><p className="mt-1 text-xs leading-5 text-muted-foreground">{body as string}</p></div></div>)}</CardContent></Card><Card className="billing-ink-panel bg-primary text-primary-foreground"><CardHeader><CardDescription className="text-primary-foreground/65">Data integrity</CardDescription><CardTitle className="text-base">One source of truth</CardTitle></CardHeader><CardContent><p className="text-sm leading-6 text-primary-foreground/75">Every billing query is resolved through the installation context. Records from other installations are rejected before they reach the operator interface.</p><div className="mt-6 flex items-center gap-2 text-xs"><CheckCircleIcon weight="fill" />Installation-wide by default</div></CardContent><CardFooter className="text-xs text-primary-foreground/60">Authoritative MySQL ledger</CardFooter></Card></div></div>
}

function StatCard({ label, value, note, icon: Icon, loading }: { label: string; value: number; note: string; icon: any; loading: boolean }) { return <Card size="sm" className="billing-stat-card"><CardHeader className="pb-2"><CardDescription className="flex items-center justify-between"><span>{label}</span><span className="billing-stat-icon grid size-8 place-items-center rounded-xl bg-secondary text-secondary-foreground"><Icon size={15} aria-hidden="true" /></span></CardDescription><CardTitle className="font-heading text-3xl tracking-tight">{loading ? <Skeleton className="h-8 w-16" /> : value}</CardTitle></CardHeader><CardContent><p className="text-[10px] text-muted-foreground">{note}</p></CardContent></Card> }

export default App
