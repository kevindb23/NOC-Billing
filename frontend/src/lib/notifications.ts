import { toast } from 'sonner'

export function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message) return error.message
  if (typeof error === 'string' && error.trim()) return error
  return fallback
}

export const notify = {
  success: (message: string) => toast.success(message),
  error: (message: string) => toast.error(message),
  info: (message: string) => toast.info(message),
  warning: (message: string) => toast.warning(message),
  loading: (message: string) => toast.loading(message),
  promise: async <T>(promise: Promise<T>, messages: { loading: string; success: string; error: string }): Promise<T> => {
    const result = toast.promise(promise, messages)
    if (typeof result === 'object' && result !== null && 'unwrap' in result) return result.unwrap() as Promise<T>
    return promise
  },
}
