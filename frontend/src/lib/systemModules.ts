export const systemModules = [
  'Users',
  'Roles',
  'Branding',
  'API Tokens',
  'Email',
  'Notifications',
  'Audit Logs',
] as const

export type SystemModule = typeof systemModules[number]
