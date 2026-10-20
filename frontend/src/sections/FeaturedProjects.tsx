import { Link } from 'react-router'
import { ProjectCard, ProjectCardSkeleton } from '../components/projects/ProjectCard'
import { DataError, EmptyState, LoadingLabel } from '../components/ui/DataStates'
import { Reveal } from '../components/ui/Reveal'
import { SectionHeading } from '../components/ui/SectionHeading'
import { useProjects } from '../data/queries'
import { useI18n } from '../i18n/I18nProvider'
import { projectsPath } from '../lib/projectListParams'

const FEATURED = { featured: true, perPage: 3 } as const

export function FeaturedProjects() {
  const { locale, t } = useI18n()
  const { status, data, error, retry } = useProjects(locale, FEATURED)
  const projects = data?.data ?? []

  return (
    <section id="projects" aria-labelledby="projects-title" className="relative py-28">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading id="projects-title" eyebrow={t.projects.eyebrow} title={t.projects.title} />

        {status === 'loading' && (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3" data-testid="projects-loading">
            <LoadingLabel />
            {[0, 1, 2].map((i) => (
              <ProjectCardSkeleton key={i} />
            ))}
          </div>
        )}

        {status === 'error' && <DataError error={error} onRetry={retry} />}

        {status === 'success' && projects.length === 0 && <EmptyState>{t.projects.empty}</EmptyState>}

        {projects.length > 0 && (
          <ul className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {projects.map((project, index) => (
              <li key={project.slug}>
                <Reveal delay={index * 0.08} className="h-full">
                  <ProjectCard project={project} index={index} />
                </Reveal>
              </li>
            ))}
          </ul>
        )}

        <div className="mt-12 flex justify-center">
          <Link
            to={projectsPath(locale)}
            className="glass group inline-flex items-center gap-2 rounded-full px-7 py-3.5 font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/10"
          >
            {t.projects.viewAll}
            <span aria-hidden="true" className="transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5">
              →
            </span>
          </Link>
        </div>
      </div>
    </section>
  )
}
