import type { ProjectListParams } from '../api/endpoints'
import type { Locale } from '../i18n/config'
import { getDataAdapter } from './adapter'
import { useApiQuery } from './useApiQuery'

/*
 * Typed data hooks for the public site. They read from the configured data source
 * (API by default). Sections are connected to them in a later phase.
 */

export const useSiteSettings = (locale: Locale) =>
  useApiQuery(`site-settings:${locale}`, async (signal) => (await getDataAdapter()).siteSettings(locale, { signal }))

export const useTechnologies = (locale: Locale) =>
  useApiQuery(`technologies:${locale}`, async (signal) => (await getDataAdapter()).technologies(locale, { signal }))

export const useCategories = (locale: Locale) =>
  useApiQuery(`categories:${locale}`, async (signal) => (await getDataAdapter()).categories(locale, { signal }))

export function useProjects(locale: Locale, params: ProjectListParams = {}) {
  const key = `projects:${locale}:${params.page ?? 1}:${params.perPage ?? ''}:${params.category ?? ''}:${params.technology ?? ''}:${params.featured ? 1 : 0}`
  return useApiQuery(key, async (signal) => (await getDataAdapter()).projects(locale, params, { signal }))
}

/** `slug` null = nothing to load (idle). */
export const useProject = (locale: Locale, slug: string | null) =>
  useApiQuery(slug === null ? null : `project:${locale}:${slug}`, async (signal) =>
    (await getDataAdapter()).project(locale, slug as string, { signal }),
  )
