import { Link } from 'react-router'
import type { Locale } from '../../i18n/config'
import { useI18n } from '../../i18n/I18nProvider'
import { projectsPath, type ProjectListFilters } from '../../lib/projectListParams'

type PageItem = number | 'gap'

/** First, last, and the pages around the current one, with gaps in between. */
export function pageItems(current: number, last: number): PageItem[] {
  const pages = new Set([1, last, current - 1, current, current + 1].filter((page) => page >= 1 && page <= last))
  const sorted = [...pages].sort((a, b) => a - b)
  const items: PageItem[] = []
  for (const page of sorted) {
    const previous = items[items.length - 1]
    if (typeof previous === 'number' && page - previous > 1) items.push('gap')
    items.push(page)
  }
  return items
}

const pill =
  'inline-flex h-10 min-w-10 items-center justify-center gap-2 rounded-full px-3 text-sm font-medium transition'

/**
 * Page links built from `current_page` / `last_page` (Laravel's `meta.links` labels are
 * English HTML entities, so they are not used). Links keep the active filters.
 */
export function Pagination({
  locale,
  filters,
  currentPage,
  lastPage,
}: {
  locale: Locale
  filters: ProjectListFilters
  currentPage: number
  lastPage: number
}) {
  const { t } = useI18n()
  if (lastPage <= 1) return null

  const to = (page: number) => projectsPath(locale, { ...filters, page })
  const arrow = (symbol: string) => (
    <span aria-hidden="true" className="rtl:-scale-x-100">
      {symbol}
    </span>
  )

  return (
    <nav aria-label={t.pagination.label} className="mt-12 flex justify-center">
      <ul className="flex flex-wrap items-center justify-center gap-2">
        <li>
          {currentPage > 1 ? (
            <Link to={to(currentPage - 1)} preventScrollReset rel="prev" className={`glass ${pill} text-white hover:border-cyan-300/40 hover:bg-white/10`}>
              {arrow('←')}
              {t.pagination.previous}
            </Link>
          ) : (
            <span aria-disabled="true" className={`${pill} border border-white/5 text-slate-600`}>
              {arrow('←')}
              {t.pagination.previous}
            </span>
          )}
        </li>

        {pageItems(currentPage, lastPage).map((item, index) =>
          item === 'gap' ? (
            <li key={`gap-${index}`} aria-hidden="true" className="px-1 text-slate-500">
              …
            </li>
          ) : (
            <li key={item}>
              <Link
                to={to(item)}
                preventScrollReset
                aria-label={t.pagination.page(item)}
                aria-current={item === currentPage ? 'page' : undefined}
                className={`${pill} ${
                  item === currentPage
                    ? 'bg-gradient-to-r from-violet-500 to-cyan-400 font-semibold text-ink-950'
                    : 'glass text-slate-200 hover:border-cyan-300/40 hover:bg-white/10 hover:text-white'
                }`}
              >
                {item}
              </Link>
            </li>
          ),
        )}

        <li>
          {currentPage < lastPage ? (
            <Link to={to(currentPage + 1)} preventScrollReset rel="next" className={`glass ${pill} text-white hover:border-cyan-300/40 hover:bg-white/10`}>
              {t.pagination.next}
              {arrow('→')}
            </Link>
          ) : (
            <span aria-disabled="true" className={`${pill} border border-white/5 text-slate-600`}>
              {t.pagination.next}
              {arrow('→')}
            </span>
          )}
        </li>
      </ul>
    </nav>
  )
}
