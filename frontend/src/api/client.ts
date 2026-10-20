/**
 * Minimal fetch wrapper for the Laravel API: JSON only, timeout, cancellation and typed
 * errors. Requests are same-origin and only carry the browser's own cookies; no tokens or
 * secrets are ever added. State-changing requests (apiSend) also send the CSRF token that
 * Laravel puts in the XSRF-TOKEN cookie.
 */

export type ApiErrorKind =
  | 'network' // no response (offline, DNS, refused connection…)
  | 'timeout' // no response within the time limit
  | 'aborted' // cancelled by the caller (e.g. component unmounted)
  | 'unauthenticated' // 401: no valid session
  | 'forbidden' // 403: signed in, but not allowed
  | 'not_found' // 404
  | 'csrf' // 419: CSRF token or session expired
  | 'validation' // 422 with field errors
  | 'rate_limited' // 429
  | 'http' // any other non-2xx status
  | 'invalid_response' // 2xx but not the expected JSON envelope

export class ApiError extends Error {
  readonly kind: ApiErrorKind
  readonly status: number | null
  readonly fieldErrors: Record<string, string[]>
  /** Seconds from the Retry-After header of a 429, when present. */
  readonly retryAfter: number | null

  constructor(
    kind: ApiErrorKind,
    message: string,
    options: { status?: number | null; fieldErrors?: Record<string, string[]>; retryAfter?: number | null; cause?: unknown } = {},
  ) {
    super(message, { cause: options.cause })
    this.name = 'ApiError'
    this.kind = kind
    this.status = options.status ?? null
    this.fieldErrors = options.fieldErrors ?? {}
    this.retryAfter = options.retryAfter ?? null
  }
}

export const DEFAULT_TIMEOUT_MS = 10_000

export type QueryValue = string | number | boolean | null | undefined

export interface RequestOptions {
  signal?: AbortSignal
  timeoutMs?: number
}

/** Base URL of the API (VITE_API_BASE_URL, default "/api"), without a trailing slash. */
export function apiBaseUrl(): string {
  const configured = import.meta.env.VITE_API_BASE_URL?.trim()
  return (configured ? configured : '/api').replace(/\/+$/, '')
}

export function buildUrl(path: string, query: Record<string, QueryValue> = {}): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue
    params.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  const search = params.toString()
  return `${apiBaseUrl()}${normalizedPath}${search ? `?${search}` : ''}`
}

/** Value of a cookie readable by scripts (e.g. XSRF-TOKEN), URL-decoded; null when absent. */
export function readCookie(name: string): string | null {
  const prefix = `${name}=`
  const entry = document.cookie.split('; ').find((part) => part.startsWith(prefix))
  if (entry === undefined) return null
  try {
    return decodeURIComponent(entry.slice(prefix.length))
  } catch {
    return null
  }
}

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value)

function retryAfterSeconds(response: Response): number | null {
  const value = Number(response.headers.get('Retry-After'))
  return Number.isFinite(value) && value > 0 ? Math.ceil(value) : null
}

function errorFromResponse(response: Response, body: unknown): ApiError {
  const status = response.status
  const message = isObject(body) && typeof body.message === 'string' ? body.message : `Request failed with status ${status}.`

  if (status === 401) return new ApiError('unauthenticated', 'You are not signed in.', { status })
  if (status === 403) return new ApiError('forbidden', 'You are not allowed to do this.', { status })
  if (status === 404) return new ApiError('not_found', 'The requested resource was not found.', { status })
  if (status === 419) return new ApiError('csrf', 'The session or security token has expired.', { status })
  if (status === 429) {
    return new ApiError('rate_limited', 'Too many requests. Please try again shortly.', { status, retryAfter: retryAfterSeconds(response) })
  }
  if (status === 422) {
    const fieldErrors = isObject(body) && isObject(body.errors) ? (body.errors as Record<string, string[]>) : {}
    return new ApiError('validation', message, { status, fieldErrors })
  }
  return new ApiError('http', `The server returned an error (${status}).`, { status })
}

export type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export interface ApiRequest extends RequestOptions {
  query?: Record<string, QueryValue>
  body?: unknown
  /** 2xx responses must be a JSON object with `data` (204 allowed when false). */
  requireData: boolean
}

/** Low-level request used by apiGet/apiSend. Resolves with the parsed JSON body, or null for 204. */
export async function apiRequest(
  method: Method,
  path: string,
  { query, body, signal, timeoutMs = DEFAULT_TIMEOUT_MS, requireData }: ApiRequest,
): Promise<Record<string, unknown> | null> {
  if (signal?.aborted) throw new ApiError('aborted', 'The request was cancelled.')

  const controller = new AbortController()
  let timedOut = false
  const cancel = () => controller.abort()
  signal?.addEventListener('abort', cancel, { once: true })
  const timer = setTimeout(() => {
    timedOut = true
    controller.abort()
  }, timeoutMs)

  const failure = (cause: unknown): ApiError =>
    timedOut
      ? new ApiError('timeout', 'The server took too long to respond.', { cause })
      : signal?.aborted
        ? new ApiError('aborted', 'The request was cancelled.', { cause })
        : new ApiError('network', 'Could not reach the server. Check your connection.', { cause })

  const headers = new Headers({ Accept: 'application/json' })
  if (method !== 'GET') {
    // Laravel accepts the encrypted XSRF-TOKEN cookie value back in this header.
    const xsrf = readCookie('XSRF-TOKEN')
    if (xsrf) headers.set('X-XSRF-TOKEN', xsrf)
    if (body !== undefined) headers.set('Content-Type', 'application/json')
  }

  try {
    let response: Response
    try {
      response = await fetch(buildUrl(path, query), {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        credentials: 'same-origin',
        signal: controller.signal,
      })
    } catch (cause) {
      throw failure(cause)
    }

    if (response.status === 204) {
      if (requireData) throw new ApiError('invalid_response', 'The server returned an unexpected response.', { status: 204 })
      return null
    }

    let parsed: unknown = null
    let isJson = true
    try {
      parsed = await response.json()
    } catch (cause) {
      if (controller.signal.aborted) throw failure(cause)
      isJson = false
    }

    if (!response.ok) throw errorFromResponse(response, parsed)
    if (!isJson || !isObject(parsed) || (requireData && !('data' in parsed))) {
      throw new ApiError('invalid_response', 'The server returned an unexpected response.', { status: response.status })
    }
    return parsed
  } finally {
    clearTimeout(timer)
    signal?.removeEventListener('abort', cancel)
  }
}

/**
 * GET a JSON resource. Resolves with the parsed body (which must contain `data`),
 * rejects with an {@link ApiError}.
 */
export async function apiGet<T>(path: string, query: Record<string, QueryValue> = {}, options: RequestOptions = {}): Promise<T> {
  return (await apiRequest('GET', path, { ...options, query, requireData: true })) as T
}

/**
 * State-changing request (POST/PUT/PATCH/DELETE) with an optional JSON body and the CSRF
 * header. Resolves with the parsed JSON body, or null for 204 No Content.
 */
export async function apiSend<T = unknown>(
  method: Exclude<Method, 'GET'>,
  path: string,
  body?: unknown,
  options: RequestOptions = {},
): Promise<T | null> {
  return (await apiRequest(method, path, { ...options, body, requireData: false })) as T | null
}
