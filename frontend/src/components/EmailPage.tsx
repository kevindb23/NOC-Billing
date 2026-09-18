import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ArrowCounterClockwiseIcon, FloppyDiskIcon, PaperPlaneTiltIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { getErrorMessage, notify } from '@/lib/notifications'
import { getEmailSettings, sendTestEmail, updateEmailSettings, type EmailSettings } from '@/lib/email'
import { hasPermission } from '@/lib/usersRoles'

const defaults: EmailSettings = { provider: 'gmail', host: 'smtp.gmail.com', port: 587, encryption: 'tls', username: '', password_configured: false, from_name: '', from_email: '' }

export function EmailPage({ token, permissions, isSuperadmin = false, settings: initialSettings, onSaved }: { token?: string; permissions?: string[]; isSuperadmin?: boolean; settings?: EmailSettings; onSaved?: (settings: EmailSettings) => void }) {
  const [settings, setSettings] = useState<EmailSettings>(initialSettings || defaults)
  const [password, setPassword] = useState('')
  const [recipient, setRecipient] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(!initialSettings)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const canView = isSuperadmin || hasPermission(permissions, 'email.view')
  const canUpdate = isSuperadmin || hasPermission(permissions, 'email.update')
  const canTest = isSuperadmin || hasPermission(permissions, 'email.test')

  useEffect(() => {
    if (initialSettings) { setSettings(initialSettings); setLoading(false); return }
    if (!canView) { setLoading(false); return }
    void getEmailSettings(token).then(response => setSettings(response.data.settings)).catch(exception => setError(getErrorMessage(exception, 'Unable to load email settings.'))).finally(() => setLoading(false))
  }, [canView, initialSettings, token])

  if (!canView) return <Alert variant="destructive"><AlertTitle>Access denied</AlertTitle><AlertDescription>You do not have permission to view email settings.</AlertDescription></Alert>
  if (loading) return <div className="rounded-lg border bg-card p-6 text-sm text-muted-foreground">Loading email settings…</div>

  const change = (field: keyof EmailSettings, value: string | number) => setSettings(current => ({ ...current, [field]: value }))
  const selectProvider = (provider: EmailSettings['provider']) => {
    const presets = { gmail: ['smtp.gmail.com', 587, 'tls'], outlook: ['smtp.office365.com', 587, 'tls'], mailgun: ['smtp.mailgun.org', 587, 'tls'], custom: [settings.host, settings.port, settings.encryption] } as const
    const [host, port, encryption] = presets[provider]
    setSettings(current => ({ ...current, provider, host, port, encryption }))
  }
  const reset = () => { if (initialSettings) setSettings(initialSettings); setPassword(''); setError('') }
  const save = async (event: FormEvent) => {
    event.preventDefault(); if (!canUpdate) return
    setSaving(true); setError('')
    try {
      const response = await notify.promise(updateEmailSettings({ ...settings, password }, token), { loading: 'Saving email settings…', success: 'Email settings saved.', error: 'Unable to save email settings.' })
      setSettings(response.data.settings); setPassword(''); onSaved?.(response.data.settings)
    } catch (exception) { setError(getErrorMessage(exception, 'Unable to save email settings.')) } finally { setSaving(false) }
  }
  const test = async () => {
    if (!canTest || !recipient) return
    setTesting(true); setError('')
    try { await notify.promise(sendTestEmail(recipient, token), { loading: 'Sending test email…', success: 'Test email sent.', error: 'Unable to send test email.' }) }
    catch (exception) { setError(getErrorMessage(exception, 'Unable to send test email.')) } finally { setTesting(false) }
  }

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading flex items-center justify-between gap-4"><div><h1 className="text-sm font-semibold">Email</h1><p className="mt-1 text-xs text-muted-foreground">Configure the SMTP account used for billing messages.</p></div></div>
    {error && <Alert variant="destructive"><AlertTitle>Email action failed</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    <form onSubmit={save} className="grid gap-4 xl:grid-cols-[minmax(0,1.28fr)_minmax(22rem,0.82fr)]">
      <Card className="billing-records"><CardHeader className="border-b"><CardTitle>SMTP server</CardTitle><CardDescription>Use a preset to populate standard provider settings, then enter your account credentials.</CardDescription></CardHeader><CardContent><FieldGroup className="gap-4">
        <Field><FieldLabel htmlFor="email-provider">Provider preset</FieldLabel><select id="email-provider" aria-label="Provider preset" className="h-9 rounded-none border border-input bg-background px-3 text-xs" value={settings.provider} onChange={event => selectProvider(event.target.value as EmailSettings['provider'])}><option value="gmail">Gmail</option><option value="outlook">Microsoft 365 / Outlook</option><option value="mailgun">Mailgun</option><option value="custom">Custom SMTP</option></select></Field>
        <Field><FieldLabel htmlFor="email-host">SMTP host</FieldLabel><Input id="email-host" aria-label="SMTP host" value={settings.host} onChange={event => change('host', event.target.value)} disabled={!canUpdate} /></Field>
        <div className="grid gap-4 sm:grid-cols-2"><Field><FieldLabel htmlFor="email-port">Port</FieldLabel><Input id="email-port" aria-label="Port" type="number" min="1" max="65535" value={settings.port} onChange={event => change('port', Number(event.target.value))} disabled={!canUpdate} /></Field><Field><FieldLabel htmlFor="email-encryption">Encryption</FieldLabel><select id="email-encryption" aria-label="Encryption" className="h-9 w-full rounded-none border border-input bg-background px-3 text-xs" value={settings.encryption} onChange={event => change('encryption', event.target.value)} disabled={!canUpdate}><option value="tls">TLS / STARTTLS</option><option value="ssl">SSL</option><option value="none">None</option></select></Field></div>
        <Field><FieldLabel htmlFor="email-username">Username</FieldLabel><Input id="email-username" aria-label="Username" value={settings.username} onChange={event => change('username', event.target.value)} disabled={!canUpdate} /></Field>
        <Field><FieldLabel htmlFor="email-password">Password</FieldLabel><Input id="email-password" aria-label="Password" type="password" placeholder={settings.password_configured ? 'Configured — enter only to replace' : 'Enter SMTP password'} value={password} onChange={event => setPassword(event.target.value)} disabled={!canUpdate} /><p className="mt-1 text-xs text-muted-foreground">Leave blank to keep the saved password.</p></Field>
      </FieldGroup></CardContent></Card>
      <Card className="billing-records"><CardHeader className="border-b"><CardTitle>Sender identity</CardTitle><CardDescription>These values appear in outgoing billing messages.</CardDescription></CardHeader><CardContent><FieldGroup className="gap-4"><Field><FieldLabel htmlFor="email-from-name">From name</FieldLabel><Input id="email-from-name" aria-label="From name" value={settings.from_name} onChange={event => change('from_name', event.target.value)} disabled={!canUpdate} /></Field><Field><FieldLabel htmlFor="email-from-email">From email</FieldLabel><Input id="email-from-email" aria-label="From email" type="email" value={settings.from_email} onChange={event => change('from_email', event.target.value)} disabled={!canUpdate} /></Field><div className="rounded-md border border-primary/30 bg-primary/5 p-3 text-xs leading-5 text-muted-foreground">Gmail usually requires an app password. Email hosting uses secure email ports: SSL port 465 or TLS port 587.</div><div className="border-t pt-4"><Field><FieldLabel htmlFor="email-test-recipient">Test email recipient</FieldLabel><Input id="email-test-recipient" aria-label="Test email recipient" type="email" placeholder="you@example.com" value={recipient} onChange={event => setRecipient(event.target.value)} disabled={!canTest} /><p className="mt-1 text-xs text-muted-foreground">Sends a real test message using the current saved SMTP settings.</p></Field><Button type="button" variant="outline" className="mt-3" disabled={!canTest || !recipient || testing} onClick={() => void test()}><PaperPlaneTiltIcon data-icon="inline-start" />{testing ? 'Sending…' : 'Send test email'}</Button></div></FieldGroup></CardContent></Card>
      <div className="flex justify-end gap-2 xl:col-span-2"><Button type="button" variant="outline" onClick={reset} disabled={!canUpdate}><ArrowCounterClockwiseIcon data-icon="inline-start" />Reset</Button>{canUpdate && <Button type="submit" disabled={saving}><FloppyDiskIcon data-icon="inline-start" />{saving ? 'Saving…' : 'Save changes'}</Button>}</div>
    </form>
  </div>
}
