import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it } from 'vitest'
import type { Category, Technology } from '../api/types'
import { clearApiQueryCache } from '../data/useApiQuery'
import { manyProjects, project, requestedUrls, stubApi, type ApiStub } from './apiStub'
import { renderRoute } from './renderRoute'

const technologies: Technology[] = [
  { slug: 'react', name: 'React', group: 'frontend', icon: null, description: null },
  { slug: 'laravel', name: 'Laravel', group: 'backend', icon: null, description: null },
]

const categories = (locale: 'en' | 'ar'): Category[] =>
  locale === 'ar'
    ? [
        { slug: 'tatbiqat-web', name: 'تطبيقات الويب', projects_count: 1 },
        { slug: 'adawat', name: 'أدوات', projects_count: 0 },
      ]
    : [
        { slug: 'web-apps', name: 'Web Apps', projects_count: 1 },
        { slug: 'tools', name: 'Tools', projects_count: 0 },
      ]

const webApp = project({
  slug: 'shop',
  title: 'Shop',
  categories: [{ slug: 'web-apps', name: 'Web Apps' }],
  technologies: [{ slug: 'react', name: 'React' }],
  links: { github: 'https://github.com/example/shop', live: null },
})
const apiTool = project({ slug: 'api-tool', title: 'API Tool', technologies: [{ slug: 'laravel', name: 'Laravel' }] })

const api = (overrides: ApiStub = {}) => stubApi({ technologies, categories, projects: [webApp, apiTool], ...overrides })

const projectRequests = (fetchMock: ReturnType<typeof stubApi>) =>
  requestedUrls(fetchMock).filter((url) => url.startsWith('/api/projects'))

const results = () => screen.getByRole('heading', { level: 1 }).closest('section')!

beforeEach(() => clearApiQueryCache())

describe('Projects page — list', () => {
  it('lists published projects from the API with links to their pages', async () => {
    const fetchMock = api()
    renderRoute('/en/projects')

    expect(await screen.findByRole('heading', { level: 1, name: 'All projects' })).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'Shop' })).toHaveAttribute('href', '/en/projects/shop')
    expect(screen.getByRole('link', { name: 'API Tool' })).toHaveAttribute('href', '/en/projects/api-tool')
    expect(screen.getByRole('link', { name: 'GitHub: Shop' })).toHaveAttribute('href', 'https://github.com/example/shop')
    expect(screen.getByText('Showing 1–2 of 2 projects')).toBeInTheDocument()
    // A single page needs no pagination.
    expect(screen.queryByRole('navigation', { name: 'Pagination' })).toBeNull()

    expect(projectRequests(fetchMock)).toEqual(['/api/projects?locale=en'])
    expect(requestedUrls(fetchMock)).toContain('/api/categories?locale=en')
    expect(requestedUrls(fetchMock)).toContain('/api/technologies?locale=en')
    await waitFor(() => expect(document.title).toBe('All projects · Software Developer Portfolio'))
  })

  it('shows loading placeholders while the API has not answered', async () => {
    stubApi({ pending: true })
    renderRoute('/en/projects')
    expect(await screen.findByTestId('projects-page-loading')).toBeInTheDocument()
    expect(screen.getByTestId('categories-loading')).toBeInTheDocument()
  })

  it('shows an error with retry and recovers', async () => {
    const fetchMock = api({ projects: 'error' })
    renderRoute('/en/projects')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Something went wrong while loading this section.')

    fetchMock.mockClear()
    api()
    await userEvent.click(within(alert).getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('link', { name: 'Shop' })).toBeInTheDocument()
  })

  it('keeps the list usable when the filters cannot be loaded', async () => {
    api({ categories: 'error', technologies: 'error' })
    renderRoute('/en/projects')

    expect(await screen.findByRole('link', { name: 'Shop' })).toBeInTheDocument()
    await waitFor(() => expect(screen.getAllByRole('alert')).toHaveLength(2))
  })

  it('says so honestly when nothing is published', async () => {
    stubApi({ technologies, categories })
    renderRoute('/en/projects')
    expect(await screen.findByText('No projects have been published yet.')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Clear filters' })).toBeNull()
  })
})

