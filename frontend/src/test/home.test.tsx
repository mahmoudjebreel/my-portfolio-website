import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it } from 'vitest'
import { clearApiQueryCache } from '../data/useApiQuery'
import { project, requestedUrls, stubApi } from './apiStub'
import { renderRoute } from './renderRoute'

// Texts that only exist in src/mocks — they must never appear when the app uses the API.
const SAMPLE_TEXTS = [/Sample project/i, /Your Name/, /اسمك/, /Building Digital Experiences That Feel Different/]

const expectNoSampleData = () => {
  for (const text of SAMPLE_TEXTS) expect(document.body.textContent ?? '').not.toMatch(text)
}

const fullApi = () =>
  stubApi({
    settings: (locale) =>
      locale === 'ar'
        ? { display_name: 'اسم للاختبار', hero_headline: 'عنوان اختباري', hero_description: 'وصف اختباري', about_text: 'نبذة اختبارية' }
        : {
            display_name: 'Test Developer',
            hero_eyebrow: 'FIXTURE EYEBROW',
            hero_headline: 'Fixture headline from the API',
            hero_description: 'Fixture description from the API.',
            about_text: 'Fixture about text from the API.',
            footer_text: 'Fixture footer text.',
            contact: { email: 'dev@example.test', github_url: 'https://github.com/example', linkedin_url: null },
          },
    technologies: [
      { slug: 'react', name: 'React', group: 'frontend', icon: null, description: null },
      { slug: 'html5', name: 'HTML5', group: 'frontend', icon: null, description: null },
      { slug: 'mysql', name: 'MySQL', group: 'database', icon: null, description: null },
    ],
    projects: [
      project({
        slug: 'api-project',
        title: 'API Project',
        short_description: 'Loaded from the API.',
        cover: { url: '/storage/projects/cover.jpg', alt: 'API Project cover' },
        categories: [{ slug: 'web-applications', name: 'Web Applications' }],
        technologies: [{ slug: 'react', name: 'React' }],
        links: { github: 'https://github.com/example/api-project', live: null },
      }),
      project({ slug: 'bare-project', title: 'Bare Project' }),
    ],
  })

beforeEach(() => clearApiQueryCache())

