import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '../api/client'

export type QueryStatus = 'idle' | 'loading' | 'success' | 'error'

export interface QueryResult<T> {
  status: QueryStatus
  data: T | undefined
  error: ApiError | undefined
  /** Drop the cached value for this key and fetch again. */
  retry: () => void
}

/** In-memory cache of successful results, per key. Lives for the page session only. */
const cache = new Map<string, unknown>()

export function clearApiQueryCache(): void {
  cache.clear()
}

const toApiError = (error: unknown): ApiError =>
  error instanceof ApiError ? error : new ApiError('invalid_response', 'Unexpected error while loading data.', { cause: error })

/**
 * Minimal data-fetching hook.
 *  - `key` identifies the request (include locale and params); `null` = do not fetch.
 *  - the request is cancelled when the key changes or the component unmounts.
 *  - successful results are cached by key; errors are never cached.
 */
export function useApiQuery<T>(key: string | null, fetcher: (signal: AbortSignal) => Promise<T>): QueryResult<T> {
  const fetcherRef = useRef(fetcher)
  const [attempt, setAttempt] = useState(0)
  const [settled, setSettled] = useState<{ requestId: string; data?: T; error?: ApiError } | null>(null)

  useEffect(() => {
    fetcherRef.current = fetcher
  })

  const requestId = key === null ? null : `${key}#${attempt}`
  const cached = key === null ? undefined : (cache.get(key) as T | undefined)

  useEffect(() => {
    if (key === null || requestId === null || cache.has(key)) return

    const controller = new AbortController()
    fetcherRef.current(controller.signal).then(
      (data) => {
        if (controller.signal.aborted) return
        cache.set(key, data)
        setSettled({ requestId, data })
      },
      (error: unknown) => {
        const apiError = toApiError(error)
        if (controller.signal.aborted || apiError.kind === 'aborted') return
        setSettled({ requestId, error: apiError })
      },
    )
    return () => controller.abort()
  }, [key, requestId])

  const retry = useCallback(() => {
    if (key !== null) cache.delete(key)
    setAttempt((value) => value + 1)
  }, [key])

  if (key === null) return { status: 'idle', data: undefined, error: undefined, retry }
  if (cached !== undefined) return { status: 'success', data: cached, error: undefined, retry }
  if (settled?.requestId === requestId) {
    return settled.error
      ? { status: 'error', data: undefined, error: settled.error, retry }
      : { status: 'success', data: settled.data, error: undefined, retry }
  }
  return { status: 'loading', data: undefined, error: undefined, retry }
}
