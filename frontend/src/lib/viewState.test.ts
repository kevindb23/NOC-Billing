// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { readStoredView } from './viewState'

describe('readStoredView', () => {
  it('returns the stored page when it is a valid view', () => {
    localStorage.setItem('isp-view', 'subscribers')
    expect(readStoredView()).toBe('subscribers')
  })

  it('falls back to overview for missing or invalid values', () => {
    localStorage.setItem('isp-view', 'unknown-page')
    expect(readStoredView()).toBe('overview')
  })
})
