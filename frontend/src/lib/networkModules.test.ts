import { describe, expect, it } from 'vitest'
import { networkModules } from './networkModules'
import { systemModules } from './systemModules'

describe('network module navigation contract', () => {
  it('contains exactly the supported modules and excludes NAP', () => {
    expect(networkModules).toEqual([
      'BNG',
      'ACS Server',
      'Routers',
      'CGNAT',
      'VLAN',
      'RADIUS',
      'OLT',
      'ONT',
    ])
    expect(networkModules).not.toContain('NAP')
  })

  it('keeps system modules in their own navigation group', () => {
    expect(systemModules).toEqual(['Users', 'Roles', 'Branding', 'API Tokens', 'Email', 'Notifications', 'Audit Logs'])
  })
})
