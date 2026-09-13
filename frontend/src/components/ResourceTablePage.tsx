import { useCallback, useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import { DownloadSimpleIcon, FilePdfIcon, MagnifyingGlassIcon, PlusIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage, notify } from '@/lib/notifications'
import { CrudModal, type CrudField } from './CrudModal'
import { TableActions } from './TableActions'
import { apiRequest } from '../lib/api'
import { formatDate, formatMoney } from '../lib/formatters'
import { BillingStatementPdf } from '../lib/BillingStatementPdf'

type View = 'accounts' | 'billing-statements' | 'plans' | 'services' | 'subscriptions' | 'invoices' | 'payments'
type Row = Record<string, any>
type Session = { token: string }
type ModalState = { mode: 'view' | 'create' | 'edit'; row?: Row } | null

const configs: Record<View, { endpoint: string; title: string; eyebrow: string; description: string; columns: string[]; createFields: CrudField[]; editFields: CrudField[] }> = {
  accounts: { endpoint: 'billing-accounts', title: 'Billing accounts', eyebrow: 'Account control', description: 'Customer billing ledgers and account status.', columns: ['Account', 'Subscriber', 'Currency', 'Status'], createFields: [{ name: 'subscriber_id', label: 'Subscriber public ID', required: true }], editFields: [{ name: 'currency', label: 'Currency', required: true }, { name: 'credit_limit_minor', label: 'Credit limit (minor units)', type: 'number' }, { name: 'status', label: 'Status', required: true }] },
  'billing-statements': { endpoint: 'billing-statements', title: 'Billing statements', eyebrow: 'Revenue cycle', description: 'Prorated charges, recurring fees, installation amortization, and VAT.', columns: ['Statement', 'Subscriber', 'Period', 'Total', 'Status'], createFields: [{ name: 'subscription_id', label: 'Subscription', required: true }, { name: 'issue_date', label: 'Issue date', type: 'date', required: true }], editFields: [] },
  plans: { endpoint: 'plans', title: 'Plans', eyebrow: 'Commercial catalog', description: 'Commercial service plans and published speeds.', columns: ['Plan', 'Code', 'Speed', 'Status'], createFields: [{ name: 'code', label: 'Plan code', required: true }, { name: 'name', label: 'Plan name', required: true }, { name: 'price', label: 'Price', type: 'number', required: true, currency: true }, { name: 'service_type', label: 'Service type', required: true, searchable: false, options: [{ value: 'prepaid', label: 'Prepaid' }, { value: 'postpaid', label: 'Postpaid' }] }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'status', label: 'Status', required: true, searchable: false, options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }], editFields: [{ name: 'code', label: 'Plan code', required: true }, { name: 'name', label: 'Plan name', required: true }, { name: 'price', label: 'Price', type: 'number', required: true, currency: true }, { name: 'service_type', label: 'Service type', required: true, searchable: false, options: [{ value: 'prepaid', label: 'Prepaid' }, { value: 'postpaid', label: 'Postpaid' }] }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'status', label: 'Status', required: true, searchable: false, options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }] },
  services: { endpoint: 'subscriber-services', title: 'Subscriber services', eyebrow: 'Service registry', description: 'Provisioning-ready subscriber connections.', columns: ['Service', 'Subscriber', 'Type', 'Status'], createFields: [{ name: 'subscriber_id', label: 'Subscriber public ID', required: true }, { name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'service_type', label: 'Service type', required: true }], editFields: [{ name: 'service_type', label: 'Service type', required: true }, { name: 'status', label: 'Status', required: true }, { name: 'notes', label: 'Notes', type: 'textarea' }] },
  subscriptions: { endpoint: 'subscriptions', title: 'Subscriptions', eyebrow: 'Service commitments', description: 'Assign a plan to a subscriber and manage billing dates.', columns: ['Subscriber', 'Plan', 'Starts on', 'Next billing date'], createFields: [{ name: 'subscriber_id', label: 'Subscriber', required: true }, { name: 'plan_id', label: 'Plan', required: true }, { name: 'starts_on', label: 'Starts on', type: 'date', required: true }, { name: 'next_billing_date', label: 'Next billing date (automatic)', type: 'date', required: true, readOnly: true }], editFields: [{ name: 'starts_on', label: 'Starts on', type: 'date', required: true }, { name: 'next_billing_date', label: 'Next billing date (automatic)', type: 'date', required: true, readOnly: true }] },
  invoices: { endpoint: 'invoices', title: 'Invoices', eyebrow: 'Revenue cycle', description: 'Issued charges and outstanding balances.', columns: ['Invoice', 'Due date', 'Total', 'Status'], createFields: [{ name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'subscription_id', label: 'Subscription ID', type: 'number', required: true }, { name: 'issue_date', label: 'Issue date', type: 'date', required: true }, { name: 'due_date', label: 'Due date', type: 'date', required: true }], editFields: [] },
  payments: { endpoint: 'payments', title: 'Payments', eyebrow: 'Cash application', description: 'Recorded receipts and payment allocation.', columns: ['Payment', 'Reference', 'Amount', 'Status'], createFields: [{ name: 'billing_account_id', label: 'Billing account public ID', required: true }, { name: 'invoice_id', label: 'Invoice public ID', required: true }, { name: 'amount_minor', label: 'Amount (minor units)', type: 'number', required: true }, { name: 'payment_method', label: 'Payment method', required: true }, { name: 'reference', label: 'Reference' }], editFields: [],
  },
}

