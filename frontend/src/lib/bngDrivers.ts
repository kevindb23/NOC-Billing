export type BngServiceTab = 'pppoe' | 'radius' | 'cgnat' | 'accel_ppp' | 'bng_interfaces'

export type BngDriver = {
  key: 'linux' | 'mikrotik'
  label: string
  serviceTabs: Array<{ value: BngServiceTab; label: string; description: string }>
}

export const bngDrivers: Record<string, BngDriver> = {
  linux: {
    key: 'linux',
    label: 'Linux BNG driver',
    serviceTabs: [
      { value: 'pppoe', label: 'PPPoE', description: 'Configure subscriber authentication and PPPoE access services.' },
      { value: 'radius', label: 'RADIUS', description: 'Configure RADIUS authentication and accounting services.' },
      { value: 'cgnat', label: 'CGNAT', description: 'Configure carrier-grade NAT policies and address pools.' },
      { value: 'accel_ppp', label: 'Accel-PPP', description: 'Configure the Linux Accel-PPP access concentrator and subscriber services.' },
      { value: 'bng_interfaces', label: 'BNG Interfaces', description: 'Configure Linux BNG interfaces, uplinks, and subscriber-facing ports.' },
    ],
  },
  mikrotik: {
    key: 'mikrotik',
    label: 'MikroTik BNG driver',
    serviceTabs: [
      { value: 'pppoe', label: 'PPPoE', description: 'Configure MikroTik PPPoE access services using the MikroTik driver.' },
      { value: 'radius', label: 'RADIUS', description: 'Configure MikroTik RADIUS authentication and accounting using the MikroTik driver.' },
      { value: 'cgnat', label: 'CGNAT', description: 'Configure MikroTik carrier-grade NAT policies using the MikroTik driver.' },
    ],
  },
}
