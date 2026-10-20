import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { clearApiQueryCache } from '../data/useApiQuery'
import { projectDetail, requestedUrls, stubApi } from './apiStub'
import { renderRoute } from './renderRoute'

const full = projectDetail({
  slug: 'portfolio-site',
  title: 'Portfolio Site',
  short_description: 'A bilingual portfolio.',
  description: 'First paragraph.\n\nSecond paragraph\nwith a line break.',
  problem: 'The problem text.',
  solution: 'The solution text.',
  cover: { url: '/storage/projects/cover.jpg', alt: 'Portfolio cover' },
  images: [
    { url: '/storage/projects/one.jpg', alt: 'Home screen', width: 1600, height: 900 },
    { url: '/storage/projects/two.jpg', alt: 'Project page', width: null, height: null },
  ],
  categories: [{ slug: 'web-apps', name: 'Web Apps' }],
  technologies: [
    { slug: 'react', name: 'React' },
    { slug: 'laravel', name: 'Laravel' },
  ],
  links: { github: 'https://github.com/example/portfolio', live: 'https://example.com' },
  alternates: { en: 'portfolio-site', ar: 'mawqi-shakhsi' },
  seo: { title: 'Portfolio Site', description: 'SEO description from the API', image_url: '/storage/projects/cover.jpg' },
})

const arabic = projectDetail({
  slug: 'mawqi-shakhsi',
  locale: 'ar',
  title: 'موقع شخصي',
  short_description: 'وصف قصير',
  description: 'وصف طويل',
  alternates: { en: 'portfolio-site', ar: 'mawqi-shakhsi' },
  seo: { title: 'موقع شخصي', description: 'وصف قصير', image_url: null },
})

const englishOnly = projectDetail({ slug: 'english-only', title: 'English Only', alternates: { en: 'english-only', ar: null } })

const api = () => stubApi({ details: (locale) => (locale === 'ar' ? [arabic] : [full, englishOnly]) })

let description: HTMLMetaElement
beforeEach(() => {
  clearApiQueryCache()
  description = document.createElement('meta')
  description.name = 'description'
  description.content = 'Default description'
  document.head.append(description)
})
afterEach(() => description.remove())

describe('Project page', () => {
  it('shows every field of the project from the API', async () => {
    const fetchMock = api()
    renderRoute('/en/projects/portfolio-site')

    expect(await screen.findByRole('heading', { level: 1, name: 'Portfolio Site' })).toBeInTheDocument()
    expect(requestedUrls(fetchMock)).toContain('/api/projects/portfolio-site?locale=en')
    expect(screen.getByText('A bilingual portfolio.')).toBeInTheDocument()

    // Plain text: paragraphs from blank lines, never HTML.
    const overview = screen.getByRole('heading', { level: 2, name: 'Overview' }).closest('section')!
    expect(within(overview).getAllByText(/paragraph/).map((p) => p.tagName)).toEqual(['P', 'P'])
    expect(screen.getByRole('heading', { level: 2, name: 'The problem' })).toBeInTheDocument()
    expect(screen.getByText('The solution text.')).toBeInTheDocument()

    expect(screen.getByRole('img', { name: 'Portfolio cover' })).toHaveAttribute('src', '/storage/projects/cover.jpg')
    const gallery = screen.getByRole('heading', { level: 2, name: 'Gallery' }).closest('section')!
    const shot = within(gallery).getByRole('img', { name: 'Home screen' })
    expect(shot).toHaveAttribute('width', '1600')
    expect(shot).toHaveAttribute('height', '900')
    expect(shot).toHaveAttribute('loading', 'lazy')
    expect(within(gallery).getByRole('img', { name: 'Project page' })).not.toHaveAttribute('width')

    expect(screen.getByRole('link', { name: 'React' })).toHaveAttribute('href', '/en/projects?technology=react')
    expect(screen.getByRole('link', { name: 'Web Apps' })).toHaveAttribute('href', '/en/projects?category=web-apps')
    expect(screen.getByText('October 20, 2026')).toHaveAttribute('datetime', '2026-10-20T10:00:00Z')

    const live = screen.getByRole('link', { name: /Live demo/ })
    expect(live).toHaveAttribute('href', 'https://example.com')
    expect(live).toHaveAttribute('target', '_blank')
    expect(live).toHaveAttribute('rel', 'noopener noreferrer')
    expect(live).toHaveAccessibleName('Live demo (opens in a new tab)')
    expect(screen.getByRole('link', { name: /GitHub/ })).toHaveAttribute('href', 'https://github.com/example/portfolio')

    await waitFor(() => expect(document.title).toBe('Portfolio Site · Software Developer Portfolio'))
    await waitFor(() => expect(description.content).toBe('SEO description from the API'))
    expect(screen.getByRole('link', { name: 'All projects' })).toHaveAttribute('href', '/en/projects')
  })

  it('hides every missing value instead of inventing one', async () => {
    api()
    renderRoute('/en/projects/english-only')

    expect(await screen.findByRole('heading', { level: 1, name: 'English Only' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'The problem' })).toBeNull()
    expect(screen.queryByRole('heading', { name: 'The solution' })).toBeNull()
    expect(screen.queryByRole('heading', { name: 'Gallery' })).toBeNull()
    expect(screen.queryByRole('heading', { name: 'Technologies' })).toBeNull()
    expect(screen.queryByRole('img')).toBeNull()
    expect(screen.queryByRole('link', { name: /Live demo|GitHub/ })).toBeNull()
  })

  it('restores the page title and description when leaving the page', async () => {
    api()
    document.title = 'Default title'
    const { router } = renderRoute('/en/projects/portfolio-site')
    await waitFor(() => expect(document.title).toBe('Portfolio Site · Software Developer Portfolio'))
    await waitFor(() => expect(description.content).toBe('SEO description from the API'))

    await router.navigate('/en')
    await waitFor(() => expect(document.title).toBe('Default title'))
    expect(description.content).toBe('Default description')
  })

  it('shows a loading state with a page heading', async () => {
    stubApi({ pending: true })
    renderRoute('/en/projects/portfolio-site')
    expect(await screen.findByTestId('project-loading')).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 1, name: 'Project details' })).toHaveClass('sr-only')
  })

  it('recovers from a server error with retry', async () => {
    const fetchMock = stubApi({ details: 'error' })
    renderRoute('/en/projects/portfolio-site')

    const alert = await screen.findByRole('alert')
    fetchMock.mockClear()
    api()
    await userEvent.click(within(alert).getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('heading', { level: 1, name: 'Portfolio Site' })).toBeInTheDocument()
  })

  it('shows a project-specific 404 for an unknown slug', async () => {
    const fetchMock = api()
    renderRoute('/en/projects/does-not-exist')

    expect(await screen.findByRole('heading', { level: 1, name: 'Project not found' })).toBeInTheDocument()
    expect(requestedUrls(fetchMock)).toContain('/api/projects/does-not-exist?locale=en')
    expect(screen.getAllByRole('link', { name: 'All projects' }).every((link) => link.getAttribute('href') === '/en/projects')).toBe(true)
    await waitFor(() => expect(document.title).toBe('Project not found · Software Developer Portfolio'))
  })

  it('does not even ask the API for a malformed slug', async () => {
    const fetchMock = api()
    renderRoute('/en/projects/Not_A_Slug')

    expect(await screen.findByRole('heading', { level: 1, name: 'Project not found' })).toBeInTheDocument()
    expect(requestedUrls(fetchMock).filter((url) => url.startsWith('/api/projects/'))).toEqual([])
  })
})

