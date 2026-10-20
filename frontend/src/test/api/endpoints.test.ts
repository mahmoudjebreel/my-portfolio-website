import { describe, expect, it, vi } from 'vitest'
import { fetchCategories, fetchProject, fetchProjects, fetchSiteSettings, fetchTechnologies } from '../../api/endpoints'

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })

function stubFetch(body: unknown) {
  const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(async () => json(body))
  vi.stubGlobal('fetch', fetchMock)
  return () => fetchMock.mock.calls.map(([url]) => url)
}

const paginated = {
  data: [{ slug: 'demo', title: 'Demo', short_description: 'Short', is_featured: true, cover: null, categories: [], technologies: [], links: { github: null, live: null }, published_at: '2026-10-20T10:00:00Z' }],
  links: { first: '/api/projects?page=1', last: '/api/projects?page=1', prev: null, next: null },
  meta: { current_page: 1, from: 1, last_page: 1, links: [], path: '/api/projects', per_page: 9, to: 1, total: 1 },
}

describe('endpoints', () => {
  it('unwraps single resources and lists from the data envelope', async () => {
    let urls = stubFetch({ data: { locale: 'ar', hero_headline: null } })
    await expect(fetchSiteSettings('ar')).resolves.toEqual({ locale: 'ar', hero_headline: null })
    expect(urls()).toEqual(['/api/site-settings?locale=ar'])

    urls = stubFetch({ data: [{ slug: 'laravel', name: 'Laravel', group: 'backend', icon: null, description: null }] })
    await expect(fetchTechnologies('en')).resolves.toHaveLength(1)
    expect(urls()).toEqual(['/api/technologies?locale=en'])

    urls = stubFetch({ data: [] })
    await expect(fetchCategories('ar')).resolves.toEqual([])
    expect(urls()).toEqual(['/api/categories?locale=ar'])
  })

  it('maps project list params to the API query and keeps links + meta', async () => {
    const urls = stubFetch(paginated)

    await expect(fetchProjects('en', { page: 2, perPage: 6, category: 'web-applications', technology: 'laravel', featured: true })).resolves.toEqual(
      paginated,
    )
    await fetchProjects('ar', { featured: false })

    expect(urls()).toEqual([
      '/api/projects?locale=en&page=2&per_page=6&category=web-applications&technology=laravel&featured=1',
      '/api/projects?locale=ar',
    ])
  })

  it('encodes the project slug in the path', async () => {
    const urls = stubFetch({ data: { slug: 'demo' } })
    await fetchProject('en', 'demo/../../x')
    expect(urls()).toEqual(['/api/projects/demo%2F..%2F..%2Fx?locale=en'])
  })
})
