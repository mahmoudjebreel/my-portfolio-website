/**
 * Response types of the Laravel public API (backend/app/Http/Resources, SiteSettingsController).
 * Verified against the JSON actually produced by the backend resources — keep them in sync.
 */
import type { Locale } from '../i18n/config'

/** Single-resource envelope: `{ "data": T }`. */
export interface ApiItem<T> {
  data: T
}

/** Collection envelope: `{ "data": T[] }`. */
export interface ApiList<T> {
  data: T[]
}

/** Laravel length-aware pagination as returned by API resource collections. */
export interface Paginated<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    links: { url: string | null; label: string; page: number | null; active: boolean }[]
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

/** GET /api/site-settings — public allow-listed keys only; missing values are null. */
export interface SiteSettings {
  locale: Locale
  display_name: string | null
  professional_title: string | null
  hero_eyebrow: string | null
  hero_headline: string | null
  hero_description: string | null
  about_text: string | null
  experience_text: string | null
  footer_text: string | null
  seo: { title: string | null; description: string | null; image_url: string | null }
  contact: { email: string | null; github_url: string | null; linkedin_url: string | null }
  profile_image: { url: string; alt: string | null } | null
}

export type TechnologyGroup = 'frontend' | 'backend' | 'database' | 'tools'

/** GET /api/technologies — visible technologies only. */
export interface Technology {
  slug: string
  name: string
  group: TechnologyGroup
  icon: string | null
  description: string | null
}

/** GET /api/categories — categories translated into the requested locale. */
export interface Category {
  slug: string
  name: string
  projects_count: number
}

export interface ProjectRef {
  slug: string
  name: string
}

export interface ProjectCover {
  url: string
  alt: string
}

export interface ProjectLinks {
  github: string | null
  live: string | null
}

/** Item of GET /api/projects (published + translated in the locale). */
export interface ProjectSummary {
  slug: string
  title: string
  short_description: string
  is_featured: boolean
  cover: ProjectCover | null
  categories: ProjectRef[]
  technologies: ProjectRef[]
  links: ProjectLinks
  /** ISO 8601 UTC, e.g. "2026-10-20T10:00:00Z". */
  published_at: string | null
}

export interface ProjectImage {
  url: string
  alt: string
  width: number | null
  height: number | null
}

/** GET /api/projects/{slug}. */
export interface ProjectDetail {
  slug: string
  locale: Locale
  title: string
  short_description: string
  description: string
  problem: string | null
  solution: string | null
  is_featured: boolean
  cover: ProjectCover | null
  images: ProjectImage[]
  categories: ProjectRef[]
  technologies: ProjectRef[]
  links: ProjectLinks
  published_at: string | null
  /** Slug per locale; null when no translation exists in that locale. */
  alternates: Record<Locale, string | null>
  seo: { title: string; description: string; image_url: string | null }
}

/** 422 body: `{ "message": string, "errors": { field: string[] } }`. */
export interface ApiValidationBody {
  message: string
  errors: Record<string, string[]>
}
