import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ArrowCounterClockwiseIcon, FloppyDiskIcon, PaperPlaneTiltIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { getErrorMessage, notify } from '@/lib/notifications'
import { getNotificationSettings, sendNotificationTest, updateNotificationSettings, type NotificationSettings } from '@/lib/notificationSettings'
import { hasPermission } from '@/lib/usersRoles'
import { useConfirm } from './ConfirmProvider'

const defaults: NotificationSettings = { telegram_enabled: false, token_configured: false, telegram_chat_id: '' }

export function NotificationsPage({ token, permissions, isSuperadmin = false, settings: initialSettings }: { token?: string; permissions?: string[]; isSuperadmin?: boolean; settings?: NotificationSettings }) {
  const [settings, setSettings] = useState(initialSettings || defaults)
  const [botToken, setBotToken] = useState('')
  const [loading, setLoading] = useState(!initialSettings)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [error, setError] = useState('')
  const canView = isSuperadmin || hasPermission(permissions, 'notifications.view')
  const canUpdate = isSuperadmin || hasPermission(permissions, 'notifications.update')
  const canTest = isSuperadmin || hasPermission(permissions, 'notifications.test')
  const confirm = useConfirm()

  useEffect(() => {
    if (initialSettings) { setSettings(initialSettings); setLoading(false); return }
    if (!canView) { setLoading(false); return }
    void getNotificationSettings(token).then(response => setSettings(response.data.settings)).catch(exception => setError(getErrorMessage(exception, 'Unable to load notification settings.'))).finally(() => setLoading(false))
  }, [canView, initialSettings, token])

  if (!canView) return <Alert variant="destructive"><AlertTitle>Access denied</AlertTitle><AlertDescription>You do not have permission to view notification settings.</AlertDescription></Alert>
  if (loading) return <div className="rounded-lg border bg-card p-6 text-sm text-muted-foreground">Loading notification settings…</div>

  const save = async (event: FormEvent) => {
    event.preventDefault(); if (!canUpdate) return
    setSaving(true); setError('')
    try { const response = await notify.promise(updateNotificationSettings({ ...settings, telegram_bot_token: botToken }, token), { loading: 'Saving notification settings…', success: 'Notification settings saved.', error: 'Unable to save notification settings.' }); setSettings(response.data.settings); setBotToken('') }
    catch (exception) { setError(getErrorMessage(exception, 'Unable to save notification settings.')) } finally { setSaving(false) }
  }
  const reset = async () => {
    if (!canUpdate) return
    const confirmed = await confirm({ title: 'Reset Telegram fields?', description: 'This will clear the bot token and chat ID from the form. Nothing will be saved.', confirmLabel: 'Reset fields', destructive: true })
    if (!confirmed) return
    setBotToken('')
    setSettings(current => ({ ...current, telegram_chat_id: '' }))
    setError('')
  }
  const test = async () => { if (!canTest) return; setTesting(true); setError(''); try { await notify.promise(sendNotificationTest(token), { loading: 'Sending test notification…', success: 'Test notification sent.', error: 'Unable to send test notification.' }) } catch (exception) { setError(getErrorMessage(exception, 'Unable to send test notification.')) } finally { setTesting(false) } }

  return <div className="flex flex-col gap-5">
    <div className="billing-page-heading"><h1 className="text-sm font-semibold">Notifications</h1></div>
    {error && <Alert variant="destructive"><AlertTitle>Notification action failed</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
    <Card className="billing-records"><CardHeader className="border-b"><CardTitle>Telegram notifications</CardTitle><CardDescription>Configure a Telegram bot for operational alerts. This integration is disabled until you enable it and save valid credentials.</CardDescription></CardHeader><CardContent><form onSubmit={save}><FieldGroup className="gap-4"><label htmlFor="telegram-enabled" className="flex items-center justify-between rounded-lg border bg-muted/35 px-4 py-3"><span><span className="block text-sm font-medium">Enable Telegram notifications</span><span className="mt-1 block text-xs text-muted-foreground">Notifications are disabled by default.</span></span><span className="flex items-center gap-2"><span className="text-[10px] uppercase tracking-wider text-muted-foreground">{settings.telegram_enabled ? 'On' : 'Off'}</span><input id="telegram-enabled" aria-label="Enable Telegram notifications" type="checkbox" className="size-5 accent-primary" checked={settings.telegram_enabled} onChange={event => setSettings(current => ({ ...current, telegram_enabled: event.target.checked }))} disabled={!canUpdate} /></span></label><div className="grid gap-4 md:grid-cols-2"><Field><FieldLabel htmlFor="telegram-token">Bot token</FieldLabel><Input id="telegram-token" aria-label="Telegram bot token" type="password" placeholder={settings.token_configured ? 'Configured — enter only to replace' : 'Enter Telegram bot token'} value={botToken} onChange={event => setBotToken(event.target.value)} disabled={!canUpdate} /><p className="mt-1 text-xs text-muted-foreground">Leave blank to keep the saved token.</p></Field><Field><FieldLabel htmlFor="telegram-chat-id">Chat ID</FieldLabel><Input id="telegram-chat-id" aria-label="Telegram chat ID" placeholder="-1001234567890" value={settings.telegram_chat_id} onChange={event => setSettings(current => ({ ...current, telegram_chat_id: event.target.value }))} disabled={!canUpdate} /><p className="mt-1 text-xs text-muted-foreground">The chat or channel where alerts should be sent.</p></Field></div></FieldGroup><div className="mt-6 flex flex-wrap justify-end gap-2 border-t pt-4"><Button type="button" variant="outline" onClick={() => void reset()} disabled={!canUpdate}><ArrowCounterClockwiseIcon data-icon="inline-start" />Reset</Button>{canTest && <Button type="button" variant="outline" onClick={() => void test()} disabled={testing || !settings.telegram_enabled}><PaperPlaneTiltIcon data-icon="inline-start" />{testing ? 'Sending…' : 'Send test notification'}</Button>}{canUpdate && <Button type="submit" disabled={saving}><FloppyDiskIcon data-icon="inline-start" />{saving ? 'Saving…' : 'Save changes'}</Button>}</div></form></CardContent></Card>
  </div>
}