describe('Home page connected to the API', () => {
  it('renders API content in English, LTR, with one shared settings request', async () => {
    const fetchMock = fullApi()
    renderRoute('/en')

    expect(await screen.findByRole('heading', { level: 1, name: 'Fixture headline from the API' })).toBeInTheDocument()
    await waitFor(() => expect(document.documentElement).toHaveAttribute('dir', 'ltr'))
    expect(screen.getByText('FIXTURE EYEBROW')).toBeInTheDocument()
    expect(screen.getByText('Fixture description from the API.')).toBeInTheDocument()
    expect(await screen.findByText('Fixture about text from the API.')).toBeInTheDocument()
    expect(screen.getByText('Fixture footer text.')).toBeInTheDocument()
    expect(screen.getAllByText('Test Developer').length).toBeGreaterThan(0)
    expect(screen.getAllByRole('link', { name: 'GitHub' })[0]).toHaveAttribute('href', 'https://github.com/example')
    expect(screen.queryByRole('link', { name: 'LinkedIn' })).toBeNull()

    const urls = requestedUrls(fetchMock)
    expect(urls.filter((url) => url.startsWith('/api/site-settings'))).toEqual(['/api/site-settings?locale=en'])
    expect(urls).toContain('/api/technologies?locale=en')
    expect(urls).toContain('/api/projects?locale=en&per_page=3&featured=1')
    expectNoSampleData()
  })

  it('renders Arabic API content with RTL direction', async () => {
    fullApi()
    renderRoute('/ar')

    expect(await screen.findByRole('heading', { level: 1, name: 'عنوان اختباري' })).toBeInTheDocument()
    await waitFor(() => expect(document.documentElement).toHaveAttribute('dir', 'rtl'))
    expect(document.documentElement).toHaveAttribute('lang', 'ar')
    expect(screen.getByRole('link', { name: 'استكشف أعمالي' })).toBeInTheDocument()
  })

  it('groups technologies from the API and hides empty groups', async () => {
    fullApi()
    renderRoute('/en')

    const section = (await screen.findByRole('heading', { name: 'The tools I build with' })).closest('section')!
    await within(section).findByText('React')
    expect(within(section).getByRole('heading', { name: 'Front-End' })).toBeInTheDocument()
    expect(within(section).getByRole('heading', { name: 'Database' })).toBeInTheDocument()
    expect(within(section).queryByRole('heading', { name: 'Back-End' })).toBeNull()
    expect(within(section).getByText('MySQL')).toBeInTheDocument()
  })

  it('shows featured projects from the API with real links only', async () => {
    fullApi()
    renderRoute('/en')

    const section = (await screen.findByRole('heading', { name: 'Selected projects' })).closest('section')!
    expect(await within(section).findByRole('heading', { name: 'API Project' })).toBeInTheDocument()
    expect(within(section).getByText('Web Applications')).toBeInTheDocument()
    expect(within(section).getByRole('img', { name: 'API Project cover' })).toHaveAttribute('src', '/storage/projects/cover.jpg')
    expect(within(section).getByRole('link', { name: 'GitHub: API Project' })).toHaveAttribute('href', 'https://github.com/example/api-project')
    expect(within(section).queryByRole('link', { name: /Live demo/ })).toBeNull()

    // Every card links to its project page.
    expect(within(section).getByRole('link', { name: 'API Project' })).toHaveAttribute('href', '/en/projects/api-project')

    // A project without links, cover or technologies shows none of them (only the link to its page).
    const bare = within(section).getByRole('heading', { name: 'Bare Project' }).closest('article')!
    expect(within(bare).getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/en/projects/bare-project'])
    expect(within(bare).queryByRole('img')).toBeNull()
    expect(within(bare).queryByRole('list')).toBeNull()
    expect(within(section).queryByText('Sample')).toBeNull()
  })

  it('shows loading states while the API has not answered, without sample data', async () => {
    stubApi({ pending: true })
    renderRoute('/en')

    expect(await screen.findByTestId('hero-loading')).toBeInTheDocument()
    expect(screen.getByTestId('about-loading')).toBeInTheDocument()
    expect(screen.getByTestId('skills-loading')).toBeInTheDocument()
    expect(screen.getByTestId('projects-loading')).toBeInTheDocument()
    expectNoSampleData()
  })

  it('shows errors with retry when the API fails — never falls back to sample data', async () => {
    const fetchMock = stubApi({ settings: 'error', technologies: 'error', projects: 'error' })
    renderRoute('/en')

    await waitFor(() => expect(screen.getAllByRole('alert')).toHaveLength(4)) // hero + about (settings), skills, projects
    expectNoSampleData()
    expect(screen.queryByRole('heading', { name: 'Fixture headline from the API' })).toBeNull()

    // Recover: the API answers again, retry reloads the section.
    fetchMock.mockClear()
    fullApi()
    await userEvent.click(screen.getAllByRole('button', { name: 'Retry' })[0]!)
    expect(await screen.findByRole('heading', { level: 1, name: 'Fixture headline from the API' })).toBeInTheDocument()
  })

  it('handles an empty database honestly: hidden values and empty states', async () => {
    stubApi()
    renderRoute('/en')

    expect(await screen.findByText('No technologies are available to display.')).toBeInTheDocument()
    expect(await screen.findByText('There are no featured projects to show right now.')).toBeInTheDocument()
    // One (screen-reader) h1 remains; nothing invented on screen.
    expect(screen.getByRole('heading', { level: 1, name: 'Software developer portfolio' })).toHaveClass('sr-only')
    expect(screen.queryByRole('link', { name: 'GitHub' })).toBeNull()
    expect(screen.getByRole('link', { name: 'Home' })).toHaveTextContent('</>')
    expectNoSampleData()
  })

  it('hides individual missing values', async () => {
    stubApi({ settings: { hero_headline: 'Only a headline' } })
    renderRoute('/en')

    const hero = (await screen.findByRole('heading', { level: 1, name: 'Only a headline' })).closest('section')!
    expect(within(hero).queryByText(/FIXTURE/)).toBeNull()
    expect(hero.querySelectorAll('p.eyebrow')).toHaveLength(0)
  })
})

describe('Home page shell', () => {
  it('language switcher points to the same page in the other language', async () => {
    stubApi()
    renderRoute('/en')
    const link = await screen.findByRole('link', { name: /Switch language to العربية/ })
    expect(link).toHaveAttribute('href', '/ar')
  })

  it('links to all projects and keeps header links working from any page', async () => {
    stubApi()
    renderRoute('/ar')
    expect(await screen.findByRole('link', { name: 'كل المشاريع' })).toHaveAttribute('href', '/ar/projects')
    const nav = screen.getAllByRole('navigation', { name: 'التنقل الرئيسي' })[0]!
    expect(within(nav).getByRole('link', { name: 'المشاريع' })).toHaveAttribute('href', '/ar#projects')
    expect(within(nav).getByRole('link', { name: 'نبذة عني' })).toHaveAttribute('href', '/ar#about')
  })

  it('shows the CSS fallback instead of the 3D scene when WebGL is unavailable (jsdom)', async () => {
    stubApi()
    renderRoute('/en')
    const visual = await screen.findByTestId('hero-visual')
    expect(within(visual).getByTestId('hero-fallback')).toBeInTheDocument()
    expect(visual.querySelector('canvas')).toBeNull()
  })

  it('unknown locale and unknown paths render the 404 page', async () => {
    stubApi()
    renderRoute('/fr')
    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
  })
})

describe('Contact form', () => {
  const fillValid = async (user: ReturnType<typeof userEvent.setup>) => {
    await user.type(screen.getByLabelText('Full Name'), 'Test Person')
    await user.type(screen.getByLabelText('Email Address'), 'person@example.com')
    await user.type(screen.getByLabelText('Subject'), 'Hello there')
    await user.type(screen.getByLabelText('Message'), 'A message long enough.')
  }

  it('validates fields before sending anything', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    renderRoute('/en')
    const send = await screen.findByRole('button', { name: 'Send Message' })

    await user.click(send)
    expect(screen.getAllByText('This field is required.')).toHaveLength(4)
    expect(screen.getByLabelText('Full Name')).toHaveFocus()

    await user.type(screen.getByLabelText('Full Name'), 'Test Person')
    await user.type(screen.getByLabelText('Email Address'), 'not-an-email')
    await user.type(screen.getByLabelText('Subject'), 'Hello there')
    await user.type(screen.getByLabelText('Message'), 'A message long enough.')
    await user.click(send)
    expect(screen.getByText('Please enter a valid email address.')).toBeInTheDocument()
    expect(requestedUrls(fetchMock).some((url) => url.includes('contact-messages'))).toBe(false)
  })

  it('sends the message with the page language, confirms it and clears the form', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    renderRoute('/en')
    await screen.findByRole('button', { name: 'Send Message' })

    await user.type(screen.getByLabelText('Full Name'), '  Test Person  ')
    await user.type(screen.getByLabelText('Email Address'), 'person@example.com')
    await user.type(screen.getByLabelText('Subject'), 'Hello there')
    await user.type(screen.getByLabelText('Message'), 'A message long enough.')
    await user.click(screen.getByRole('button', { name: 'Send Message' }))

    expect(await screen.findByText(/Your message has been sent/)).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([url]) => url.includes('/api/contact-messages'))
    expect(call?.[1]?.method).toBe('POST')
    expect(JSON.parse(String(call?.[1]?.body))).toEqual({
      name: 'Test Person',
      email: 'person@example.com',
      subject: 'Hello there',
      message: 'A message long enough.',
      locale: 'en',
    })
    expect(screen.getByLabelText('Full Name')).toHaveValue('')
  })

  it('shows server field errors and failures without claiming success', async () => {
    stubApi({ contact: { email: 'The email field must be a valid email address.' } })
    const user = userEvent.setup()
    renderRoute('/ar')
    await screen.findByRole('button', { name: 'إرسال الرسالة' })

    await user.type(screen.getByLabelText('الاسم الكامل'), 'شخص للاختبار')
    await user.type(screen.getByLabelText('البريد الإلكتروني'), 'person@example.com')
    await user.type(screen.getByLabelText('الموضوع'), 'مرحبا بك')
    await user.type(screen.getByLabelText('الرسالة'), 'رسالة طويلة بما يكفي.')
    await user.click(screen.getByRole('button', { name: 'إرسال الرسالة' }))

    expect(await screen.findByText('The email field must be a valid email address.')).toBeInTheDocument()
    expect(screen.getByText('يرجى مراجعة الحقول المحددة.')).toBeInTheDocument()
    expect(screen.getByLabelText('البريد الإلكتروني')).toHaveFocus()
    expect(screen.queryByText(/تم إرسال رسالتك/)).not.toBeInTheDocument()
  })

  it('reports a server error and a rate limit', async () => {
    stubApi({ contact: 'error' })
    const user = userEvent.setup()
    const { unmount } = renderRoute('/en')
    await screen.findByRole('button', { name: 'Send Message' })
    await fillValid(user)
    await user.click(screen.getByRole('button', { name: 'Send Message' }))
    expect(await screen.findByText(/could not be sent/)).toBeInTheDocument()
    expect(screen.getByLabelText('Full Name')).toHaveValue('Test Person')
    unmount()

    clearApiQueryCache()
    stubApi({ contact: 'rate_limited' })
    renderRoute('/en')
    await screen.findByRole('button', { name: 'Send Message' })
    await fillValid(user)
    await user.click(screen.getByRole('button', { name: 'Send Message' }))
    expect(await screen.findByText(/Too many messages/)).toBeInTheDocument()
  })
})
