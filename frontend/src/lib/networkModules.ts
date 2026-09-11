export const networkModules = [
  'BNG',
  'ACS Server',
  'Routers',
  'CGNAT',
  'VLAN',
  'RADIUS',
  'OLT',
  'ONT',
] as const

export type NetworkModule = typeof networkModules[number]
