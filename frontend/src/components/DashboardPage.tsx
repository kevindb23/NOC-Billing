import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  ArrowClockwiseIcon,
  CalendarBlankIcon,
  CaretRightIcon,
  CreditCardIcon,
  InvoiceIcon,
  ReceiptIcon,
  UsersThreeIcon,
  WifiHighIcon,
} from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiRequest } from '../lib/api'
import { formatDate, formatMoney } from '../lib/formatters'

type Session = { token: string }
type Row = Record<string, any>
type Page<T = Row> = { data: T[]; total: number }
type ApiPage<T = Row> = { data: Page<T> }
type DashboardData = {
  subscribers: Page
  accounts: Page
  plans: Page
  services: Page
  subscriptions: Page
  invoices: Page
  payments: Page
}

const emptyPage: Page = { data: [], total: 0 }

export function DashboardPage({ session }: { session: Session }) {
  const [data, setData] = useState<DashboardData>({
    subscribers: emptyPage,
    accounts: emptyPage,
    plans: emptyPage,
    services: emptyPage,
    subscriptions: emptyPage,
    invoices: emptyPage,
    payments: emptyPage,
  })
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      const [subscribers, accounts, plans, services, subscriptions, invoices, payments] = await Promise.all([
        fetchPage('customers', session.token),
        fetchPage('billing-accounts', session.token),
        fetchPage('plans', session.token),
        fetchPage('subscriber-services', session.token),
        fetchPage('subscriptions', session.token),
        fetchPage('invoices', session.token),
        fetchPage('payments', session.token),
      ])
      setData({ subscribers, accounts, plans, services, subscriptions, invoices, payments })
      setError('')
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Unable to load dashboard data.')
    }
  }, [session.token])

  useEffect(() => {
    const timer = window.setTimeout(() => { void load() }, 0)
    return () => window.clearTimeout(timer)
  }, [load])

  const openInvoices = data.invoices.data.filter(invoice => ['open', 'overdue', 'partially_paid'].includes(invoice.status))
  const activeServices = data.services.data.filter(service => service.status === 'active')
  const paymentTotal = data.payments.data.reduce((sum, payment) => sum + Number(payment.amount_minor || 0), 0)
  const invoiceTotal = data.invoices.data.reduce((sum, invoice) => sum + Number(invoice.total_minor || 0), 0)
  const currency = data.payments.data[0]?.currency || data.invoices.data[0]?.currency || 'PHP'

  const kpis = [
    { label: 'Subscribers', value: String(data.subscribers.total), meta: 'Live total', tone: 'good' as const, icon: UsersThreeIcon },
    { label: 'Active services', value: String(activeServices.length), meta: `${data.services.total} total`, tone: 'good' as const, icon: WifiHighIcon },
    { label: 'Open invoices', value: String(openInvoices.length), meta: `${data.invoices.total} issued`, tone: openInvoices.length ? 'bad' as const : 'good' as const, icon: InvoiceIcon },
    { label: 'Payments posted', value: formatMoney(paymentTotal, currency), meta: `${data.payments.total} receipts`, tone: 'good' as const, icon: CreditCardIcon },
    { label: 'Active plans', value: String(data.plans.data.filter(plan => plan.status === 'active').length), meta: `${data.plans.total} catalog items`, tone: 'good' as const, icon: ReceiptIcon },
  ]

  return (
    <div className="dashboard-grid flex flex-col gap-3">
      <DashboardHeader onRefresh={load} />
      {error && <Alert variant="destructive"><AlertTitle>Live data unavailable</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
      <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-5">
        {kpis.map(kpi => <KpiCard key={kpi.label} kpi={kpi} />)}
      </div>
      <div className="grid gap-3 xl:grid-cols-2">
        <InvoiceAttentionTable invoices={data.invoices.data} />
        <ServiceStatusTable services={data.services.data} />
      </div>
      <BillingPipeline invoices={data.invoices.data} payments={data.payments.data} invoiceTotal={invoiceTotal} paymentTotal={paymentTotal} currency={currency} />
      <BillingActivityChart invoices={data.invoices.data} payments={data.payments.data} currency={currency} />
    </div>
  )
}

async function fetchPage(endpoint: string, token: string): Promise<Page> {
  const response = await apiRequest<ApiPage>(`/${endpoint}?per_page=100`, {}, token)
  return response.data
}

function DashboardHeader({ onRefresh }: { onRefresh: () => void }) {
  return (
    <div className="dashboard-top-row flex items-center justify-between gap-3">
      <h1 className="text-sm font-semibold">Dashboard</h1>
      <div className="flex items-center gap-1.5">
        <Button variant="outline" size="sm" className="dashboard-toolbar-button"><CalendarBlankIcon data-icon="inline-start" />Live data</Button>
        <Button variant="outline" size="icon-sm" className="dashboard-toolbar-button" aria-label="Refresh dashboard" onClick={() => void onRefresh()}><ArrowClockwiseIcon /></Button>
      </div>
    </div>
  )
}

