/**
 * DEVELOPMENT-ONLY mock adapter (VITE_DATA_SOURCE=mock). Serves the clearly marked
 * sample data from src/mocks in the same shapes as the real API. It is only reachable
 * through a dynamic import guarded by `import.meta.env.DEV`, so it is not part of the
 * production bundle.
 */
import { ApiError } from '../api/client'
import type { ProjectListParams } from '../api/endpoints'
import type { Category, Paginated, ProjectDetail, ProjectSummary, SiteSettings, Technology } from '../api/types'
import type { Locale } from '../i18n/config'
import { sampleProjects, type SampleProject } from '../mocks/projects'
import { mockSiteContent } from '../mocks/siteContent'
import { technologies as mockTechnologies } from '../mocks/technologies'
import type { PortfolioDataAdapter } from './adapter'

/** Used by tests/build checks to prove this module is excluded from production bundles. */
export const MOCK_ADAPTER_MARKER = 'portfolio-dev-mock-adapter'

const slugify = (value: string) =>
  value
    .toLowerCase()
    .replace(/&/g, ' ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const sampleSlug = (project: SampleProject) => slugify(project.title.en.replace(/^Sample project — /, 'sample '))

const technologyRef = (name: string) => ({ slug: slugify(name), name })

function summary(project: SampleProject, locale: Locale): ProjectSummary {
  return {
    slug: sampleSlug(project),
    title: project.title[locale],
    short_description: project.summary[locale],
    is_featured: true,
    cover: null,
    categories: [{ slug: slugify(project.category.en), name: project.category[locale] }],
    technologies: project.technologies.map(technologyRef),
    links: { github: null, live: null },
    published_at: null,
  }
}

export const mockAdapter: PortfolioDataAdapter = {
  source: 'mock',

  async siteSettings(locale): Promise<SiteSettings> {
    const content = mockSiteContent
    return {
      locale,
      display_name: content.displayName[locale],
      professional_title: null,
      hero_eyebrow: content.heroEyebrow[locale],
      hero_headline: content.heroHeadline[locale],
      hero_description: content.heroDescription[locale],
      about_text: content.aboutText[locale],
      experience_text: null,
      footer_text: null,
      seo: { title: null, description: null, image_url: null },
      contact: { email: content.contactEmail, github_url: content.githubUrl, linkedin_url: content.linkedinUrl },
      profile_image: null,
    }
  },

  async technologies(): Promise<Technology[]> {
    return mockTechnologies.map((tech) => ({ slug: tech.slug, name: tech.name, group: tech.group, icon: null, description: null }))
  },

  async categories(locale): Promise<Category[]> {
    const bySlug = new Map<string, Category>()
    for (const project of sampleProjects) {
      const slug = slugify(project.category.en)
      const current = bySlug.get(slug)
      bySlug.set(slug, { slug, name: project.category[locale], projects_count: (current?.projects_count ?? 0) + 1 })
    }
    return [...bySlug.values()]
  },

  async projects(locale, params: ProjectListParams = {}): Promise<Paginated<ProjectSummary>> {
    const perPage = params.perPage ?? 9
    const page = params.page ?? 1
    const all = sampleProjects
      .map((project) => summary(project, locale))
      .filter((item) => !params.category || item.categories.some((c) => c.slug === params.category))
      .filter((item) => !params.technology || item.technologies.some((t) => t.slug === params.technology))
    const lastPage = Math.max(1, Math.ceil(all.length / perPage))
    const data = all.slice((page - 1) * perPage, page * perPage)
    const from = data.length ? (page - 1) * perPage + 1 : null

    return {
      data,
      links: { first: null, last: null, prev: null, next: null },
      meta: {
        current_page: page,
        from,
        last_page: lastPage,
        links: [],
        path: '/api/projects',
        per_page: perPage,
        to: from === null ? null : from + data.length - 1,
        total: all.length,
      },
    }
  },

  async project(locale, slug): Promise<ProjectDetail> {
    const project = sampleProjects.find((item) => sampleSlug(item) === slug)
    if (!project) throw new ApiError('not_found', 'The requested resource was not found.', { status: 404 })
    const base = summary(project, locale)
    return {
      ...base,
      locale,
      description: project.summary[locale],
      problem: null,
      solution: null,
      images: [],
      alternates: { en: base.slug, ar: base.slug },
      seo: { title: base.title, description: base.short_description, image_url: null },
    }
  },
}
