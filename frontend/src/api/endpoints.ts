import type { Locale } from '../i18n/config'
import { apiGet, apiSend, type RequestOptions } from './client'
import type {
  ApiItem,
  ApiList,
  Category,
  Paginated,
  ProjectDetail,
  ProjectSummary,
  SiteSettings,
  Technology,
} from './types'

export interface ProjectListParams {
  page?: number
  perPage?: number
  /** Category slug in the requested locale. */
  category?: string
  /** Technology slug (language-neutral). */
  technology?: string
  /** true = featured only; omitted/false = no featured filter. */
  featured?: boolean
}

export async function fetchSiteSettings(locale: Locale, options?: RequestOptions): Promise<SiteSettings> {
  return (await apiGet<ApiItem<SiteSettings>>('/site-settings', { locale }, options)).data
}

export async function fetchTechnologies(locale: Locale, options?: RequestOptions): Promise<Technology[]> {
  return (await apiGet<ApiList<Technology>>('/technologies', { locale }, options)).data
}

export async function fetchCategories(locale: Locale, options?: RequestOptions): Promise<Category[]> {
  return (await apiGet<ApiList<Category>>('/categories', { locale }, options)).data
}

export function fetchProjects(
  locale: Locale,
  params: ProjectListParams = {},
  options?: RequestOptions,
): Promise<Paginated<ProjectSummary>> {
  return apiGet<Paginated<ProjectSummary>>(
    '/projects',
    {
      locale,
      page: params.page,
      per_page: params.perPage,
      category: params.category,
      technology: params.technology,
      featured: params.featured ? true : undefined,
    },
    options,
  )
}

export async function fetchProject(locale: Locale, slug: string, options?: RequestOptions): Promise<ProjectDetail> {
  return (await apiGet<ApiItem<ProjectDetail>>(`/projects/${encodeURIComponent(slug)}`, { locale }, options)).data
}

export interface ContactMessageInput {
  name: string
  email: string
  subject: string
  message: string
}

/** Sends the contact form; it is stored in the administration inbox. */
export async function sendContactMessage(locale: Locale, input: ContactMessageInput, options?: RequestOptions): Promise<void> {
  await apiSend('POST', '/contact-messages', { ...input, locale }, options)
}
