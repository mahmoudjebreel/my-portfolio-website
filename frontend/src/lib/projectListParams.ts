import type { Locale } from '../i18n/config'

/**
 * URL state of the public project list (`/:locale/projects?category=&technology=&page=`).
 * Values are cleaned with the same rules as the API (ProjectIndexRequest), so a request
 * that would certainly be rejected is never sent. Whether a filter exists is still decided
 * by the API (422).
 */

/** Same slug rule as the API routes and ProjectIndexRequest. */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

const MAX_PAGE = 100_000
const MAX_CATEGORY_LENGTH = 100
const MAX_TECHNOLOGY_LENGTH = 80

export interface ProjectListFilters {
  page: number
  /** Category slug in the current locale. */
  category: string | null
  /** Technology slug (language-neutral). */
  technology: string | null
}

const slugOrNull = (value: string | null, maxLength: number): string | null =>
  value !== null && value.length <= maxLength && SLUG_PATTERN.test(value) ? value : null

export function isProjectSlug(value: string | undefined): value is string {
  return value !== undefined && value.length <= 160 && SLUG_PATTERN.test(value)
}

export function parseProjectListParams(params: URLSearchParams): ProjectListFilters {
  const rawPage = params.get('page')
  const page = rawPage !== null && /^\d{1,6}$/.test(rawPage) ? Number(rawPage) : 1

  return {
    page: page >= 1 && page <= MAX_PAGE ? page : 1,
    category: slugOrNull(params.get('category'), MAX_CATEGORY_LENGTH),
    technology: slugOrNull(params.get('technology'), MAX_TECHNOLOGY_LENGTH),
  }
}

/** Canonical query string ("" when there is nothing to keep). Page 1 is implicit. */
export function projectListSearch({ page = 1, category = null, technology = null }: Partial<ProjectListFilters> = {}): string {
  const params = new URLSearchParams()
  if (category) params.set('category', category)
  if (technology) params.set('technology', technology)
  if (page > 1) params.set('page', String(page))
  const search = params.toString()
  return search ? `?${search}` : ''
}

export const projectsPath = (locale: Locale, filters: Partial<ProjectListFilters> = {}): string =>
  `/${locale}/projects${projectListSearch(filters)}`

export const projectPath = (locale: Locale, slug: string): string => `/${locale}/projects/${encodeURIComponent(slug)}`

export const hasActiveFilters = (filters: ProjectListFilters): boolean => filters.category !== null || filters.technology !== null
