import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Toaster } from 'sonner'
import {
  BellIcon,
  BellRingingIcon,
  ArrowsLeftRightIcon,
  CaretRightIcon,
  ClipboardTextIcon,
  CloudIcon,
  ChartLineUpIcon,
  CreditCardIcon,
  DesktopTowerIcon,
  HardDriveIcon,
  HouseIcon,
  InvoiceIcon,
  EnvelopeSimpleIcon,
  KeyIcon,
  MoonIcon,
  PaintBrushIcon,
  ReceiptIcon,
  NetworkIcon,
  PackageIcon,
  SignOutIcon,
  ShareNetworkIcon,
  SidebarSimpleIcon,
  ShieldCheckIcon,
  SunIcon,
  TagIcon,
  UserGearIcon,
  UsersThreeIcon,
  WifiHighIcon,
  XIcon,
} from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { cn } from '@/lib/utils'
import { apiRequest } from './lib/api'
import { getErrorMessage, notify } from './lib/notifications'
import { hasPermission } from './lib/usersRoles'
import { readStoredView, type View } from './lib/viewState'
import { DashboardPage } from './components/DashboardPage'
import { BrandingPage } from './components/BrandingPage'
import { NetworkModulePage } from './components/NetworkModulePage'
import { ResourceTablePage } from './components/ResourceTablePage'
import { SubscribersPage } from './components/SubscribersPage'
import { RolesPage } from './components/RolesPage'
import { UsersPage } from './components/UsersPage'
import { ConfirmProvider } from './components/ConfirmProvider'
import { networkModules, type NetworkModule } from './lib/networkModules'
import { systemModules, type SystemModule } from './lib/systemModules'
import type { BrandingValues } from './lib/branding'

type Session = { token: string; user: { name: string; email: string }; permissions?: string[]; is_superadmin?: boolean; branding?: BrandingValues }
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

function openAllSections(): Record<NavSection, boolean> {
  return { Dashboard: true, Billing: true, Network: true, System: true }
}

function App() {
  const [session, setSession] = useState<Session | null>(() => JSON.parse(localStorage.getItem('isp-session') || 'null'))
  const [theme, setTheme] = useState<'light' | 'dark'>(() => {
    const stored = localStorage.getItem('isp-theme')
    return stored === 'dark' || stored === 'light' ? stored : (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
  })
  const [view, setView] = useState<AppView>(readStoredAppView)
  const [error, setError] = useState('')
  useEffect(() => { document.title = session?.branding?.organization_name || BRAND_NAME }, [session?.branding?.organization_name])
  useEffect(() => { document.documentElement.classList.toggle('dark', theme === 'dark'); localStorage.setItem('isp-theme', theme) }, [theme])
  useEffect(() => {
    const root = document.documentElement
    const values: Record<string, string | null | undefined> = {
      '--primary': session?.branding?.primary_color,
      '--ring': session?.branding?.primary_color,
      '--sidebar-primary': session?.branding?.primary_color,
      '--accent': session?.branding?.accent_color,
      '--sidebar-accent': session?.branding?.accent_color,
    }
    Object.entries(values).forEach(([property, value]) => value ? root.style.setProperty(property, value) : root.style.removeProperty(property))
  }, [session?.branding?.primary_color, session?.branding?.accent_color])
  useEffect(() => { localStorage.setItem('isp-view', view) }, [view])
  useEffect(() => {
    if (!session?.token) return
    void apiRequest<{ data: { permissions: string[]; is_superadmin?: boolean; branding?: BrandingValues } }>('/auth/me', {}, session.token).then(response => setSession(current => current ? { ...current, permissions: response.data.permissions, is_superadmin: response.data.is_superadmin, branding: response.data.branding || current.branding } : current)).catch(() => undefined)
  }, [session?.token])
  useEffect(() => {
    if (session?.is_superadmin === false && view === 'Users' && !hasPermission(session?.permissions, 'users.view')) setView('overview')
    if (session?.is_superadmin === false && view === 'Roles' && !hasPermission(session?.permissions, 'roles.view')) setView('overview')
    if (session?.is_superadmin === false && view === 'Branding' && !hasPermission(session?.permissions, 'branding.view')) setView('overview')
  }, [session?.is_superadmin, session?.permissions, view])
  const signIn = (next: Session) => { localStorage.setItem('isp-session', JSON.stringify(next)); setSession(next); setError('') }
  const signOut = async () => { if (session) await apiRequest('/auth/logout', { method: 'POST' }, session.token).catch(() => undefined); localStorage.removeItem('isp-session'); setSession(null); notify.success('Signed out.') }

  return <ConfirmProvider>
    <Toaster position="top-right" theme={theme} closeButton richColors={false} />
    {!session ? <Login onSignedIn={signIn} error={error} setError={setError} /> : <Shell session={session} view={view} setView={setView} signOut={signOut} theme={theme} setTheme={setTheme} onBrandingSaved={branding => setSession(current => current ? { ...current, branding } : current)} />}
  </ConfirmProvider>
}

function Login({ onSignedIn, error, setError }: { onSignedIn: (s: Session) => void; error: string; setError: (s: string) => void }) {
  const [email, setEmail] = useState('admin@example.com')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setLoading(true); setError('')
    try { const response = await apiRequest<{ data: Session }>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }); onSignedIn(response.data); notify.success('Signed in successfully.') }
    catch (e) { const message = getErrorMessage(e, 'Unable to sign in.'); setError(message); notify.error(message) }
    finally { setLoading(false) }
  }

  return <main className="min-h-screen bg-background p-0 text-foreground sm:p-3">
    <div className="grid min-h-screen overflow-hidden bg-card shadow-[0_30px_90px_-48px_rgb(24_35_54_/_65%)] sm:min-h-[calc(100vh-1.5rem)] sm:rounded-[2rem] lg:grid-cols-[minmax(26rem,0.95fr)_1.05fr]">
      <section className="flex flex-col bg-foreground px-6 py-7 text-background sm:px-10 lg:px-14 lg:py-9">
        <div className="flex items-center gap-3"><BrandMark className="rounded-md bg-background text-foreground" /><div><p className="font-heading text-sm font-semibold tracking-tight"><span>{BRAND_NAME.split(' - ')[0]}</span>{BRAND_NAME.includes(' - ') && <span> - {BRAND_NAME.split(' - ').slice(1).join(' - ')}</span>}</p><p className="text-xs text-background/55">Billing operations</p></div></div>
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