describe('Projects page — filters', () => {
  it('sends the filters from the URL to the API', async () => {
    const fetchMock = api({ projects: manyProjects(12, { categories: [{ slug: 'web-apps', name: 'Web Apps' }], technologies: [{ slug: 'react', name: 'React' }] }) })
    renderRoute('/en/projects?category=web-apps&technology=react&page=2')

    expect(await screen.findByRole('link', { name: 'Project 10' })).toBeInTheDocument()
    expect(projectRequests(fetchMock)).toEqual(['/api/projects?locale=en&page=2&category=web-apps&technology=react'])
    expect(screen.getByRole('link', { name: /Web Apps/ })).toHaveAttribute('aria-current', 'true')
    expect(screen.getByRole('combobox', { name: 'Technology' })).toHaveValue('react')
  })

  it('shows only categories with projects, and a category click resets the page', async () => {
    api({ projects: manyProjects(12, { technologies: [{ slug: 'react', name: 'React' }] }) })
    const { router } = renderRoute('/en/projects?technology=react&page=2')

    const chip = await screen.findByRole('link', { name: /Web Apps/ })
    expect(screen.queryByRole('link', { name: /Tools/ })).toBeNull() // projects_count = 0
    expect(screen.getByRole('link', { name: 'All' })).toHaveAttribute('aria-current', 'true')

    await userEvent.click(chip)
    expect(router.state.location.search).toBe('?category=web-apps&technology=react')

    // Clicking the active category removes it.
    await userEvent.click(await screen.findByRole('link', { name: /Web Apps/ }))
    expect(router.state.location.search).toBe('?technology=react')
  })

  it('filters by technology with the selector and clears all filters', async () => {
    const user = userEvent.setup()
    const fetchMock = api()
    const { router } = renderRoute('/en/projects?category=web-apps')

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Technology' }), 'laravel')
    expect(router.state.location.search).toBe('?category=web-apps&technology=laravel')
    expect(await screen.findByText('No projects match these filters.')).toBeInTheDocument()
    expect(projectRequests(fetchMock)).toContain('/api/projects?locale=en&category=web-apps&technology=laravel')

    await user.click(screen.getAllByRole('link', { name: 'Clear filters' })[0]!)
    expect(router.state.location.search).toBe('')
    expect(await screen.findByRole('link', { name: 'Shop' })).toBeInTheDocument()
  })

  it('groups technologies in the selector', async () => {
    api()
    renderRoute('/en/projects')
    const select = await screen.findByRole('combobox', { name: 'Technology' })
    expect(within(select).getByRole('group', { name: 'Front-End' })).toContainElement(within(select).getByRole('option', { name: 'React' }))
    expect(within(select).queryByRole('group', { name: 'Database' })).toBeNull()
  })

  it('cleans invalid query values from the URL instead of sending them', async () => {
    const fetchMock = api()
    const { router } = renderRoute('/en/projects?page=abc&category=Bad_Slug&technology=react&utm=x')

    await waitFor(() => expect(router.state.location.search).toBe('?technology=react'))
    await screen.findByRole('link', { name: 'Shop' })
    for (const url of projectRequests(fetchMock)) expect(url).toBe('/api/projects?locale=en&technology=react')
  })

  it('explains a filter the API does not know (422) instead of offering a useless retry', async () => {
    api()
    const { router } = renderRoute('/en/projects?category=unknown-category')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The selected filter is not available.')
    expect(within(alert).queryByRole('button', { name: 'Retry' })).toBeNull()

    await userEvent.click(within(alert).getByRole('link', { name: 'Clear filters' }))
    expect(router.state.location.search).toBe('')
    expect(await screen.findByRole('link', { name: 'Shop' })).toBeInTheDocument()
  })

  it('explains an unknown filter in Arabic and clears it', async () => {
    api()
    const { router } = renderRoute('/ar/projects?technology=unknown-tech')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('خيار التصفية المحدد غير متاح.')
    await userEvent.click(within(alert).getByRole('link', { name: 'إلغاء التصفية' }))
    expect(router.state.location.search).toBe('')
    expect(await screen.findByRole('link', { name: 'Shop' })).toBeInTheDocument()
  })
})