describe('Project page — languages', () => {
  it('renders the Arabic translation right-to-left with Latin digits', async () => {
    const fetchMock = api()
    renderRoute('/ar/projects/mawqi-shakhsi')

    expect(await screen.findByRole('heading', { level: 1, name: 'موقع شخصي' })).toBeInTheDocument()
    await waitFor(() => expect(document.documentElement).toHaveAttribute('dir', 'rtl'))
    expect(requestedUrls(fetchMock)).toContain('/api/projects/mawqi-shakhsi?locale=ar')
    const date = screen.getByRole('heading', { name: 'تاريخ النشر' }).parentElement!.querySelector('time')!
    expect(date.textContent).toContain('2026')
    expect(date.textContent).not.toMatch(/[٠-٩]/)
  })

  it('switches language to the translation from `alternates`', async () => {
    api()
    const { router } = renderRoute('/en/projects/portfolio-site')
    await screen.findByRole('heading', { level: 1, name: 'Portfolio Site' })

    const switcher = screen.getByRole('link', { name: /Switch language to العربية/ })
    await waitFor(() => expect(switcher).toHaveAttribute('href', '/ar/projects/mawqi-shakhsi'))

    await userEvent.click(switcher)
    expect(router.state.location.pathname).toBe('/ar/projects/mawqi-shakhsi')
    expect(await screen.findByRole('heading', { level: 1, name: 'موقع شخصي' })).toBeInTheDocument()
    await waitFor(() =>
      expect(screen.getByRole('link', { name: /تغيير اللغة إلى English/ })).toHaveAttribute('href', '/en/projects/portfolio-site'),
    )
  })

  it('falls back to the other language project list without a translation (S1)', async () => {
    api()
    renderRoute('/en/projects/english-only')
    await screen.findByRole('heading', { level: 1, name: 'English Only' })
    await waitFor(() => expect(screen.getByRole('link', { name: /Switch language to العربية/ })).toHaveAttribute('href', '/ar/projects'))
  })

  it('a slug from the other language is not found here, and the switcher goes to the list', async () => {
    api()
    renderRoute('/ar/projects/portfolio-site')
    expect(await screen.findByRole('heading', { level: 1, name: 'المشروع غير موجود' })).toBeInTheDocument()
    await waitFor(() => expect(screen.getByRole('link', { name: /تغيير اللغة إلى English/ })).toHaveAttribute('href', '/en/projects'))
  })
})
