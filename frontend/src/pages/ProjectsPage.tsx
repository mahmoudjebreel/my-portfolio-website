import { useEffect, useRef, type ReactNode } from 'react'
import { Link, Navigate, useLocation, useSearchParams } from 'react-router'
import { useSetAlternateLocalePath } from '../app/AlternateLocalePath'
import { Pagination } from '../components/projects/Pagination'
import { ProjectCard, ProjectCardSkeleton } from '../components/projects/ProjectCard'
import { ProjectFilters } from '../components/projects/ProjectFilters'
import { DataError, EmptyState, LoadingLabel } from '../components/ui/DataStates'
import { useProjects } from '../data/queries'
import { otherLocale } from '../i18n/config'
import { useI18n } from '../i18n/I18nProvider'
import { hasActiveFilters, parseProjectListParams, projectListSearch, projectsPath } from '../lib/projectListParams'
import { useDocumentMeta } from '../lib/useDocumentMeta'

export function ProjectsPage() {
  const { locale, t } = useI18n()
  const { search } = useLocation()
  const [searchParams] = useSearchParams()
  const filters = parseProjectListParams(searchParams)
  const canonicalSearch = projectListSearch(filters)

  const { status, data, error, retry } = useProjects(locale, {
    page: filters.page > 1 ? filters.page : undefined,
    category: filters.category ?? undefined,
    technology: filters.technology ?? undefined,
  })
  const resultsHeading = useRef<HTMLHeadingElement>(null)
  const previousPage = useRef(filters.page)

  // Category slugs differ per language: the other language keeps only the technology filter.
  useSetAlternateLocalePath(projectsPath(otherLocale(locale), { technology: filters.technology }))
  useDocumentMeta(`${t.projectsPage.title} · ${t.meta.siteName}`, t.projectsPage.intro)

  // After a page change, move focus (and the view) to the results.
  useEffect(() => {
    if (previousPage.current === filters.page) return
    previousPage.current = filters.page
    resultsHeading.current?.focus()
  }, [filters.page])

  // Invalid or unknown query values are dropped from the URL instead of being sent to the API.
  if (search !== canonicalSearch) return <Navigate to={projectsPath(locale, filters)} replace />

  const filtered = hasActiveFilters(filters)
  const projects = data?.data ?? []
  const meta = data?.meta

  return (
    <section aria-labelledby="projects-page-title" className="relative isolate overflow-hidden pt-32 pb-28">
      <div className="grid-backdrop absolute inset-x-0 top-0 -z-10 h-[28rem]" aria-hidden="true" />
      <div
        className="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(ellipse_at_top,rgba(124,58,237,0.2),transparent_65%)]"
        aria-hidden="true"
      />

      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <header className="mb-10 max-w-2xl">
          <p className="eyebrow mb-3">{t.projectsPage.eyebrow}</p>
          <h1 id="projects-page-title" className="font-display text-4xl font-bold tracking-tight text-white sm:text-5xl">
            <span className="text-gradient">{t.projectsPage.title}</span>
          </h1>
          <p className="mt-4 text-slate-400">{t.projectsPage.intro}</p>
        </header>

        <ProjectFilters locale={locale} filters={filters} />

        <h2 ref={resultsHeading} tabIndex={-1} className="sr-only">
          {t.projectsPage.results}
        </h2>

        <p role="status" className="mb-6 min-h-5 text-sm text-slate-400">
          {status === 'success' && meta && meta.from !== null && meta.to !== null && t.projectsPage.showing(meta.from, meta.to, meta.total)}
        </p>

        {status === 'loading' && (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3" data-testid="projects-page-loading">
            <LoadingLabel />
            {[0, 1, 2, 3, 4, 5].map((i) => (
              <ProjectCardSkeleton key={i} />
            ))}
          </div>
        )}

        {status === 'error' &&
          (error?.kind === 'validation' ? (
            // 422: the category/technology in the URL does not exist (here). Retrying cannot help.
            <Notice role="alert" message={t.projectsPage.invalidFilter}>
              <ActionLink to={projectsPath(locale)}>{t.filters.clear}</ActionLink>
            </Notice>
          ) : (
            <DataError error={error} onRetry={retry} />
          ))}

        {status === 'success' && meta && projects.length === 0 && (
          meta.total > 0 ? (
            <Notice message={t.projectsPage.pageOutOfRange}>
              <ActionLink to={projectsPath(locale, { ...filters, page: 1 })}>{t.projectsPage.firstPage}</ActionLink>
            </Notice>
          ) : filtered ? (
            <Notice message={t.projectsPage.noMatches}>
              <ActionLink to={projectsPath(locale)}>{t.filters.clear}</ActionLink>
            </Notice>
          ) : (
            <EmptyState>{t.projectsPage.empty}</EmptyState>
          )
        )}

        {projects.length > 0 && meta && (
          <>
            <ul className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {projects.map((project, index) => (
                <li key={project.slug}>
                  <ProjectCard project={project} index={index} />
                </li>
              ))}
            </ul>
            <Pagination locale={locale} filters={filters} currentPage={meta.current_page} lastPage={meta.last_page} />
          </>
        )}
      </div>
    </section>
  )
}

function Notice({ message, role, children }: { message: string; role?: 'alert'; children: ReactNode }) {
  return (
    <div role={role} className="glass flex flex-col items-center gap-4 rounded-2xl px-6 py-8 text-center text-slate-400">
      <p>{message}</p>
      {children}
    </div>
  )
}

function ActionLink({ to, children }: { to: string; children: ReactNode }) {
  return (
    <Link
      to={to}
      preventScrollReset
      className="rounded-full border border-white/15 bg-white/5 px-5 py-2 text-sm font-medium text-white transition hover:border-cyan-300/40 hover:bg-white/10"
    >
      {children}
    </Link>
  )
}
