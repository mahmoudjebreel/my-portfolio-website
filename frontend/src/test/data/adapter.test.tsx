import { render, renderHook, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import { DataSourceBadge } from '../../components/dev/DataSourceBadge'
import { apiAdapter, getDataAdapter } from '../../data/adapter'
import { mockAdapter } from '../../data/mockAdapter'
import { useProjects, useSiteSettings } from '../../data/queries'
import { clearApiQueryCache } from '../../data/useApiQuery'

beforeEach(() => clearApiQueryCache())

describe('data adapter selection', () => {
  it('uses the API by default', async () => {
    await expect(getDataAdapter()).resolves.toBe(apiAdapter)
  })

  it('uses the mock adapter only when VITE_DATA_SOURCE=mock in development', async () => {
    vi.stubEnv('VITE_DATA_SOURCE', 'mock')
    const adapter = await getDataAdapter()
    expect(adapter.source).toBe('mock')
  })
})

describe('no silent fallback to mock data', () => {
  it('surfaces the API error instead of serving sample data', async () => {
    const fetchMock = vi.fn(async () => Promise.reject(new TypeError('Failed to fetch')))
    vi.stubGlobal('fetch', fetchMock)

    const { result } = renderHook(() => useSiteSettings('en'))

    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.error?.kind).toBe('network')
    expect(result.current.data).toBeUndefined()
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('returns an empty API result as empty, not as sample projects', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () =>
        new Response(
          JSON.stringify({
            data: [],
            links: { first: null, last: null, prev: null, next: null },
            meta: { current_page: 1, from: null, last_page: 1, links: [], path: '/api/projects', per_page: 3, to: null, total: 0 },
          }),
          { status: 200 },
        ),
      ),
    )

    const { result } = renderHook(() => useProjects('en', { featured: true, perPage: 3 }))
    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.data?.data).toEqual([])
    expect(JSON.stringify(result.current.data)).not.toMatch(/Sample project/i)
  })
})

describe('mock adapter (development only)', () => {
  it('serves sample data in API shapes and 404s unknown slugs', async () => {
    const page = await mockAdapter.projects('en', { perPage: 2 })
    expect(page.data).toHaveLength(2)
    expect(page.meta).toMatchObject({ current_page: 1, per_page: 2, total: 3, last_page: 2 })
    expect(page.data.every((project) => project.links.github === null && project.links.live === null)).toBe(true)

    await expect(mockAdapter.project('en', 'does-not-exist')).rejects.toMatchObject({ kind: 'not_found' } satisfies Partial<ApiError>)
  })
})

describe('DataSourceBadge', () => {
  it('is hidden with the API data source', () => {
    const { container } = render(<DataSourceBadge />)
    expect(container).toBeEmptyDOMElement()
  })

  it('is clearly visible with mock data in development', () => {
    vi.stubEnv('VITE_DATA_SOURCE', 'mock')
    render(<DataSourceBadge />)
    expect(screen.getByTestId('mock-data-badge')).toHaveTextContent('Mock data · development only')
  })
})
