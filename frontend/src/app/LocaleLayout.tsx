import { Outlet, ScrollRestoration, useParams } from 'react-router'
import { DataSourceBadge } from '../components/dev/DataSourceBadge'
import { Footer } from '../components/layout/Footer'
import { Header } from '../components/layout/Header'
import { RouteProgress } from '../components/ui/RouteStates'
import { SiteSettingsProvider } from '../data/SiteSettingsContext'
import { DEFAULT_LOCALE, isLocale } from '../i18n/config'
import { I18nProvider, useI18n } from '../i18n/I18nProvider'
import { NotFoundPage } from '../pages/NotFoundPage'
import { AlternateLocalePathProvider } from './AlternateLocalePath'

/** Validates the `/:locale` segment and provides language + direction to every public page. */
export function LocaleLayout() {
  const { locale } = useParams()

  if (!isLocale(locale)) {
    return (
      <I18nProvider locale={DEFAULT_LOCALE}>
        <NotFoundPage />
      </I18nProvider>
    )
  }

  return (
    <I18nProvider locale={locale}>
      <SiteSettingsProvider>
        <AlternateLocalePathProvider>
          <PublicShell />
        </AlternateLocalePathProvider>
      </SiteSettingsProvider>
    </I18nProvider>
  )
}

function PublicShell() {
  const { t } = useI18n()
  return (
    <>
      <a
        href="#main"
        className="fixed start-4 top-4 z-[60] -translate-y-24 rounded-full bg-white px-4 py-2 text-sm font-semibold text-ink-950 transition focus:translate-y-0"
      >
        {t.skipToContent}
      </a>
      <RouteProgress />
      <Header />
      <main id="main">
        <Outlet />
      </main>
      <Footer />
      <DataSourceBadge />
      {/* New pages start at the top (or at the #section in the URL); back/forward restore the position. */}
      <ScrollRestoration />
    </>
  )
}