function BrandMark({ className, mark = BRAND_MARK, logoUrl, primary }: { className?: string; mark?: string; logoUrl?: string | null; primary?: string | null }) { return <div className={cn('grid size-9 shrink-0 place-items-center overflow-hidden bg-primary text-xs font-bold text-primary-foreground', className)} style={primary ? { backgroundColor: primary } : undefined}>{logoUrl ? <img src={logoUrl} alt="" className="size-7 object-contain" /> : mark}</div> }

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

function Shell({ session, view, setView, signOut, theme, setTheme, onBrandingSaved }: { session: Session; view: AppView; setView: (v: AppView) => void; signOut: () => void; theme: 'light' | 'dark'; setTheme: (theme: 'light' | 'dark') => void; onBrandingSaved: (branding: BrandingValues) => void }) {
  const current = nav.find(item => item.key === view)
  const visibleNav = nav.filter(item => {
    if (session.is_superadmin !== false) return true
    if (item.key === 'Users') return hasPermission(session.permissions, 'users.view')
    if (item.key === 'Roles') return hasPermission(session.permissions, 'roles.view')
    if (item.key === 'Branding') return hasPermission(session.permissions, 'branding.view')
    return true
  })
  const brandName = session.branding?.short_name || session.branding?.organization_name || BRAND_NAME
  const brandMark = session.branding?.brand_mark || BRAND_MARK
  const brandLogo = session.branding?.logo_url
  const brandPrimary = session.branding?.primary_color
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => localStorage.getItem('isp-sidebar-collapsed') === 'true')
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const [expandedSections, setExpandedSections] = useState<Record<NavSection, boolean>>(() => {
    const stored = localStorage.getItem('isp-expanded-sections')
    if (stored) {
      try {
        const parsed = JSON.parse(stored) as Partial<Record<NavSection, boolean>>
        if (navSections.every(section => typeof parsed[section] === 'boolean')) return parsed as Record<NavSection, boolean>
        return openAllSections()
      } catch {
        return openAllSections()
      }
    }
    return openAllSections()
  })
  const toggleSection = (section: NavSection) => setExpandedSections(current => {
    const next = { ...current, [section]: !current[section] }
    localStorage.setItem('isp-expanded-sections', JSON.stringify(next))
    return next
  })
  const selectNavItem = (item: NavItem) => {
    setView(item.key)
    const next = { ...expandedSections, [item.section]: true }
    setExpandedSections(next)
    localStorage.setItem('isp-expanded-sections', JSON.stringify(next))
  }
  const toggleSidebar = () => setSidebarCollapsed(current => { const next = !current; localStorage.setItem('isp-sidebar-collapsed', String(next)); return next })
  return <div className={cn('billing-app min-h-screen bg-muted/30 text-foreground md:grid', sidebarCollapsed ? 'md:grid-cols-[4.5rem_1fr]' : 'md:grid-cols-[15rem_1fr]')}>
    <div className="billing-header-actions fixed right-52 top-4 z-20 hidden items-center gap-1 md:flex"><Button variant="ghost" size="icon" className="rounded-xl" aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`} title={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`} onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>{theme === 'dark' ? <SunIcon /> : <MoonIcon />}</Button><Button variant="ghost" size="icon" className="rounded-xl" aria-label="Open notifications" aria-expanded={notificationsOpen} onClick={() => setNotificationsOpen(true)}><BellIcon /></Button></div>
    <aside className={cn('billing-sidebar relative hidden bg-sidebar text-sidebar-foreground md:flex md:flex-col', sidebarCollapsed && 'items-center')}>
      <div className={cn('relative flex h-16 w-full items-center px-5', sidebarCollapsed ? 'justify-center' : 'gap-3')}><BrandMark mark={brandMark} logoUrl={brandLogo} primary={brandPrimary} />{!sidebarCollapsed && <div><p className="font-heading text-sm font-semibold tracking-tight">{brandName}</p><p className="text-[10px] text-sidebar-foreground/55">Billing operations</p></div>}</div>
      <nav className={cn("mt-5 flex flex-1 flex-col gap-4 overflow-y-auto", sidebarCollapsed ? "w-full px-2" : "px-3")} aria-label="Primary navigation">{navSections.map(section => <div className="flex flex-col gap-1" key={section}>{section === "Dashboard" ? <p className={cn("px-3 pb-1 text-[10px] font-medium uppercase tracking-[0.16em] text-sidebar-foreground/45", sidebarCollapsed && "sr-only")}>Dashboard</p> : <button type="button" className={cn("flex items-center justify-between px-3 pb-1 text-left text-[10px] font-medium uppercase tracking-[0.16em] text-sidebar-foreground/45 transition-colors hover:text-sidebar-foreground/75", sidebarCollapsed && "sr-only")} onClick={() => toggleSection(section)} aria-expanded={expandedSections[section]}><span>{section}</span><CaretRightIcon size={12} className={cn("transition-transform", expandedSections[section] && "rotate-90")} aria-hidden="true" /></button>}{(section === "Dashboard" || expandedSections[section]) && visibleNav.filter(item => item.section === section).map(item => <Button key={item.key} variant="ghost" className={cn("w-full justify-start gap-2.5 rounded-xl px-3 text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground", sidebarCollapsed && "justify-center px-2", view === item.key && "bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground")} onClick={() => selectNavItem(item)} aria-current={view === item.key ? "page" : undefined} title={sidebarCollapsed ? item.label : undefined}><NavIcon name={item.key} /><span className={sidebarCollapsed ? "sr-only" : undefined}>{item.label}</span></Button>)}</div>)}</nav>
      <div className={cn('m-3 mt-3 w-full p-0 pt-3', sidebarCollapsed && 'px-2')}><Button variant="ghost" className={cn('w-full justify-start gap-2.5 rounded-xl px-3 text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground', sidebarCollapsed && 'justify-center px-2')} onClick={signOut} title={sidebarCollapsed ? 'Sign out' : undefined}><SignOutIcon data-icon="inline-start" /><span className={sidebarCollapsed ? 'sr-only' : undefined}>Sign out</span></Button>{!sidebarCollapsed && <p className="px-3 pt-3 text-[10px] text-sidebar-foreground/40">v0.2 billing foundation</p>}</div>
