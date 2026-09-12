import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getErrorMessage, notify } from './notifications'

const { toastMock } = vi.hoisted(() => ({ toastMock: {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  loading: vi.fn(),
  promise: vi.fn((promise: Promise<unknown>) => promise),
} }))

vi.mock('sonner', () => ({ toast: toastMock }))

describe('notifications', () => {
  beforeEach(() => vi.clearAllMocks())

  it('normalizes common error values into a user-facing message', () => {
    expect(getErrorMessage(new Error('Request failed'), 'Fallback')).toBe('Request failed')
    expect(getErrorMessage('Network unavailable', 'Fallback')).toBe('Network unavailable')
    expect(getErrorMessage({ reason: 'unknown' }, 'Fallback')).toBe('Fallback')
  })

  it('delegates standard notifications to Sonner', () => {
    notify.success('Saved')
    notify.error('Failed')
    notify.info('Heads up')
    notify.warning('Check this')
    notify.loading('Saving')

    expect(toastMock.success).toHaveBeenCalledWith('Saved')
    expect(toastMock.error).toHaveBeenCalledWith('Failed')
    expect(toastMock.info).toHaveBeenCalledWith('Heads up')
    expect(toastMock.warning).toHaveBeenCalledWith('Check this')
    expect(toastMock.loading).toHaveBeenCalledWith('Saving')
  })

  it('delegates promise notifications to Sonner', async () => {
    const result = await notify.promise(Promise.resolve('done'), { loading: 'Saving', success: 'Saved', error: 'Failed' })

    expect(result).toBe('done')
    expect(toastMock.promise).toHaveBeenCalledWith(expect.any(Promise), { loading: 'Saving', success: 'Saved', error: 'Failed' })
  })
})
