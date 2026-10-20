import { Link, useNavigate } from 'react-router'
import type { Technology, TechnologyGroup } from '../../api/types'
import { useCategories, useTechnologies } from '../../data/queries'
import type { Locale } from '../../i18n/config'
import { useI18n } from '../../i18n/I18nProvider'
import { hasActiveFilters, projectsPath, type ProjectListFilters } from '../../lib/projectListParams'
import { DataError, Skeleton } from '../ui/DataStates'

const GROUPS: TechnologyGroup[] = ['frontend', 'backend', 'database', 'tools']

const chip = 'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition'
const chipIdle = 'border-white/10 bg-white/[0.04] text-slate-300 hover:border-cyan-300/40 hover:bg-white/10 hover:text-white'
const chipActive = 'border-cyan-300/50 bg-cyan-300/10 font-semibold text-white shadow-[0_0_24px_-8px] shadow-cyan-400/60'

/**
 * Category chips and a technology selector. The URL is the only state: every change is a
 * navigation (shareable, works with back/forward) and returns to page 1.
 */
export function ProjectFilters({ locale, filters }: { locale: Locale; filters: ProjectListFilters }) {
  const { t } = useI18n()

  return (
    <section aria-label={t.filters.label} className="glass mb-10 space-y-6 rounded-3xl p-5 sm:p-6">
      <CategoryFilter locale={locale} filters={filters} />
      <div className="flex flex-wrap items-end justify-between gap-4">
        <TechnologyFilter locale={locale} filters={filters} />
        {hasActiveFilters(filters) && (
          <Link
            to={projectsPath(locale)}
            preventScrollReset
            className="rounded-full px-4 py-2 text-sm font-medium text-cyan-300 transition hover:bg-white/5 hover:text-cyan-200"
          >
            {t.filters.clear}
          </Link>
        )}
      </div>
    </section>
  )
}

function CategoryFilter({ locale, filters }: { locale: Locale; filters: ProjectListFilters }) {
  const { t } = useI18n()
  const { status, data, error, retry } = useCategories(locale)
  // Categories without published projects are hidden, unless the URL selects one.
  const categories = (data ?? []).filter((category) => category.projects_count > 0 || category.slug === filters.category)

  return (
    <div>
      <h2 id="filter-category" className="mb-3 text-xs font-semibold tracking-wider text-slate-400 uppercase rtl:tracking-normal">
        {t.filters.category}
      </h2>
      {status === 'loading' && (
        <div className="flex flex-wrap gap-2" data-testid="categories-loading">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-9 w-24 rounded-full" />
          ))}
        </div>
      )}
      {status === 'error' && <DataError error={error} onRetry={retry} />}
      {status === 'success' && (
        <ul className="flex flex-wrap gap-2" aria-labelledby="filter-category">
          <li>
            <Link
              to={projectsPath(locale, { technology: filters.technology })}
              preventScrollReset
              aria-current={filters.category === null ? 'true' : undefined}
              className={`${chip} ${filters.category === null ? chipActive : chipIdle}`}
            >
              {t.filters.allCategories}
            </Link>
          </li>
          {categories.map((category) => {
            const active = category.slug === filters.category
            return (
              <li key={category.slug}>
                <Link
                  // Selecting the active category again removes it.
                  to={projectsPath(locale, { technology: filters.technology, category: active ? null : category.slug })}
                  preventScrollReset
                  aria-current={active ? 'true' : undefined}
                  className={`${chip} ${active ? chipActive : chipIdle}`}
                >
                  {category.name}
                  <span className="text-xs text-slate-400">{category.projects_count}</span>
                </Link>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}

function TechnologyFilter({ locale, filters }: { locale: Locale; filters: ProjectListFilters }) {
  const { t } = useI18n()
  const navigate = useNavigate()
  const { status, data, error, retry } = useTechnologies(locale)
  const technologies = data ?? []
  const known = technologies.some((tech) => tech.slug === filters.technology)

  if (status === 'loading') return <Skeleton className="h-11 w-56 rounded-full" />
  if (status === 'error') return <DataError error={error} onRetry={retry} className="w-full" />

  const byGroup = (group: TechnologyGroup): Technology[] => technologies.filter((tech) => tech.group === group)

  return (
    <label className="flex flex-col gap-2">
      <span className="text-xs font-semibold tracking-wider text-slate-400 uppercase rtl:tracking-normal">{t.filters.technology}</span>
      <select
        value={known ? (filters.technology ?? '') : ''}
        onChange={(event) =>
          navigate(projectsPath(locale, { category: filters.category, technology: event.target.value || null }), {
            preventScrollReset: true,
          })
        }
        className="glass h-11 min-w-56 rounded-full bg-ink-900 px-4 text-sm text-white"
      >
        <option value="">{t.filters.allTechnologies}</option>
        {GROUPS.map((group) =>
          byGroup(group).length > 0 ? (
            <optgroup key={group} label={t.skills.groups[group]}>
              {byGroup(group).map((tech) => (
                <option key={tech.slug} value={tech.slug}>
                  {tech.name}
                </option>
              ))}
            </optgroup>
          ) : null,
        )}
      </select>
    </label>
  )
}