</aside>
    <main className="min-w-0"><header className="billing-header sticky top-0 z-10 flex min-h-16 items-center justify-between px-5 backdrop-blur sm:px-8"><div className="flex items-center gap-1.5"><div className="md:hidden"><BrandMark mark={brandMark} logoUrl={brandLogo} primary={brandPrimary} /></div><Button variant="ghost" size="icon-sm" className="hidden text-muted-foreground hover:bg-muted hover:text-foreground md:inline-flex md:size-6" onClick={toggleSidebar} aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'} title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}><SidebarSimpleIcon weight="bold" /></Button><Separator orientation="vertical" className="hidden h-5 md:block" /><p className="font-mono text-[10px] font-medium uppercase tracking-[0.18em] text-muted-foreground">ISP billing / {current?.section}</p></div><div className="flex items-center gap-3"><Button variant="ghost" size="icon" className="rounded-xl" aria-label="Open notifications" aria-expanded={notificationsOpen} onClick={() => setNotificationsOpen(true)}><BellIcon /></Button><Separator orientation="vertical" className="h-5" /><button type="button" className="billing-profile-trigger flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-muted/70" aria-expanded={profileOpen} aria-haspopup="dialog" onClick={() => setProfileOpen(current => !current)}><Avatar size="sm"><AvatarFallback>{session.user.name.slice(0, 1).toUpperCase()}</AvatarFallback></Avatar><span className="hidden text-xs font-medium sm:block">{session.user.name}</span></button></div></header><nav className="flex gap-1 overflow-x-auto bg-sidebar p-2 text-sidebar-foreground md:hidden" aria-label="Mobile navigation">{visibleNav.map(item => <Button key={item.key} variant="ghost" className={cn('shrink-0 gap-2 rounded-xl text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground', view === item.key && 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary hover:text-sidebar-primary-foreground')} onClick={() => selectNavItem(item)} aria-current={view === item.key ? 'page' : undefined}><NavIcon name={item.key} />{item.label}</Button>)}</nav><div className="billing-content billing-canvas mx-auto flex w-full max-w-[1680px] flex-col gap-6 px-4 pb-4 pt-0 sm:px-6 sm:pb-6 sm:pt-0">{view === 'overview' ? <DashboardPage session={session} /> : view === 'subscribers' ? <SubscribersPage session={session} /> : view === 'Users' ? <UsersPage token={session.token} permissions={session.permissions} /> : view === 'Roles' ? <RolesPage token={session.token} permissions={session.permissions} /> : view === 'Branding' ? <BrandingPage token={session.token} permissions={session.permissions} branding={session.branding} onSaved={onBrandingSaved} /> : isModuleView(view) ? <NetworkModulePage module={view} section={current?.section} /> : <ResourceTablePage session={session} view={view} />}</div></main>
    {notificationsOpen && <NotificationDrawer onClose={() => setNotificationsOpen(false)} />}
    {profileOpen && <ProfileDrawer name={session.user.name} email={session.user.email} onClose={() => setProfileOpen(false)} onSignOut={signOut} />}
  </div>
}

