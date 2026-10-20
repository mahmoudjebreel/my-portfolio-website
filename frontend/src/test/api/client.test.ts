import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiGet, apiSend, buildUrl, readCookie } from '../../api/client'

const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } })

/** A fetch that never answers, but rejects like the browser when its signal is aborted. */
const hangingFetch = () =>
  vi.fn(
    (_url: string, init?: RequestInit) =>
      new Promise<Response>((_resolve, reject) => {
        init?.signal?.addEventListener('abort', () => reject(new DOMException('The operation was aborted.', 'AbortError')))
      }),
  )

async function expectApiError(promise: Promise<unknown>): Promise<ApiError> {
  const error = await promise.then(
    () => {
      throw new Error('expected the request to fail')
    },
    (reason: unknown) => reason,
  )
  expect(error).toBeInstanceOf(ApiError)
  return error as ApiError
}

afterEach(() => {
  vi.useRealTimers()
})

describe('buildUrl', () => {
  it('uses /api by default and skips empty query values', () => {
    expect(buildUrl('/projects', { locale: 'en', page: 2, category: undefined, technology: null, empty: '' })).toBe(
      '/api/projects?locale=en&page=2',
    )
  })

  it('encodes booleans as 1/0 and accepts a configured base URL without trailing slash', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'https://example.test/api/')
    expect(buildUrl('projects', { featured: true, other: false })).toBe('https://example.test/api/projects?featured=1&other=0')
  })
})

describe('apiGet', () => {
  it('GETs JSON with an Accept header, no credentials beyond same-origin, and returns the parsed body', async () => {
    const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(async () => json({ data: [{ slug: 'laravel' }] }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(apiGet('/technologies', { locale: 'ar' })).resolves.toEqual({ data: [{ slug: 'laravel' }] })

    const [url, init] = fetchMock.mock.calls[0]!
    expect(url).toBe('/api/technologies?locale=ar')
    expect(init?.method).toBe('GET')
    expect(init?.credentials).toBe('same-origin')
    expect(new Headers(init?.headers).get('Accept')).toBe('application/json')
    expect(new Headers(init?.headers).has('Authorization')).toBe(false)
  })

  it('maps a failed connection to a network error', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new TypeError('Failed to fetch'))))
    expect((await expectApiError(apiGet('/site-settings'))).kind).toBe('network')
  })

  it('maps 404 to not_found', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'Resource not found.' }, 404)))
    const error = await expectApiError(apiGet('/projects/missing'))
    expect(error.kind).toBe('not_found')
    expect(error.status).toBe(404)
  })

  it('maps 422 to validation with the field errors', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => json({ message: 'The selected locale is invalid.', errors: { locale: ['The selected locale is invalid.'] } }, 422)),
    )
    const error = await expectApiError(apiGet('/projects', { locale: 'fr' }))
    expect(error.kind).toBe('validation')
    expect(error.fieldErrors).toEqual({ locale: ['The selected locale is invalid.'] })
  })

  it('maps 429 to rate_limited', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'Too Many Attempts.' }, 429)))
    expect((await expectApiError(apiGet('/projects'))).kind).toBe('rate_limited')
  })

  it('maps other statuses to http without exposing the server message', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'SQLSTATE[HY000] internal detail' }, 500)))
    const error = await expectApiError(apiGet('/projects'))
    expect(error.kind).toBe('http')
    expect(error.status).toBe(500)
    expect(error.message).not.toContain('SQLSTATE')
  })

  it('rejects 2xx responses that are not the expected JSON envelope', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('<html>proxy error</html>', { status: 200 })))
    expect((await expectApiError(apiGet('/projects'))).kind).toBe('invalid_response')

    vi.stubGlobal('fetch', vi.fn(async () => json({ items: [] })))
    expect((await expectApiError(apiGet('/projects'))).kind).toBe('invalid_response')
  })

  it('times out', async () => {
    vi.useFakeTimers()
    const fetchMock = hangingFetch()
    vi.stubGlobal('fetch', fetchMock)

    const request = expectApiError(apiGet('/projects', {}, { timeoutMs: 50 }))
    await vi.advanceTimersByTimeAsync(50)

    expect((await request).kind).toBe('timeout')
    expect(fetchMock.mock.calls[0]![1]?.signal?.aborted).toBe(true)
  })

  it('can be cancelled by the caller', async () => {
    vi.stubGlobal('fetch', hangingFetch())
    const controller = new AbortController()

    const request = expectApiError(apiGet('/projects', {}, { signal: controller.signal }))
    controller.abort()

    expect((await request).kind).toBe('aborted')
  })

  it('does not even start a request with an already cancelled signal', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    const controller = new AbortController()
    controller.abort()

    expect((await expectApiError(apiGet('/projects', {}, { signal: controller.signal }))).kind).toBe('aborted')
    expect(fetchMock).not.toHaveBeenCalled()
  })
})

