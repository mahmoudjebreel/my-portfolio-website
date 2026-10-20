import { Navigate, createBrowserRouter, type RouteObject } from 'react-router'
import { RouteError, RouteLoading } from '../components/ui/RouteStates'
import { DEFAULT_LOCALE, detectPreferredLocale } from '../i18n/config'
import { I18nProvider } from '../i18n/I18nProvider'
import { HomePage } from '../pages/HomePage'
import { NotFoundPage } from '../pages/NotFoundPage'
import { LocaleLayout } from './LocaleLayout'

function RootRedirect() {
  return <Navigate to={`/${detectPreferredLocale()}`} replace />
}

/*
 * The project pages are loaded on demand (own chunks), so the home page never waits for
 * their code. A direct visit shows RouteLoading inside the layout; a failed download
 * (offline, new deployment) shows RouteError instead of the router's default error screen.
 */
const lazyPage = {
  hydrateFallbackElement: <RouteLoading />,
  errorElement: <RouteError />,
}

/*
 * The administration is not part of this app: it is the Laravel Filament panel at /admin,
 * served by Laravel (the dev server proxies /admin and the panel's assets to it).
 */
export const routes: RouteObject[] = [
  { path: '/', element: <RootRedirect /> },
  {
    path: '/:locale',
    element: <LocaleLayout />,
    children: [
      { index: true, element: <HomePage /> },
      {
        path: 'projects',
        ...lazyPage,
        lazy: async () => ({ Component: (await import('../pages/ProjectsPage')).ProjectsPage }),
      },
      {
        path: 'projects/:slug',
        ...lazyPage,
        lazy: async () => ({ Component: (await import('../pages/ProjectDetailPage')).ProjectDetailPage }),
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
  {
    path: '*',
    element: (
      <I18nProvider locale={DEFAULT_LOCALE}>
        <NotFoundPage />
      </I18nProvider>
    ),
  },
]

export const createRouter = () => createBrowserRouter(routes)
