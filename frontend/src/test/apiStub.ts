/**
 * TEST FIXTURES ONLY — a fake `fetch` that answers like the Laravel public API.
 * Shapes and rules follow src/api/types.ts and the backend (ProjectController,
 * ProjectIndexRequest): locale-specific category slugs, 422 for unknown filters,
 * length-aware pagination, one generic 404 per unknown project slug.
 */
import { vi } from 'vitest'
import type { Locale } from '../i18n/config'
import type { Category, Paginated, ProjectDetail, ProjectSummary, SiteSettings, Technology } from '../api/types'

type PerLocale<T> = T | ((locale: Locale) => T)

export interface ApiStub {
  settings?: PerLocale<Partial<SiteSettings>> | 'error'
  technologies?: PerLocale<Technology[]> | 'error'
  categories?: PerLocale<Category[]> | 'error'
  projects?: PerLocale<ProjectSummary[]> | 'error'
  /** Project pages, looked up by slug in the requested locale. */
  details?: PerLocale<ProjectDetail[]> | 'error'
  /** Answer to POST /api/contact-messages (default: stored). Field errors answer with 422. */
  contact?: 'ok' | 'error' | 'rate_limited' | Record<string, string>
  /** Never answer (to observe loading states). */
  pending?: boolean
}

export const emptySettings = (locale: Locale): SiteSettings => ({
  locale,
  display_name: null,
  professional_title: null,
  hero_eyebrow: null,
  hero_headline: null,
  hero_description: null,
  about_text: null,
  experience_text: null,
  footer_text: null,
  seo: { title: null, description: null, image_url: null },
  contact: { email: null, github_url: null, linkedin_url: null },
  profile_image: null,
})

export const project = (overrides: Partial<ProjectSummary> = {}): ProjectSummary => ({
  slug: 'fixture-project',
  title: 'Fixture Project',
  short_description: 'Fixture short description.',
  is_featured: true,
  cover: null,
  categories: [],
  technologies: [],
  links: { github: null, live: null },
  published_at: '2026-10-20T10:00:00Z',
  ...overrides,
})

export const projectDetail = (overrides: Partial<ProjectDetail> = {}): ProjectDetail => ({
  slug: 'fixture-project',
  locale: 'en',
  title: 'Fixture Project',
  short_description: 'Fixture short description.',
  description: 'Fixture long description.',
  problem: null,
  solution: null,
  is_featured: false,
  cover: null,
  images: [],
  categories: [],
  technologies: [],
  links: { github: null, live: null },
  published_at: '2026-10-20T10:00:00Z',
  alternates: { en: 'fixture-project', ar: null },
  seo: { title: 'Fixture Project', description: 'Fixture short description.', image_url: null },
  ...overrides,
})

/** `count` numbered projects: "Project 1" … */
export const manyProjects = (count: number, overrides: Partial<ProjectSummary> = {}): ProjectSummary[] =>
  Array.from({ length: count }, (_, i) => project({ slug: `project-${i + 1}`, title: `Project ${i + 1}`, ...overrides }))

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })

const resolve = <T>(value: PerLocale<T>, locale: Locale): T =>
  typeof value === 'function' ? (value as (l: Locale) => T)(locale) : value

const validationError = (field: string, message: string) =>
  json({ message, errors: { [field]: [message] } }, 422)

function paginate(all: ProjectSummary[], page: number, perPage: number): Paginated<ProjectSummary> {
  const lastPage = Math.max(1, Math.ceil(all.length / perPage))
  const data = all.slice((page - 1) * perPage, page * perPage)
  const from = data.length ? (page - 1) * perPage + 1 : null
  const pageUrl = (p: number) => `/api/projects?page=${p}`
  return {
    data,
    links: {
      first: pageUrl(1),
      last: pageUrl(lastPage),
      prev: page > 1 ? pageUrl(page - 1) : null,
      next: page < lastPage ? pageUrl(page + 1) : null,
    },
    meta: {
      current_page: page,
      from,
      last_page: lastPage,
      links: [],
      path: '/api/projects',
      per_page: perPage,
      to: from === null ? null : from + data.length - 1,
      total: all.length,
    },
  }
}

const isInteger = (value: string | null) => value === null || /^\d+$/.test(value)

export function stubApi(stub: ApiStub = {}) {
  const fetchMock = vi.fn<(input: string, init?: RequestInit) => Promise<Response>>(async (input, init) => {
    if (stub.pending) return new Promise<Response>(() => {})

    const url = new URL(input, 'http://localhost')
    const query = url.searchParams
    const locale = (query.get('locale') ?? 'en') as Locale
    const serverError = () => json({ message: 'Server Error' }, 500)
    const notFound = () => json({ message: 'Not Found' }, 404)

    if (url.pathname === '/api/contact-messages') {
      if (init?.method !== 'POST') return json({ message: 'Method not allowed.' }, 405)
      const contact = stub.contact ?? 'ok'
      if (contact === 'ok') return json({ data: { received: true } }, 201)
      if (contact === 'error') return serverError()
      if (contact === 'rate_limited') return json({ message: 'Too Many Attempts.' }, 429)
      const errors = Object.fromEntries(Object.entries(contact).map(([field, message]) => [field, [message]]))
      return json({ message: 'The given data was invalid.', errors }, 422)
    }

    if (url.pathname.startsWith('/api/projects/')) {
      if (stub.details === 'error') return serverError()
      const slug = decodeURIComponent(url.pathname.slice('/api/projects/'.length))
      const detail = resolve(stub.details ?? [], locale).find((item) => item.slug === slug)
      return detail ? json({ data: detail }) : notFound()
    }

    switch (url.pathname) {
      case '/api/site-settings':
        if (stub.settings === 'error') return serverError()
        return json({ data: { ...emptySettings(locale), ...resolve(stub.settings ?? {}, locale) } })
      case '/api/technologies':
        if (stub.technologies === 'error') return serverError()
        return json({ data: resolve(stub.technologies ?? [], locale) })
      case '/api/categories':
        if (stub.categories === 'error') return serverError()
        return json({ data: resolve(stub.categories ?? [], locale) })
      case '/api/projects': {
        if (stub.projects === 'error') return serverError()
        if (!isInteger(query.get('page'))) return validationError('page', 'The page field must be an integer.')
        if (!isInteger(query.get('per_page'))) return validationError('per_page', 'The per page field must be an integer.')

        const category = query.get('category')
        const technology = query.get('technology')
        const categories = stub.categories === 'error' ? [] : resolve(stub.categories ?? [], locale)
        const technologies = stub.technologies === 'error' ? [] : resolve(stub.technologies ?? [], locale)
        if (category !== null && !categories.some((item) => item.slug === category)) {
          return validationError('category', 'The selected category does not exist in the requested language.')
        }
        if (technology !== null && !technologies.some((item) => item.slug === technology)) {
          return validationError('technology', 'The selected technology does not exist.')
        }

        const all = resolve(stub.projects ?? [], locale)
          .filter((item) => category === null || item.categories.some((c) => c.slug === category))
          .filter((item) => technology === null || item.technologies.some((t) => t.slug === technology))
          .filter((item) => query.get('featured') !== '1' || item.is_featured)
        return json(paginate(all, Number(query.get('page') ?? 1), Number(query.get('per_page') ?? 9)))
      }
      default:
        return notFound()
    }
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

/** Request paths (with query) the app made, in order. */
export const requestedUrls = (fetchMock: ReturnType<typeof stubApi>) => fetchMock.mock.calls.map(([url]) => url)
