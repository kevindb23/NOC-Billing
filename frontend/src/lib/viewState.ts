export const views = ['overview', 'subscribers', 'subscriptions', 'accounts', 'billing-statements', 'plans', 'invoices', 'payments'] as const
export type View = typeof views[number]

export function readStoredView(): View {
  const stored = localStorage.getItem('isp-view')
  return views.includes(stored as View) ? stored as View : 'overview'
}