const statusBadge = (status: string) => <Badge className="billing-status-badge" variant={['cancelled', 'overdue', 'failed', 'suspended', 'void'].includes(status) ? 'destructive' : ['pending', 'draft', 'inactive'].includes(status) ? 'secondary' : 'outline'}>{status}</Badge>
const actionLabel = (row: Row) => row.legal_name || row.account_number || row.name || row.service_number || row.invoice_number || row.payment_number || row.plan_name_snapshot || 'record'
const rowId = (view: View, row: Row) => view === 'subscriptions' ? row.id : row.public_id
const nextBillingDate = (date: string, cycleDay: number) => { const value = new Date(`${date}T00:00:00`); const candidate = new Date(value.getFullYear(), value.getMonth(), Math.min(cycleDay, new Date(value.getFullYear(), value.getMonth() + 1, 0).getDate())); if (candidate <= value) candidate.setMonth(candidate.getMonth() + 1); return candidate.toISOString().slice(0, 10) }

function rowValues(view: View, row: Row): Record<string, string> {
  if (view === 'accounts') return { currency: row.currency || '', credit_limit_minor: String(row.credit_limit_minor || 0), status: row.status || '' }
  if (view === 'plans') return { code: row.code || '', name: row.name || '', price: row.versions?.[0] ? String((Number(row.versions[0].recurring_price_minor) / 100).toFixed(2)) : '', currency: row.versions?.[0]?.currency || 'PHP', service_type: row.service_type || 'postpaid', description: row.description || '', status: row.status || 'active' }
  if (view === 'services') return { service_type: row.service_type || '', status: row.status || '', notes: row.notes || '' }
  if (view === 'subscriptions') return { starts_on: String(row.starts_on || '').slice(0, 10), next_billing_date: String(row.next_billing_date || '').slice(0, 10), subscriber_id: row.service?.public_id || '', plan_id: String(row.plan_version?.plan_id || '') }
  if (view === 'billing-statements') return { statement_number: row.statement_number || '', subscriber: row.billing_account?.customer?.legal_name || '', period: `${formatDate(row.billing_period_start)} – ${formatDate(row.billing_period_end)}`, due_date: formatDate(row.due_date), total: formatMoney(row.total_minor, row.currency), status: row.status || '' }
  if (view === 'invoices') return { invoice_number: row.invoice_number || '', due_date: formatDate(row.due_date), total: formatMoney(row.total_minor, row.currency), status: row.status || '' }
  return { payment_number: row.payment_number || '', amount: formatMoney(row.amount_minor, row.currency), payment_method: row.payment_method || '', reference: row.reference || '', status: row.status || '' }
}

