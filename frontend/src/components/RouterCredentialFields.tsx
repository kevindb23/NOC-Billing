import { CaretDownIcon, KeyIcon, LockKeyIcon } from '@phosphor-icons/react'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

export type RouterTransport = 'api' | 'ssh' | 'netconf' | 'snmp'
export type RouterCredentialValues = Record<string, string>

type CredentialMetadata = {
  connection_metadata?: Record<string, unknown> | null
  auth_type?: string | null
  version?: number | null
}

type RouterCredentialFieldsProps = {
  transport: RouterTransport
  values: RouterCredentialValues
  configured?: boolean
  metadata?: CredentialMetadata | null
  errors?: Record<string, string>
  onChange: (name: string, value: string) => void
}

const fieldClassName = 'h-9 bg-background/60'

function NativeSelect({ id, label, value, options, onChange, required, error }: {
  id: string
  label: string
  value: string
  options: Array<{ value: string; label: string }>
  onChange: (value: string) => void
  required?: boolean
  error?: string
}) {
  return <Field>
    <FieldLabel htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</FieldLabel>
    <div className="relative">
      <select id={id} className={`${fieldClassName} w-full appearance-none border border-input px-2.5 pr-9 text-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50`} value={value} onChange={event => onChange(event.target.value)} required={required} aria-invalid={error ? true : undefined}>
        {options.map(option => <option value={option.value} key={option.value}>{option.label}</option>)}
      </select>
      <CaretDownIcon aria-hidden="true" className="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
    </div>
    {error && <p className="text-[11px] text-destructive">{error}</p>}
  </Field>
}

function SecretInput({ id, label, value, onChange, placeholder, required, error, hint }: {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
  required?: boolean
  error?: string
  hint?: string
}) {
  return <Field>
    <FieldLabel htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</FieldLabel>
    <div className="relative">
      <LockKeyIcon aria-hidden="true" className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
      <Input id={id} className={`${fieldClassName} pl-8`} type="password" autoComplete="new-password" value={value} onChange={event => onChange(event.target.value)} placeholder={placeholder} required={required} aria-invalid={error ? true : undefined} />
    </div>
    {hint && !error && <p className="text-[11px] text-muted-foreground">{hint}</p>}
    {error && <p className="text-[11px] text-destructive">{error}</p>}
  </Field>
}

function TextInput({ id, label, value, onChange, placeholder, required, type = 'text', error, hint }: {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
  required?: boolean
  type?: string
  error?: string
  hint?: string
}) {
  return <Field>
    <FieldLabel htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</FieldLabel>
    <Input id={id} className={fieldClassName} type={type} value={value} onChange={event => onChange(event.target.value)} placeholder={placeholder} required={required} aria-invalid={error ? true : undefined} />
    {hint && !error && <p className="text-[11px] text-muted-foreground">{hint}</p>}
    {error && <p className="text-[11px] text-destructive">{error}</p>}
  </Field>
}

export function RouterCredentialFields({ transport, values, configured = false, metadata, errors = {}, onChange }: RouterCredentialFieldsProps) {
  const connectionMetadata = metadata?.connection_metadata || {}
  const value = (name: string, fallback = '') => values[name] ?? (typeof connectionMetadata[name] === 'string' || typeof connectionMetadata[name] === 'number' ? String(connectionMetadata[name]) : fallback)
  const update = (name: string) => (next: string) => onChange(name, next)

  return <section className="sm:col-span-2 rounded-md border border-border/70 bg-muted/15 p-4" aria-label="Transport credentials">
    <div className="mb-4 flex items-start gap-3">
      <div className="grid size-8 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><KeyIcon size={16} /></div>
      <div><h3 className="text-sm font-semibold">Transport credentials</h3><p className="mt-1 text-xs text-muted-foreground">Only safe connection metadata is shown for configured routers. Leave secret fields blank to keep existing credentials.</p></div>
      {configured && <span className="ml-auto rounded-sm border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-mono text-[10px] text-emerald-700 dark:text-emerald-300">Configured</span>}
    </div>
    {transport === 'ssh' && <div className="grid gap-4 sm:grid-cols-2">
      <TextInput id="credential-username" label="Username" value={value('username')} onChange={update('username')} required error={errors.username} />
      <SecretInput id="credential-password" label="Password" value={values.password || ''} onChange={update('password')} placeholder={configured ? 'Leave blank to keep current' : undefined} required={!configured} error={errors.password} />
      <SecretInput id="credential-private-key" label="Private key (optional)" value={values.private_key || ''} onChange={update('private_key')} placeholder={configured ? 'Leave blank to keep current' : 'PEM key, if used'} hint="Use password authentication or a private key." error={errors.private_key} />
      <SecretInput id="credential-private-key-passphrase" label="Private-key passphrase (optional)" value={values.private_key_passphrase || ''} onChange={update('private_key_passphrase')} placeholder={configured ? 'Leave blank to keep current' : undefined} error={errors.private_key_passphrase} />
      <TextInput id="credential-port" label="SSH port" type="number" value={value('port', '22')} onChange={update('port')} required error={errors.port} />
    </div>}
    {transport === 'netconf' && <div className="grid gap-4 sm:grid-cols-2">
      <TextInput id="credential-username" label="Username" value={value('username')} onChange={update('username')} required error={errors.username} />
      <SecretInput id="credential-password" label="Password" value={values.password || ''} onChange={update('password')} placeholder={configured ? 'Leave blank to keep current' : undefined} required={!configured} error={errors.password} />
      <TextInput id="credential-port" label="NETCONF port" type="number" value={value('port', '830')} onChange={update('port')} required error={errors.port} />
    </div>}
    {transport === 'api' && <div className="grid gap-4 sm:grid-cols-2">
      <TextInput id="credential-api-base-url" label="API base URL" value={value('api_base_url')} onChange={update('api_base_url')} placeholder="https://router.example/api" error={errors.api_base_url} />
      <NativeSelect id="credential-auth-mode" label="Authentication mode" value={value('auth_mode', 'bearer')} onChange={update('auth_mode')} options={[{ value: 'bearer', label: 'Bearer token' }, { value: 'basic', label: 'Basic authentication' }]} error={errors.auth_mode} />
      <SecretInput id="credential-api-token" label="API token" value={values.api_token || ''} onChange={update('api_token')} placeholder={configured ? 'Leave blank to keep current' : undefined} error={errors.api_token} />
      <TextInput id="credential-port" label="API port (optional)" type="number" value={value('port')} onChange={update('port')} error={errors.port} />
    </div>}
    {transport === 'snmp' && <div className="grid gap-4 sm:grid-cols-2">
      <NativeSelect id="credential-snmp-version" label="SNMP version" value={value('snmp_version', '2c')} onChange={update('snmp_version')} options={[{ value: '2c', label: 'SNMP v2c' }, { value: '3', label: 'SNMP v3' }]} error={errors.snmp_version} />
      <SecretInput id="credential-snmp-community" label="Community" value={values.snmp_community || ''} onChange={update('snmp_community')} placeholder={configured ? 'Leave blank to keep current' : undefined} error={errors.snmp_community} />
      <TextInput id="credential-port" label="SNMP port" type="number" value={value('port', '161')} onChange={update('port')} required error={errors.port} />
    </div>}
  </section>
}
