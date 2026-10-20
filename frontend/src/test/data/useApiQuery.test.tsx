import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import { clearApiQueryCache, useApiQuery } from '../../data/useApiQuery'

/** A controllable fetcher: resolve/reject each call from the test. */
function deferredFetcher<T>() {
  const calls: { signal: AbortSignal; resolve: (value: T) => void; reject: (error: unknown) => void }[] = []
  const fetcher = vi.fn(
    (signal: AbortSignal) =>
      new Promise<T>((resolve, reject) => {
        calls.push({ signal, resolve, reject })
      }),
  )
  return { fetcher, calls }
}

beforeEach(() => clearApiQueryCache())

describe('useApiQuery', () => {
  it('is idle and does not fetch without a key', () => {
    const fetcher = vi.fn()
    const { result } = renderHook(() => useApiQuery(null, fetcher))
    expect(result.current.status).toBe('idle')
    expect(fetcher).not.toHaveBeenCalled()
  })

  it('goes from loading to success', async () => {
    const { fetcher, calls } = deferredFetcher<string[]>()
    const { result } = renderHook(() => useApiQuery('tech:en', fetcher))

    expect(result.current.status).toBe('loading')
    await waitFor(() => expect(calls).toHaveLength(1))
    act(() => calls[0]!.resolve(['Laravel']))

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.data).toEqual(['Laravel'])
    expect(result.current.error).toBeUndefined()
  })

  it('exposes typed errors and retries on demand', async () => {
    const { fetcher, calls } = deferredFetcher<string>()
    const { result } = renderHook(() => useApiQuery('settings:en', fetcher))

    await waitFor(() => expect(calls).toHaveLength(1))
    act(() => calls[0]!.reject(new ApiError('network', 'offline')))
    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.error?.kind).toBe('network')
    expect(result.current.data).toBeUndefined()

    act(() => result.current.retry())
    expect(result.current.status).toBe('loading')
    await waitFor(() => expect(calls).toHaveLength(2))
    act(() => calls[1]!.resolve('ok'))
    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.data).toBe('ok')
  })

  it('wraps unexpected errors as invalid_response', async () => {
    const { fetcher, calls } = deferredFetcher<string>()
    const { result } = renderHook(() => useApiQuery('x', fetcher))
    await waitFor(() => expect(calls).toHaveLength(1))
    act(() => calls[0]!.reject(new Error('boom')))
    await waitFor(() => expect(result.current.error?.kind).toBe('invalid_response'))
  })

  it('cancels the request when the component unmounts', async () => {
    const { fetcher, calls } = deferredFetcher<string>()
    const { unmount } = renderHook(() => useApiQuery('categories:ar', fetcher))
    await waitFor(() => expect(calls).toHaveLength(1))

    unmount()
    expect(calls[0]!.signal.aborted).toBe(true)
  })

  it('cancels the previous request when the key changes', async () => {
    const { fetcher, calls } = deferredFetcher<string>()
    const { rerender } = renderHook(({ key }) => useApiQuery(key, fetcher), { initialProps: { key: 'projects:en' } })
    await waitFor(() => expect(calls).toHaveLength(1))

    rerender({ key: 'projects:ar' })
    await waitFor(() => expect(calls).toHaveLength(2))
    expect(calls[0]!.signal.aborted).toBe(true)
    expect(calls[1]!.signal.aborted).toBe(false)
  })

  it('serves successful results from the in-memory cache, but never caches errors', async () => {
    const first = deferredFetcher<string>()
    const a = renderHook(() => useApiQuery('cached', first.fetcher))
    await waitFor(() => expect(first.calls).toHaveLength(1))
    act(() => first.calls[0]!.resolve('value'))
    await waitFor(() => expect(a.result.current.status).toBe('success'))

    const second = vi.fn()
    const b = renderHook(() => useApiQuery('cached', second))
    expect(b.result.current.status).toBe('success')
    expect(b.result.current.data).toBe('value')
    expect(second).not.toHaveBeenCalled()

    const failing = deferredFetcher<string>()
    const c = renderHook(() => useApiQuery('failing', failing.fetcher))
    await waitFor(() => expect(failing.calls).toHaveLength(1))
    act(() => failing.calls[0]!.reject(new ApiError('http', 'down', { status: 503 })))
    await waitFor(() => expect(c.result.current.status).toBe('error'))
    c.unmount()

    const again = deferredFetcher<string>()
    renderHook(() => useApiQuery('failing', again.fetcher))
    await waitFor(() => expect(again.calls).toHaveLength(1))
  })
})