function KpiCard({ kpi }: { kpi: { label: string; value: string; meta: string; tone: 'good' | 'bad'; icon: typeof UsersThreeIcon } }) {
  const Icon = kpi.icon
  return (
    <Card size="sm" className="dashboard-card dashboard-kpi-card">
      <CardContent className="flex h-full flex-col justify-between p-3">
        <div className="flex items-start justify-between">
          <span className="dashboard-icon-box"><Icon size={13} aria-hidden="true" /></span>
          <span className={`dashboard-trend dashboard-trend-${kpi.tone}`}>Live</span>
        </div>
        <div>
          <p className="mt-4 break-words text-lg font-medium tracking-tight">{kpi.value}</p>
          <p className="mt-0.5 text-[11px] text-muted-foreground">{kpi.label}</p>
          <p className="text-[10px] text-muted-foreground/75">{kpi.meta}</p>
        </div>
      </CardContent>
    </Card>
  )
}

function SectionHeader({ title, count }: { title: string; count?: number }) {
  return <CardHeader className="dashboard-section-header"><CardTitle className="text-xs font-semibold">{title}<span className="ml-2 rounded-sm bg-muted px-1.5 py-0.5 font-mono text-[10px] font-normal text-muted-foreground">{count ?? 0}</span></CardTitle><Button variant="ghost" size="xs" className="h-6 gap-1 px-1.5">View all<CaretRightIcon size={12} /></Button></CardHeader>
}