describe('session-related statuses', () => {
  it('maps 401, 403 and 419 to their own kinds', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'Unauthenticated.' }, 401)))
    expect((await expectApiError(apiGet('/admin/me'))).kind).toBe('unauthenticated')

    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'Forbidden.' }, 403)))
    expect((await expectApiError(apiGet('/admin/me'))).kind).toBe('forbidden')

    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'CSRF token mismatch.' }, 419)))
    expect((await expectApiError(apiSend('POST', '/admin/logout'))).kind).toBe('csrf')
  })

  it('reads Retry-After from a 429', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ message: 'Too many.' }, 429, { 'Retry-After': '37' })))
    const error = await expectApiError(apiSend('POST', '/admin/login', {}))
    expect(error.kind).toBe('rate_limited')
    expect(error.retryAfter).toBe(37)
  })

  it('still rejects a 204 where data is expected', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(null, { status: 204 })))
    expect((await expectApiError(apiGet('/projects'))).kind).toBe('invalid_response')
  })
})

describe('apiSend', () => {
  afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; path=/; max-age=0'
  })

  it('sends JSON with the decoded XSRF-TOKEN cookie as X-XSRF-TOKEN, same-origin only', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('eyJpdiI6Ij==/+x')}; path=/`
    const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(async () => json({ data: { id: 1 } }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(apiSend('POST', '/admin/login', { email: 'a@example.com', password: 'secret' })).resolves.toEqual({ data: { id: 1 } })

    const [url, init] = fetchMock.mock.calls[0]!
    const headers = new Headers(init?.headers)
    expect(url).toBe('/api/admin/login')
    expect(init?.method).toBe('POST')
    expect(init?.credentials).toBe('same-origin')
    expect(init?.body).toBe(JSON.stringify({ email: 'a@example.com', password: 'secret' }))
    expect(headers.get('X-XSRF-TOKEN')).toBe('eyJpdiI6Ij==/+x')
    expect(headers.get('Content-Type')).toBe('application/json')
    expect(headers.get('Accept')).toBe('application/json')
    expect(headers.has('Authorization')).toBe(false)
  })

  it('resolves 204 No Content with null and sends no body or Content-Type without one', async () => {
    const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(async () => new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(apiSend('POST', '/admin/logout')).resolves.toBeNull()
    const init = fetchMock.mock.calls[0]![1]
    expect(init?.body).toBeUndefined()
    expect(new Headers(init?.headers).has('Content-Type')).toBe(false)
    expect(new Headers(init?.headers).has('X-XSRF-TOKEN')).toBe(false) // no cookie, no header
  })

  it('never sends the CSRF header with GET requests', async () => {
    document.cookie = 'XSRF-TOKEN=abc; path=/'
    const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(async () => json({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await apiGet('/projects')
    expect(new Headers(fetchMock.mock.calls[0]![1]?.headers).has('X-XSRF-TOKEN')).toBe(false)
  })
})

describe('readCookie', () => {
  it('returns the decoded value or null', () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('a=b/c')}; path=/`
    expect(readCookie('XSRF-TOKEN')).toBe('a=b/c')
    expect(readCookie('missing')).toBeNull()
    document.cookie = 'XSRF-TOKEN=; path=/; max-age=0'
  })
})
