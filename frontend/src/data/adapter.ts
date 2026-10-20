import type { RequestOptions } from '../api/client'
import {
  fetchCategories,
  fetchProject,
  fetchProjects,
  fetchSiteSettings,
  fetchTechnologies,
  type ProjectListParams,
} from '../api/endpoints'
import type { Category, Paginated, ProjectDetail, ProjectSummary, SiteSettings, Technology } from '../api/types'
import type { Locale } from '../i18n/config'
import type { DataSource } from './sourceConfig'
import { isMockDataSource } from './source'

/** Everything the public site reads. Implemented by the API and (dev only) by the mock adapter. */
export interface PortfolioDataAdapter {
  readonly source: DataSource
  siteSettings(locale: Locale, options?: RequestOptions): Promise<SiteSettings>
  technologies(locale: Locale, options?: RequestOptions): Promise<Technology[]>
  categories(locale: Locale, options?: RequestOptions): Promise<Category[]>
  projects(locale: Locale, params?: ProjectListParams, options?: RequestOptions): Promise<Paginated<ProjectSummary>>
  project(locale: Locale, slug: string, options?: RequestOptions): Promise<ProjectDetail>
}

export const apiAdapter: PortfolioDataAdapter = {
  source: 'api',
  siteSettings: fetchSiteSettings,
  technologies: fetchTechnologies,
  categories: fetchCategories,
  projects: fetchProjects,
  project: fetchProject,
}

/**
 * The adapter for the configured data source. API errors are returned as errors —
 * there is deliberately NO fallback from the API to mock data.
 *
 * `import.meta.env.DEV` is replaced by `false` in production builds, so the mock
 * branch and its dynamic import are removed from the production bundle.
 */
export function getDataAdapter(): Promise<PortfolioDataAdapter> {
  if (import.meta.env.DEV && isMockDataSource()) {
    return import('./mockAdapter').then((module) => module.mockAdapter)
  }
  return Promise.resolve(apiAdapter)
}
