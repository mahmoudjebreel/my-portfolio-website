import { Link, useNavigation } from 'react-router'
import { useI18n } from '../../i18n/I18nProvider'
import { LoadingLabel, Skeleton } from './DataStates'

/**
 * Thin bar at the top while the router waits for a lazily loaded page (e.g. the first
 * click on "All projects"); the current page stays visible meanwhile.
 */
export function RouteProgress() {
  const { state } = useNavigation()
  if (state !== 'loading') return null

  return (
    <div data-testid="route-progress">
      <LoadingLabel />
      <div
        aria-hidden="true"
        className="fixed inset-x-0 top-0 z-[70] h-0.5 bg-gradient-to-r from-violet-500 via-fuchsia-400 to-cyan-400 motion-safe:animate-pulse"
      />
    </div>
  )
}

/** Shown in place of a lazily loaded page while its code downloads on a direct visit. */
export function RouteLoading() {
  return (
    <div data-testid="route-loading" className="mx-auto max-w-6xl space-y-5 px-5 pt-32 pb-28 sm:px-8">
      <LoadingLabel />
      <Skeleton className="h-3 w-28" />
      <Skeleton className="h-12 w-2/3 max-w-md" />
      <Skeleton className="h-5 w-full max-w-xl" />
    </div>
  )
}

/**
 * A page's code could not be loaded (offline, or a newer version was deployed since this
 * tab opened). A failed module import is not retried by the browser, so the fix is a reload.
 */
export function RouteError() {
  const { locale, t } = useI18n()

  return (
    <section aria-labelledby="route-error-title" className="grid min-h-[80svh] place-items-center px-5 pt-16 text-center">
      <div role="alert" className="glass max-w-md rounded-3xl px-6 py-10">
        <h1 id="route-error-title" className="font-display text-2xl font-semibold text-white">
          {t.route.failedTitle}
        </h1>
        <p className="mt-3 text-slate-400">{t.route.failedText}</p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <button
            type="button"
            onClick={() => window.location.reload()}
            className="rounded-full bg-gradient-to-r from-violet-500 to-cyan-400 px-6 py-3 font-semibold text-ink-950 transition hover:shadow-[0_10px_40px_-10px] hover:shadow-cyan-400/60"
          >
            {t.route.reload}
          </button>
          <Link
            to={`/${locale}`}
            className="glass inline-flex rounded-full px-6 py-3 font-medium text-white transition hover:border-cyan-300/40 hover:bg-white/10"
          >
            {t.notFound.home}
          </Link>
        </div>
      </div>
    </section>
  )
}