describe('Projects page — pagination', () => {
  it('paginates with page links that keep the filters', async () => {
    const fetchMock = api({ projects: manyProjects(20, { technologies: [{ slug: 'react', name: 'React' }] }) })
    const { router } = renderRoute('/en/projects?technology=react')

    const nav = await screen.findByRole('navigation', { name: 'Pagination' })
    expect(screen.getByText('Showing 1–9 of 20 projects')).toBeInTheDocument()
    expect(within(nav).getByRole('link', { name: 'Page 1' })).toHaveAttribute('aria-current', 'page')
    expect(within(nav).getByRole('link', { name: 'Page 3' })).toHaveAttribute('href', '/en/projects?technology=react&page=3')
    expect(within(nav).queryByRole('link', { name: /Previous/ })).toBeNull() // disabled on page 1

    await userEvent.click(within(nav).getByRole('link', { name: /Next/ }))
    expect(router.state.location.search).toBe('?technology=react&page=2')
    expect(await screen.findByText('Showing 10–18 of 20 projects')).toBeInTheDocument()
    expect(projectRequests(fetchMock)).toContain('/api/projects?locale=en&page=2&technology=react')
    // Focus moves to the results after a page change.
    expect(screen.getByRole('heading', { level: 2, name: 'Projects' })).toHaveFocus()

    const pageNav = screen.getByRole('navigation', { name: 'Pagination' })
    expect(within(pageNav).getByRole('link', { name: /Previous/ })).toHaveAttribute('href', '/en/projects?technology=react')
  })

  it('handles a page beyond the last one', async () => {
    api()
    renderRoute('/en/projects?page=9')

    expect(await screen.findByText('This page does not exist.')).toBeInTheDocument()
    expect(within(results()).getByRole('link', { name: 'Go to the first page' })).toHaveAttribute('href', '/en/projects')
  })
})

describe('Projects page — languages', () => {
  it('renders in Arabic, right-to-left, with Arabic requests', async () => {
    const fetchMock = api()
    renderRoute('/ar/projects')

    expect(await screen.findByRole('heading', { level: 1, name: 'كل المشاريع' })).toBeInTheDocument()
    await waitFor(() => expect(document.documentElement).toHaveAttribute('dir', 'rtl'))
    expect(document.documentElement).toHaveAttribute('lang', 'ar')
    expect(await screen.findByRole('link', { name: /تطبيقات الويب/ })).toHaveAttribute('href', '/ar/projects?category=tatbiqat-web')
    expect(await screen.findByText('عرض 1 إلى 2 من أصل 2')).toBeInTheDocument()
    expect(projectRequests(fetchMock)).toEqual(['/api/projects?locale=ar'])
  })

  it('the language switcher keeps only the language-neutral technology filter', async () => {
    api()
    renderRoute('/en/projects?category=web-apps&technology=react')

    const switcher = await screen.findByRole('link', { name: /Switch language to العربية/ })
    await waitFor(() => expect(switcher).toHaveAttribute('href', '/ar/projects?technology=react'))
  })
})

describe('Project links from the list', () => {
  it('opens the project page from a card', async () => {
    api({
      details: [
        {
          ...project({ slug: 'shop', title: 'Shop' }),
          locale: 'en',
          description: 'Shop description.',
          problem: null,
          solution: null,
          images: [],
          alternates: { en: 'shop', ar: null },
          seo: { title: 'Shop', description: 'Shop seo', image_url: null },
        },
      ],
    })
    const { router } = renderRoute('/en/projects')

    await userEvent.click(await screen.findByRole('link', { name: 'Shop' }))
    // The project page is a lazy route: the URL changes once its code has loaded.
    await waitFor(() => expect(router.state.location.pathname).toBe('/en/projects/shop'))
    expect(await screen.findByRole('heading', { level: 1, name: 'Shop' })).toBeInTheDocument()
  })

  it('header and logo links lead back to the home page sections', async () => {
    api()
    renderRoute('/en/projects')

    const nav = (await screen.findAllByRole('navigation', { name: 'Main navigation' }))[0]!
    expect(within(nav).getByRole('link', { name: 'Projects' })).toHaveAttribute('href', '/en#projects')
    expect(within(nav).getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/en#contact')
    expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/en')
    expect(screen.getByRole('link', { name: /Back to top/ })).toHaveAttribute('href', '#main')
  })
})