function ProfileDrawer({ name, email, onClose, onSignOut }: { name: string; email: string; onClose: () => void; onSignOut: () => void }) {
  return <div className="billing-profile-layer fixed inset-0 z-40" role="dialog" aria-modal="true" aria-labelledby="profile-drawer-title"><button type="button" className="absolute inset-0" aria-label="Close profile menu" onClick={onClose} /><aside className="billing-profile-drawer absolute right-4 top-[4.35rem] w-72 rounded-lg border bg-card p-2 shadow-xl sm:right-8"><div className="flex items-center gap-3 px-3 py-3"><Avatar><AvatarFallback>{name.slice(0, 1).toUpperCase()}</AvatarFallback></Avatar><div className="min-w-0"><h2 id="profile-drawer-title" className="truncate text-sm font-semibold">{name}</h2><p className="truncate text-xs text-muted-foreground">{email}</p></div></div><Separator /><div className="mt-2 grid gap-1"><Button variant="ghost" className="justify-start gap-3" onClick={onClose}><UserGearIcon size={16} />Profile</Button><Button variant="ghost" className="justify-start gap-3 text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={onSignOut}><SignOutIcon size={16} />Logout</Button></div></aside></div>
}

function NotificationDrawer({ onClose }: { onClose: () => void }) {
  const items = [
    { title: 'Invoice queue is clear', body: 'No billing documents require immediate review.', time: 'Now' },
    { title: 'Subscriber records synced', body: 'Directory counts are current for this installation.', time: '5m' },
    { title: 'Payment ledger healthy', body: 'Recorded receipts are ready for reconciliation.', time: '18m' },
  ]
  return <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-labelledby="notification-drawer-title"><button type="button" className="absolute inset-0 bg-black/35 backdrop-blur-[1px]" aria-label="Close notifications" onClick={onClose} /><aside className="notification-drawer relative z-10 flex h-full w-full max-w-sm flex-col bg-background shadow-2xl"><div className="flex items-center justify-between border-b px-5 py-4"><div><p className="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Activity</p><h2 id="notification-drawer-title" className="mt-1 font-heading text-lg font-semibold">Notifications</h2></div><Button variant="ghost" size="icon-sm" onClick={onClose} aria-label="Close notifications"><XIcon /></Button></div><div className="flex-1 overflow-y-auto p-3">{items.map(item => <div className="rounded-md border border-transparent px-3 py-3 transition-colors hover:border-border hover:bg-muted/45" key={item.title}><div className="flex items-start justify-between gap-3"><p className="text-sm font-semibold">{item.title}</p><span className="font-mono text-[10px] text-muted-foreground">{item.time}</span></div><p className="mt-1 text-xs leading-5 text-muted-foreground">{item.body}</p></div>)}</div><div className="border-t px-5 py-3 text-xs text-muted-foreground">Notification center scaffold. Live alerts can be wired to the backend later.</div></aside></div>
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

export default App