function InvoiceAttentionTable({ invoices }: { invoices: Row[] }) {
  const rows = invoices.filter(invoice => invoice.status !== 'paid').slice(0, 5)
  return (
    <Card className="dashboard-card">
      <SectionHeader title="Invoices needing attention" count={rows.length} />
      <CardContent className="p-0">
        <Table className="dashboard-table min-w-[620px]">
          <TableHeader><TableRow><TableHead>Status</TableHead><TableHead>Invoice</TableHead><TableHead>Subscriber</TableHead><TableHead>Due</TableHead><TableHead>Balance</TableHead><TableHead /></TableRow></TableHeader>
          <TableBody>{rows.length ? rows.map(invoice => <TableRow key={invoice.id || invoice.public_id}><TableCell><StatusBadge status={invoice.status} /></TableCell><TableCell>{invoice.invoice_number}</TableCell><TableCell>{invoice.billing_account?.subscriber?.legal_name || '—'}</TableCell><TableCell>{formatDate(invoice.due_date)}</TableCell><TableCell>{formatMoney(Number(invoice.balance_due_minor || 0), invoice.currency)}</TableCell><TableCell className="text-right"><CaretRightIcon size={13} className="text-muted-foreground" /></TableCell></TableRow>) : <TableRow><TableCell colSpan={6} className="text-muted-foreground">No invoice attention items.</TableCell></TableRow>}</TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}

function ServiceStatusTable({ services }: { services: Row[] }) {
  const rows = [...services].sort((a, b) => statusPriority(a.status) - statusPriority(b.status)).slice(0, 5)
  return (
    <Card className="dashboard-card">
      <SectionHeader title="Subscriber service status" count={rows.length} />
      <CardContent className="p-0">
        <Table className="dashboard-table min-w-[620px]">
          <TableHeader><TableRow><TableHead>Status</TableHead><TableHead>Service</TableHead><TableHead>Subscriber</TableHead><TableHead>Type</TableHead><TableHead /></TableRow></TableHeader>
          <TableBody>{rows.length ? rows.map(service => <TableRow key={service.id || service.public_id}><TableCell><StatusBadge status={service.status} /></TableCell><TableCell>{service.service_number}</TableCell><TableCell>{service.subscriber?.legal_name || '—'}</TableCell><TableCell>{service.service_type || 'internet'}</TableCell><TableCell className="text-right"><CaretRightIcon size={13} className="text-muted-foreground" /></TableCell></TableRow>) : <TableRow><TableCell colSpan={5} className="text-muted-foreground">No subscriber services found.</TableCell></TableRow>}</TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}

function BillingPipeline({ invoices, payments, invoiceTotal, paymentTotal, currency }: { invoices: Row[]; payments: Row[]; invoiceTotal: number; paymentTotal: number; currency: string }) {
  const invoiceStatuses = countByStatus(invoices)
  const paymentStatuses = countByStatus(payments)
  return (
    <Card className="dashboard-card">
      <SectionHeader title="Billing pipeline" count={invoices.length + payments.length} />
      <CardContent className="grid gap-0 p-0 md:grid-cols-2">
        <SupplyBlock title="Invoices issued" total={formatMoney(invoiceTotal, currency)} label={`${invoices.length} invoices loaded`} legends={[['green', `${invoiceStatuses.paid || 0} Paid`], ['amber', `${(invoiceStatuses.open || 0) + (invoiceStatuses.partially_paid || 0)} Open`], ['red', `${invoiceStatuses.void || 0} Void`]]} />
        <SupplyBlock title="Payments recorded" total={formatMoney(paymentTotal, currency)} label={`${payments.length} payments loaded`} legends={[['blue', `${paymentStatuses.posted || 0} Posted`], ['amber', `${paymentStatuses.pending || 0} Pending`], ['red', `${paymentStatuses.void || 0} Void`]]} />
      </CardContent>
    </Card>
  )
}

function SupplyBlock({ title, total, label, legends }: { title: string; total: string; label: string; legends: string[][] }) {
  return <div className="dashboard-supply-block"><div><p className="text-xs font-medium">{title}</p><p className="mt-4 text-lg font-medium">{total}</p><p className="text-[10px] text-muted-foreground">{label}</p></div><div className="flex flex-wrap items-center gap-3">{legends.map(([tone, text]) => <span className="dashboard-legend" key={text}><span className={`dashboard-dot dashboard-dot-${tone}`} />{text}</span>)}</div></div>
}

function BillingActivityChart({ invoices, payments, currency }: { invoices: Row[]; payments: Row[]; currency: string }) {
  const chartData = useMemo(() => buildBillingChartData(invoices, payments), [invoices, payments])
  const maxRevenue = Math.max(1, ...chartData.map(point => point.revenue))
  const maxInvoices = Math.max(1, ...chartData.map(point => point.invoices))
  return (
    <Card className="dashboard-card">
      <SectionHeader title="Invoices & payments" count={chartData.reduce((sum, point) => sum + point.invoices + point.payments, 0)} />
      <CardContent className="p-3 pt-2">
        <div className="mb-2 flex justify-end gap-3 text-[10px] text-muted-foreground"><span className="dashboard-legend"><span className="dashboard-dot dashboard-dot-blue" />Invoices</span><span className="dashboard-legend"><span className="dashboard-dot dashboard-dot-gray" />Payments</span></div>
        <div className="dashboard-chart">
          <div className="dashboard-y-left"><span>{formatMoney(maxRevenue, currency)}</span><span>{formatMoney(maxRevenue * 0.75, currency)}</span><span>{formatMoney(maxRevenue * 0.5, currency)}</span><span>{formatMoney(maxRevenue * 0.25, currency)}</span><span>{formatMoney(0, currency)}</span></div>
          <div className="dashboard-plot">
            {[0, 1, 2, 3, 4].map(line => <span className="dashboard-grid-line" style={{ bottom: `${line * 25}%` }} key={line} />)}
            <div className="dashboard-bars">{chartData.map(point => <div className="dashboard-bar-group" key={point.label} title={`${point.label}: ${point.invoices} invoices, ${formatMoney(point.revenue, currency)} payments`}><span className="dashboard-bar dashboard-bar-orders" style={{ height: `${(point.invoices / maxInvoices) * 100}%` }} /><span className="dashboard-bar dashboard-bar-revenue" style={{ height: `${(point.revenue / maxRevenue) * 100}%` }} /></div>)}</div>
          </div>
          <div className="dashboard-y-right"><span>{maxInvoices}</span><span>{Math.round(maxInvoices * 0.75)}</span><span>{Math.round(maxInvoices * 0.5)}</span><span>{Math.round(maxInvoices * 0.25)}</span><span>0</span></div>
        </div>
        <div className="mt-2 flex justify-between text-[10px] text-muted-foreground"><span>{chartData[0]?.label || '—'}</span><span>{chartData[chartData.length - 1]?.label || '—'}</span></div>
      </CardContent>
    </Card>
  )
}

function StatusBadge({ status }: { status: string }) {
  const bad = ['overdue', 'failed', 'suspended', 'void', 'cancelled', 'terminated'].includes(status)
  const pending = ['open', 'pending', 'partially_paid', 'draft'].includes(status)
  return <Badge variant={bad ? 'destructive' : pending ? 'secondary' : 'outline'} className="billing-status-badge">{status.replace('_', ' ')}</Badge>
}

function countByStatus(rows: Row[]) {
  return rows.reduce<Record<string, number>>((counts, row) => {
    counts[row.status || 'unknown'] = (counts[row.status || 'unknown'] || 0) + 1
    return counts
  }, {})
}

function statusPriority(status: string) {
  if (['pending', 'suspended', 'terminated'].includes(status)) return 0
  if (status === 'active') return 1
  return 2
}

function buildBillingChartData(invoices: Row[], payments: Row[]) {
  const labels = Array.from({ length: 7 }, (_, index) => {
    const date = new Date()
    date.setDate(date.getDate() - (6 - index))
    return date.toISOString().slice(0, 10)
  })
  const points = labels.map(label => ({ label: new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' }).format(new Date(`${label}T00:00:00`)), key: label, invoices: 0, payments: 0, revenue: 0 }))
  const byKey = new Map(points.map(point => [point.key, point]))
  invoices.forEach(invoice => {
    const key = String(invoice.issue_date || invoice.created_at || '').slice(0, 10)
    const point = byKey.get(key)
    if (point) point.invoices += 1
  })
  payments.forEach(payment => {
    const key = String(payment.received_at || payment.created_at || '').slice(0, 10)
    const point = byKey.get(key)
    if (point) {
      point.payments += 1
      point.revenue += Number(payment.amount_minor || 0)
    }
  })
  return points
}
