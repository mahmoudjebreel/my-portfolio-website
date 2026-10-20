import { Link } from 'react-router'
import type { ProjectSummary } from '../../api/types'
import { useI18n } from '../../i18n/I18nProvider'
import { projectPath } from '../../lib/projectListParams'
import { Skeleton } from '../ui/DataStates'

/** Generated artwork accents, used when a project has no cover image (never a fake screenshot). */
const ART = [
  'from-violet-600/50 via-indigo-600/20 to-transparent',
  'from-cyan-500/45 via-sky-600/20 to-transparent',
  'from-fuchsia-500/45 via-violet-600/20 to-transparent',
] as const

export const projectArt = (index: number): string => ART[index % ART.length]!

/** Placeholder artwork shown in place of a missing cover. Decorative only. */
export function ProjectArtwork() {
  return (
    <div aria-hidden="true">
      <div className="grid-backdrop absolute inset-0 opacity-60" />
      <div className="absolute top-1/2 left-1/2 size-28 -translate-1/2 rotate-12 rounded-3xl border border-white/20 bg-white/5 backdrop-blur transition duration-700 group-hover:rotate-45 group-hover:scale-110" />
      <div className="absolute top-1/2 left-1/2 size-16 -translate-1/2 -rotate-12 rounded-2xl border border-cyan-200/30 transition duration-700 group-hover:-rotate-45" />
    </div>
  )
}

/**
 * Project summary card. The title links to the project page and its click area covers the
 * whole card; the external links sit above it, so there is never a link inside a link.
 */
export function ProjectCard({ project, index = 0, headingLevel = 3 }: { project: ProjectSummary; index?: number; headingLevel?: 2 | 3 }) {
  const { locale, t } = useI18n()
  const category = project.categories[0]?.name
  const Heading = headingLevel === 2 ? 'h2' : 'h3'

  return (
    <article className="glass group relative flex h-full flex-col overflow-hidden rounded-3xl transition duration-500 focus-within:border-cyan-300/30 hover:-translate-y-1.5 hover:border-white/20 hover:shadow-[0_30px_80px_-30px] hover:shadow-violet-500/40">
      <div className={`relative aspect-[16/10] overflow-hidden bg-gradient-to-br ${projectArt(index)}`}>
        {project.cover ? (
          <img
            src={project.cover.url}
            alt={project.cover.alt}
            loading="lazy"
            decoding="async"
            className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
          />
        ) : (
          <ProjectArtwork />
        )}
      </div>

      <div className="flex flex-1 flex-col p-6">
        {category && <span className="mb-3 text-xs text-slate-400">{category}</span>}
        <Heading className="font-display text-lg font-semibold text-white">
          <Link
            to={projectPath(locale, project.slug)}
            className="rounded-sm transition after:absolute after:inset-0 after:content-[''] group-hover:text-cyan-100"
          >
            {project.title}
          </Link>
        </Heading>
        <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-400">{project.short_description}</p>

        {project.technologies.length > 0 && (
          <ul className="mt-5 flex flex-wrap gap-2" aria-label={t.projects.technologies}>
            {project.technologies.map((tech) => (
              <li key={tech.slug} dir="auto" className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-200">
                {tech.name}
              </li>
            ))}
          </ul>
        )}

        {(project.links.github || project.links.live) && (
          <div className="relative z-10 mt-5 flex flex-wrap gap-3 border-t border-white/10 pt-4 text-sm">
            {project.links.live && (
              <a
                href={project.links.live}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`${t.projects.live}: ${project.title}`}
                className="font-medium text-cyan-300 transition hover:text-cyan-200"
              >
                {t.projects.live} ↗
              </a>
            )}
            {project.links.github && (
              <a
                href={project.links.github}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`${t.projects.github}: ${project.title}`}
                className="font-medium text-slate-300 transition hover:text-white"
              >
                {t.projects.github} ↗
              </a>
            )}
          </div>
        )}
      </div>
    </article>
  )
}

export function ProjectCardSkeleton() {
  return (
    <div className="glass overflow-hidden rounded-3xl">
      <Skeleton className="aspect-[16/10] w-full rounded-none" />
      <div className="space-y-3 p-6">
        <Skeleton className="h-3 w-24" />
        <Skeleton className="h-5 w-3/4" />
        <Skeleton className="h-4 w-full" />
      </div>
    </div>
  )
}
