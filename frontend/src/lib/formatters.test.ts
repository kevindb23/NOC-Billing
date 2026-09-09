import { describe, expect, it } from 'vitest'
import { formatMoney } from './formatters'

describe('formatMoney', () => {
  it('formats minor units as PHP currency', () => {
    expect(formatMoney(150000)).toBe('PHP 1,500.00')
  })
})