function rowCells(view: View, row: Row, actions: ReactNode) {
  if (view === 'accounts') return <><TableCell><div className="font-medium">{row.account_number}</div><div className="text-[10px] text-muted-foreground">{row.public_id}</div></TableCell><TableCell>{row.subscriber?.legal_name || '—'}</TableCell><TableCell>{row.currency}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'plans') return <><TableCell><div className="font-medium">{row.name}</div><div className="text-[10px] text-muted-foreground">{row.code}</div></TableCell><TableCell>{row.service_type}</TableCell><TableCell>{row.versions?.[0] ? `${row.versions[0].download_kbps / 1000} / ${row.versions[0].upload_kbps / 1000} Mbps` : '—'}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'services') return <><TableCell><div className="font-medium">{row.service_number}</div><div className="text-[10px] text-muted-foreground">{row.public_id}</div></TableCell><TableCell>{row.subscriber?.legal_name || '—'}</TableCell><TableCell>{row.service_type}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'subscriptions') return <><TableCell><div className="font-medium">{row.service?.customer?.legal_name || '—'}</div><div className="text-[10px] text-muted-foreground">{row.service?.service_number || '—'}</div></TableCell><TableCell>{row.plan_name_snapshot || '—'}</TableCell><TableCell>{formatDate(row.starts_on)}</TableCell><TableCell>{formatDate(row.next_billing_date)}</TableCell>{actions}</>
  if (view === 'billing-statements') return <><TableCell className="font-medium">{row.statement_number}</TableCell><TableCell>{row.billing_account?.customer?.legal_name || '—'}</TableCell><TableCell>{formatDate(row.billing_period_start)} – {formatDate(row.billing_period_end)}</TableCell><TableCell>{formatMoney(row.total_minor, row.currency)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  if (view === 'invoices') return <><TableCell><div className="font-medium">{row.invoice_number}</div><div className="text-[10px] text-muted-foreground">{row.billing_account?.subscriber?.legal_name || '—'}</div></TableCell><TableCell>{formatDate(row.due_date)}</TableCell><TableCell>{formatMoney(row.total_minor, row.currency)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
  return <><TableCell><div className="font-medium">{row.payment_number}</div><div className="text-[10px] text-muted-foreground">{row.payment_method}</div></TableCell><TableCell>{row.reference || '—'}</TableCell><TableCell>{formatMoney(row.amount_minor, row.currency)}</TableCell><TableCell>{statusBadge(row.status)}</TableCell>{actions}</>
}

function CycleSettingsModal({ session, onClose }: { session: Session; onClose: () => void }) {
  const [settings, setSettings] = useState({ cycle_start_day: '20', vat_rate: '12', installation_amortization_months: '0' }); const [saving, setSaving] = useState(false)
  useEffect(() => { void apiRequest<any>('/billing-settings', {}, session.token).then(response => setSettings({ cycle_start_day: String(response.data.cycle_start_day), vat_rate: String(response.data.vat_rate), installation_amortization_months: String(response.data.installation_amortization_months) })) }, [session.token])
  const save = async () => { setSaving(true); try { await notify.promise(apiRequest('/billing-settings', { method: 'PUT', body: JSON.stringify({ cycle_start_day: Number(settings.cycle_start_day), vat_rate: Number(settings.vat_rate), installation_amortization_months: Number(settings.installation_amortization_months) }) }, session.token), { loading: 'Saving cycle settings…', success: 'Cycle settings saved.', error: 'Unable to save cycle settings.' }); onClose() } finally { setSaving(false) } }
  return <div className="billing-modal-backdrop fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true"><Card className="billing-modal-card w-full max-w-md shadow-2xl"><CardHeader className="border-b"><CardTitle>Cycle settings</CardTitle><CardDescription>Configure the billing cycle and statement calculations.</CardDescription></CardHeader><CardContent><FieldGroup><Field><FieldLabel>Cycle starts on day</FieldLabel><Input type="number" min="1" max="28" value={settings.cycle_start_day} onChange={event => setSettings({ ...settings, cycle_start_day: event.target.value })} /></Field><Field><FieldLabel>VAT rate (%)</FieldLabel><Input type="number" min="0" max="100" step="0.01" value={settings.vat_rate} onChange={event => setSettings({ ...settings, vat_rate: event.target.value })} /></Field><Field><FieldLabel>Installation amortization (months)</FieldLabel><Input type="number" min="0" max="120" value={settings.installation_amortization_months} onChange={event => setSettings({ ...settings, installation_amortization_months: event.target.value })} /></Field></FieldGroup><div className="mt-6 flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="button" onClick={() => void save()} disabled={saving}>{saving ? 'Saving…' : 'Save settings'}</Button></div></CardContent></Card></div>
}

function BillingPreview({ values, options, branding }: { values: Record<string, string>; options: { value: string; label: string }[]; branding?: Row }) {
  const plan = options.find(option => option.value === values.plan_id)?.label || 'Selected plan'
  const pdf = new BillingStatementPdf()
  const input = { plan, startsOn: values.starts_on || '', nextBillingDate: values.next_billing_date || '', organizationName: branding?.short_name || branding?.organization_name, logoUrl: branding?.logo_url }
  const preview = async (kind: 'pro-rated' | 'full-cycle') => pdf.open(await (kind === 'pro-rated' ? pdf.newSubscriptionProratedBillingStatement(input) : pdf.newSubscriptionFullCycleBillingStatement(input)))
  return <div className="mt-7 rounded-xl border border-border/70 bg-muted/20 p-5 sm:col-span-2"><div className="mb-4 flex items-start justify-between gap-4"><div><p className="text-sm font-semibold">Upcoming billing statement</p><p className="mt-1 text-xs text-muted-foreground">Review the first-cycle charges before creating the subscription.</p></div><span className="rounded-full border border-border bg-background px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-muted-foreground">Preview</span></div><div className="overflow-hidden rounded-lg border border-border/70 bg-background"><table className="w-full text-xs"><thead className="bg-muted/35"><tr><th className="px-4 py-3 text-left font-medium">Charge breakdown</th><th className="px-4 py-3 text-left font-medium">Billing period</th><th className="px-4 py-3 text-right font-medium">Action</th></tr></thead><tbody><tr className="border-t"><td className="px-4 py-3.5"><p className="font-medium">Pro-rated recurring</p><p className="mt-0.5 text-[11px] text-muted-foreground">Partial period before the regular cycle</p></td><td className="px-4 py-3.5 text-muted-foreground">Start date → cycle end</td><td className="px-4 py-3.5 text-right"><Button type="button" variant="outline" size="sm" onClick={() => preview('pro-rated')}>Preview statement</Button></td></tr><tr className="border-t"><td className="px-4 py-3.5"><p className="font-medium">Full-cycle recurring</p><p className="mt-0.5 text-[11px] text-muted-foreground">Regular recurring plan charge</p></td><td className="px-4 py-3.5 text-muted-foreground">Full billing cycle</td><td className="px-4 py-3.5 text-right"><Button type="button" variant="outline" size="sm" onClick={() => preview('full-cycle')}>Preview statement</Button></td></tr></tbody></table></div></div>
}

function BillingStatementActions({ row, branding }: { row: Row; branding?: Row }) {
  const pdf = new BillingStatementPdf()
  const input = { plan: row.subscription?.plan_name_snapshot || row.subscription?.plan_version?.plan?.name || 'Plan', subscriber: row.billing_account?.customer?.legal_name || 'Subscriber account', startsOn: String(row.billing_period_start || '').slice(0, 10), nextBillingDate: String(row.due_date || '').slice(0, 10), organizationName: branding?.short_name || branding?.organization_name, logoUrl: branding?.logo_url }
  const run = async (download: boolean) => { const blob = await pdf.newSubscriptionFullCycleBillingStatement(input); if (!download) return pdf.open(blob); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = `${row.statement_number || 'billing-statement'}.pdf`; link.click(); window.setTimeout(() => URL.revokeObjectURL(url), 60_000) }
  return <div className="mt-6 grid gap-4"><div className="grid gap-3 sm:grid-cols-2"><div className="rounded-lg border border-border/70 bg-muted/20 p-3"><p className="billing-modal-eyebrow">Subscriber</p><p className="mt-1 text-sm font-medium">{input.subscriber}</p></div><div className="rounded-lg border border-border/70 bg-muted/20 p-3"><p className="billing-modal-eyebrow">Plan</p><p className="mt-1 text-sm font-medium">{input.plan}</p></div></div><div className="flex justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" size="sm" onClick={() => void run(false)}><FilePdfIcon data-icon="inline-start" />Preview PDF</Button><Button type="button" size="sm" onClick={() => void run(true)}><DownloadSimpleIcon data-icon="inline-start" />Download PDF</Button></div></div>
}


export function ResourceTablePage({ session, view }: { session: Session; view: View }) {
  const config = configs[view]
  const [rows, setRows] = useState<Row[]>([])
  const [error, setError] = useState('')
  const [errorTitle, setErrorTitle] = useState('Could not load records')
  const [search, setSearch] = useState('')
  const [modalError, setModalError] = useState('')
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState<ModalState>(null)
  const [cycleSettingsOpen, setCycleSettingsOpen] = useState(false)
  const [cycleStartDay, setCycleStartDay] = useState(20)
  const [branding, setBranding] = useState<Row>()
  const [subscriptionOptions, setSubscriptionOptions] = useState<{ subscriber: { value: string; label: string }[]; plan: { value: string; label: string; inactive?: boolean }[]; subscription: { value: string; label: string }[] }>({ subscriber: [], plan: [], subscription: [] })
  const load = useCallback(() => apiRequest<any>(`/${config.endpoint}`, {}, session.token).then(response => setRows(response.data.data)).catch(exception => { setErrorTitle('Could not load records'); setError(getErrorMessage(exception, 'Unable to load records.')) }), [config.endpoint, session.token])
  useEffect(() => { void load() }, [load])
  useEffect(() => {
    if (view !== 'subscriptions' && view !== 'billing-statements') return
    void Promise.all([
      apiRequest<any>('/customers?per_page=100', {}, session.token),
      apiRequest<any>('/plans?per_page=100', {}, session.token),
      apiRequest<any>('/subscriptions?per_page=100', {}, session.token),
      apiRequest<any>('/billing-settings', {}, session.token),
      apiRequest<any>('/branding', {}, session.token),
    ]).then(([customers, plans, subscriptions, settings, brand]) => {
      setCycleStartDay(Number(settings.data.cycle_start_day || 20))
      setBranding(brand.data.branding)
      setSubscriptionOptions({
        subscriber: customers.data.data.map((customer: Row) => ({ value: customer.public_id, label: customer.legal_name || customer.customer_number })),
        plan: plans.data.data.flatMap((plan: Row) => (plan.versions || []).map((version: Row) => ({
          value: String(version.id),
          label: `${plan.name} — ${formatMoney(version.recurring_price_minor, version.currency)}${plan.status !== 'active' ? ' (Inactive)' : ''}`,
          inactive: plan.status !== 'active',
        }))),
        subscription: subscriptions.data.data.map((subscription: Row) => ({ value: String(subscription.id), label: `${subscription.service?.customer?.legal_name || 'Subscriber'} — ${subscription.plan_name_snapshot || 'Plan'}` })),
      })
    }).catch(() => setSubscriptionOptions({ subscriber: [], plan: [], subscription: [] }))
  }, [session.token, view])
  const close = () => { setModal(null); setModalError('') }
  const submit = async (values: Record<string, string>) => {
    if (!modal || modal.mode === 'view') return
    setLoading(true); setModalError('')
    try {
      const selectedPlan = view === 'subscriptions' && modal.mode === 'create' ? subscriptionOptions.plan.find(plan => plan.value === values.plan_id) : undefined
      if (selectedPlan?.inactive) {
        const message = 'Inactive plans cannot be assigned to subscribers. Activate the plan before creating a subscription.'
        notify.warning(message)
        return
      }
      const payload = view === 'subscriptions' && modal.mode === 'create' ? { subscriber_id: values.subscriber_id, plan_version_id: Number(values.plan_id), starts_on: values.starts_on, next_billing_date: values.next_billing_date } : view === 'plans' ? { ...values, price: Number(values.price) } : values
      const operation = apiRequest(modal.mode === 'create' ? `/${config.endpoint}` : `/${config.endpoint}/${rowId(view, modal.row!)}`, { method: modal.mode === 'create' ? 'POST' : 'PUT', body: JSON.stringify(payload) }, session.token)
      await notify.promise(operation, { loading: modal.mode === 'create' ? `Creating ${config.title.toLowerCase().replace(/s$/, '')}…` : `Saving ${config.title.toLowerCase().replace(/s$/, '')}…`, success: modal.mode === 'create' ? 'Record created.' : 'Record updated.', error: 'Unable to save this record.' })
      close()
      await load()
    }
    catch (exception) { setModalError(getErrorMessage(exception, 'Unable to save this record.')) }
    finally { setLoading(false) }
  }
  const archive = async (row: Row) => { try { await notify.promise(apiRequest(`/${config.endpoint}/${rowId(view, row)}`, { method: 'DELETE' }, session.token), { loading: 'Archiving record…', success: 'Record archived.', error: 'Unable to archive this record.' }); await load() } catch (exception) { setErrorTitle('Unable to archive record'); setError(getErrorMessage(exception, 'Unable to archive this record.')) } }
  const remove = async (row: Row) => { try { await notify.promise(apiRequest(`/${config.endpoint}/${rowId(view, row)}?permanent=1`, { method: 'DELETE' }, session.token), { loading: 'Deleting record…', success: 'Record permanently deleted.', error: 'Unable to permanently delete this record.' }); await load() } catch (exception) { setErrorTitle(`Unable to delete ${config.title.replace(/s$/, '').toLowerCase()}`); setError(getErrorMessage(exception, 'Unable to permanently delete this record.')) } }
  const voidRecord = async (row: Row) => { try { await notify.promise(apiRequest(`/${config.endpoint}/${rowId(view, row)}/void`, { method: 'POST' }, session.token), { loading: 'Voiding record…', success: 'Record voided.', error: 'Unable to void this record.' }); await load() } catch (exception) { setErrorTitle('Unable to void record'); setError(getErrorMessage(exception, 'Unable to void this record.')) } }
  const baseModalFields = modal?.mode === 'view' ? [...config.editFields, ...(view === 'billing-statements' ? [{ name: 'statement_number', label: 'Statement number' }, { name: 'subscriber', label: 'Subscriber' }, { name: 'period', label: 'Billing period' }, { name: 'due_date', label: 'Due date' }, { name: 'total', label: 'Total' }, { name: 'status', label: 'Status' }] : view === 'invoices' ? [{ name: 'invoice_number', label: 'Invoice number' }, { name: 'total', label: 'Total' }] : view === 'payments' ? [{ name: 'payment_number', label: 'Payment number' }, { name: 'amount', label: 'Amount' }] : [])] : modal?.mode === 'create' ? config.createFields : config.editFields
  const modalFields = view === 'subscriptions' || view === 'billing-statements' ? baseModalFields.map(field => field.name === 'subscriber_id' ? { ...field, options: subscriptionOptions.subscriber } : field.name === 'plan_id' ? { ...field, options: subscriptionOptions.plan } : field.name === 'subscription_id' ? { ...field, options: subscriptionOptions.subscription } : field) : baseModalFields
  const modalValues = modal?.row ? (view === 'subscriptions' ? { ...rowValues(view, modal.row), next_billing_date: nextBillingDate(String(modal.row.starts_on).slice(0, 10), cycleStartDay) } : rowValues(view, modal.row)) : view === 'subscriptions' ? { starts_on: new Date().toISOString().slice(0, 10), next_billing_date: nextBillingDate(new Date().toISOString().slice(0, 10), cycleStartDay) } : view === 'plans' ? { currency: 'PHP', service_type: 'postpaid', status: 'active' } : {}
  const isFinancial = view === 'invoices' || view === 'payments'
  const visibleRows = rows.filter(row => JSON.stringify(row).toLowerCase().includes(search.toLowerCase()))
  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex items-center justify-between gap-4"><h1 className="text-sm font-semibold">{config.title}</h1>{view === 'billing-statements' && <Button variant="outline" onClick={() => setCycleSettingsOpen(true)}>Cycle settings</Button>}<Button onClick={() => setModal({ mode: 'create' })}><PlusIcon data-icon="inline-start" />New {config.title.replace(/s$/, '').toLowerCase()}</Button></div>{error && <Alert variant="destructive"><AlertTitle>{errorTitle}</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}<div className="billing-records flex flex-col gap-4"><div className="billing-record-toolbar flex w-full flex-col gap-4 border-b border-border/60 pb-4 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex items-center gap-2"><p className="text-sm font-semibold">{config.title}</p><span className="bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground">{rows.length} records</span></div><p className="mt-1 text-xs text-muted-foreground">Installation-wide records ready for billing operations.</p></div><div className="relative w-full sm:w-80"><MagnifyingGlassIcon size={14} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" /><Input className="h-9 w-full pl-9 shadow-[0_8px_20px_-16px_rgb(24_35_54_/_55%)]" placeholder="Filter records" value={search} onChange={event => setSearch(event.target.value)} /></div></div><Table className="min-w-[860px]"><TableHeader><TableRow>{config.columns.map(column => <TableHead key={column}>{column}</TableHead>)}<TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{visibleRows.length ? visibleRows.map(row => { const label = actionLabel(row); const actions = <TableCell className="text-right"><TableActions label={label} kind={isFinancial ? 'financial' : 'operational'} onView={() => setModal({ mode: 'view', row })} onEdit={!isFinancial && view !== 'billing-statements' && config.editFields.length ? () => setModal({ mode: 'edit', row }) : undefined} onArchive={!isFinancial ? () => { void archive(row) } : undefined} onDelete={() => { void remove(row) }} onVoid={isFinancial ? () => { void voidRecord(row) } : undefined} /></TableCell>; return <TableRow key={row.id}>{rowCells(view, view === 'subscriptions' ? { ...row, __branding: branding } : row, actions)}</TableRow> }) : <TableRow><TableCell colSpan={config.columns.length + 1} className="h-48 text-center">No records found.</TableCell></TableRow>}</TableBody></Table></div><CrudModal key={modal ? `${modal.mode}-${modal.row?.id || modal.row?.public_id || 'new'}` : 'closed'} open={modal !== null} mode={modal?.mode || 'view'} title={modal?.mode === 'create' ? `New ${config.title.replace(/s$/, '').toLowerCase()}` : modal?.mode === 'edit' ? `Edit ${config.title.replace(/s$/, '').toLowerCase()}` : `${config.title} details`} description={modal?.mode === 'view' ? 'Review this record.' : 'Save changes through the billing API.'} fields={modalFields} initialValues={modalValues} error={modalError} loading={loading} onClose={close} onSubmit={submit} wide={view === 'subscriptions' && modal?.mode === 'create'} renderExtra={view === 'subscriptions' && modal?.mode === 'create' ? values => <BillingPreview values={values} options={subscriptionOptions.plan} branding={branding} /> : view === 'billing-statements' && modal?.mode === 'view' && modal?.row ? () => <BillingStatementActions row={modal.row!} branding={branding} /> : undefined} />{view === 'billing-statements' && cycleSettingsOpen && <CycleSettingsModal session={session} onClose={() => setCycleSettingsOpen(false)} />}</div>
}
