import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clearApiQueryCache } from '../data/useApiQuery'
import { stubApi } from './apiStub'
import { renderRoute } from './renderRoute'

// The project pages are lazy routes. Here their code never arrives (list) or fails to load (detail).
vi.mock('../pages/ProjectsPage', () => new Promise(() => {}))
vi.mock('../pages/ProjectDetailPage', () => {
  throw new Error('Failed to fetch dynamically imported module')
})

beforeEach(() => clearApiQueryCache())

describe('Lazy project pages', () => {
  it('shows a loading state inside the layout on a direct visit', async () => {
    stubApi()
    renderRoute('/en/projects')

    expect(await screen.findByTestId('route-loading')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Loading…')
    // Header and footer are already usable.
    expect(screen.getAllByRole('navigation', { name: 'Main navigation' }).length).toBeGreaterThan(0)
    expect(screen.getByRole('link', { name: /Back to top/ })).toBeInTheDocument()
  })

  it('keeps the home page visible and shows progress while the page code downloads', async () => {
    stubApi()
    const { router } = renderRoute('/en')

    await userEvent.click(await screen.findByRole('link', { name: 'All projects' }))
    expect(await screen.findByTestId('route-progress')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/en')
    expect(screen.getByRole('heading', { name: 'Selected projects' })).toBeInTheDocument()
  })

  it('explains a page that could not be loaded, in the page language, with reload and home actions', async () => {
    stubApi()
    renderRoute('/ar/projects/some-project')

    const alert = await screen.findByRole('alert')
    expect(within(alert).getByRole('heading', { level: 1, name: 'تعذّر تحميل هذه الصفحة' })).toBeInTheDocument()
    expect(within(alert).getByRole('button', { name: 'إعادة تحميل الصفحة' })).toBeInTheDocument()
    expect(within(alert).getByRole('link', { name: 'الذهاب إلى الصفحة الرئيسية' })).toHaveAttribute('href', '/ar')
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(screen.getAllByRole('navigation', { name: 'التنقل الرئيسي' }).length).toBeGreaterThan(0)
  })
})
