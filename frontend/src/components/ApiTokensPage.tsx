import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { BookOpenTextIcon, CopyIcon, KeyIcon, TrashIcon } from '@phosphor-icons/react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { getErrorMessage, notify } from '@/lib/notifications'
import { createApiToken, listApiTokens, revokeApiToken, type ApiToken } from '@/lib/apiTokens'
import { hasPermission } from '@/lib/usersRoles'
import { useConfirm } from './ConfirmProvider'

const modules = ['customers', 'billing-accounts', 'billing-statements', 'plans', 'subscriber-services', 'subscriptions', 'invoices', 'payments', 'users', 'roles', 'branding', 'api-tokens', 'email', 'notifications', 'paymongo', 'gcash', 'audit-logs']
const scopes = modules.flatMap(module => ['view', 'create', 'update', 'delete', 'test', 'export'].map(action => `${module}.${action}`))

export function ApiTokensPage({ token, permissions, isSuperadmin = false }: { token?: string; permissions?: string[]; isSuperadmin?: boolean }) {
  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [name, setName] = useState('')
  const [expiresAt, setExpiresAt] = useState('')
  const [noExpiration, setNoExpiration] = useState(false)
  const [selected, setSelected] = useState<string[]>(['customers.view'])
  const [plainToken, setPlainToken] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const confirm = useConfirm()
  const canView = isSuperadmin || hasPermission(permissions, 'api-tokens.view')
  const canCreate = isSuperadmin || hasPermission(permissions, 'api-tokens.create')
  const canDelete = isSuperadmin || hasPermission(permissions, 'api-tokens.delete')
  const load = useCallback(async () => { if (!canView) { setLoading(false); return }; try { const response = await listApiTokens(token); setTokens(response.data) } catch (exception) { setError(getErrorMessage(exception, 'Unable to load API tokens.')) } finally { setLoading(false) } }, [canView, token])
  useEffect(() => { void load() }, [load])

  if (!canView) return <Alert variant="destructive"><AlertTitle>Access denied</AlertTitle><AlertDescription>You do not have permission to view API tokens.</AlertDescription></Alert>
  const generate = async (event: FormEvent) => { event.preventDefault(); if (!canCreate || !name.trim() || !selected.length) return; setSaving(true); setError(''); try { const response = await notify.promise(createApiToken({ name: name.trim(), abilities: selected, ...(!noExpiration && expiresAt ? { expires_at: expiresAt } : {}) }, token), { loading: 'Generating API token…', success: 'API token generated.', error: 'Unable to generate API token.' }); setPlainToken(response.data.token); setName(''); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to generate API token.')) } finally { setSaving(false) } }
  const revoke = async (item: ApiToken) => { if (!canDelete) return; const ok = await confirm({ title: `Revoke ${item.name}?`, description: 'Any integration using this token will immediately lose access.', confirmLabel: 'Revoke token', destructive: true }); if (!ok) return; try { await notify.promise(revokeApiToken(item.id, token), { loading: 'Revoking API token…', success: 'API token revoked.', error: 'Unable to revoke API token.' }); await load() } catch (exception) { setError(getErrorMessage(exception, 'Unable to revoke API token.')) } }
  const copy = async () => { await navigator.clipboard?.writeText(plainToken); notify.success('Token copied.') }

  return <div className="flex flex-col gap-5"><div className="billing-page-heading flex items-center justify-between gap-4"><div><h1 className="text-sm font-semibold">API Tokens</h1><p className="mt-1 text-xs text-muted-foreground">Create scoped bearer tokens for external billing integrations.</p></div><Button variant="outline" asChild><a href="/api/v1/docs" target="_blank" rel="noreferrer"><BookOpenTextIcon data-icon="inline-start" />API Docs</a></Button></div>{error && <Alert variant="destructive"><AlertTitle>API token action failed</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}{plainToken && <Alert><AlertTitle>Copy this token now</AlertTitle><AlertDescription className="flex flex-wrap items-center gap-2"><Input aria-label="Generated API token" readOnly value={plainToken} className="min-w-72 font-mono" /><Button type="button" variant="outline" onClick={() => void copy()}><CopyIcon data-icon="inline-start" />Copy token</Button></AlertDescription></Alert>}<div className="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.35fr)]"><Card className="billing-records"><CardHeader className="border-b"><CardTitle>Generate token</CardTitle><CardDescription>Choose only the scopes the integration needs. The secret is shown once.</CardDescription></CardHeader><CardContent><form onSubmit={generate}><FieldGroup><Field><FieldLabel htmlFor="api-token-name">Token name</FieldLabel><Input id="api-token-name" aria-label="Token name" placeholder="Billing integration" value={name} onChange={event => setName(event.target.value)} disabled={!canCreate} /></Field><Field><FieldLabel htmlFor="api-token-expiry">Expires on</FieldLabel><div className="flex items-center gap-3"><Input id="api-token-expiry" aria-label="Expires on" type="date" value={expiresAt} onChange={event => setExpiresAt(event.target.value)} disabled={!canCreate || noExpiration} /><label className="flex shrink-0 items-center gap-2 text-xs"><input type="checkbox" aria-label="No expiration" checked={noExpiration} onChange={event => { setNoExpiration(event.target.checked); if (event.target.checked) setExpiresAt('') }} disabled={!canCreate} />No expiration</label></div></Field><fieldset><legend className="mb-2 text-xs font-medium">Scopes</legend><div className="grid gap-2 sm:grid-cols-2">{scopes.map(scope => <label className="flex items-center gap-2 text-xs" key={scope}><input type="checkbox" aria-label={scope} checked={selected.includes(scope)} onChange={event => setSelected(current => event.target.checked ? [...current, scope] : current.filter(item => item !== scope))} disabled={!canCreate} />{scope}</label>)}</div></fieldset><Button type="submit" disabled={!canCreate || saving || !name.trim() || !selected.length}><KeyIcon data-icon="inline-start" />{saving ? 'Generating…' : 'Generate token'}</Button></FieldGroup></form></CardContent></Card><Card className="billing-records"><CardHeader className="border-b"><CardTitle>Issued tokens</CardTitle><CardDescription>Tokens are visible only to you within the active organization.</CardDescription></CardHeader><CardContent className="p-0">{loading ? <p className="p-5 text-xs text-muted-foreground">Loading tokens…</p> : tokens.length === 0 ? <p className="p-5 text-xs text-muted-foreground">No API tokens have been issued.</p> : <div className="divide-y">{tokens.map(item => <div className="flex items-center justify-between gap-4 p-4" key={item.id}><div className="min-w-0"><p className="text-sm font-medium">{item.name}</p><div className="mt-1 flex flex-wrap gap-1">{item.abilities.map(ability => <Badge variant="outline" key={ability}>{ability}</Badge>)}</div><p className="mt-2 text-[10px] text-muted-foreground">Created {new Date(item.created_at).toLocaleDateString()}{item.expires_at ? ` · Expires ${new Date(item.expires_at).toLocaleDateString()}` : ''}</p></div>{canDelete && <Button type="button" variant="ghost" size="icon" aria-label={`Revoke ${item.name}`} onClick={() => void revoke(item)}><TrashIcon /></Button>}</div>)}</div>}</CardContent></Card></div></div>
}
