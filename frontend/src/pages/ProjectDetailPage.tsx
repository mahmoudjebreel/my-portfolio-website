import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import type { ProjectDetail } from '../api/types'
import { useSetAlternateLocalePath } from '../app/AlternateLocalePath'
import { ProjectArtwork, projectArt } from '../components/projects/ProjectCard'
import { DataError, LoadingLabel, Skeleton } from '../components/ui/DataStates'
import { useProject } from '../data/queries'
import { otherLocale } from '../i18n/config'
import { useI18n } from '../i18n/I18nProvider'
import { formatDate } from '../lib/formatDate'
import { isProjectSlug, projectPath, projectsPath } from '../lib/projectListParams'
import { useDocumentMeta } from '../lib/useDocumentMeta'

export function ProjectDetailPage() {
  const { locale, t } = useI18n()
  const { slug } = useParams()
  const validSlug = isProjectSlug(slug)
  // A malformed slug can never match (the API route rejects it too): no request is made.
  const { status, data, error, retry } = useProject(locale, validSlug ? slug : null)
  const notFound = !validSlug || (status === 'error' && error?.kind === 'not_found')

  // The other language: this project's translation if it exists, else that language's project list (S1).
  const other = otherLocale(locale)
  const alternate = data?.alternates[other]
  useSetAlternateLocalePath(alternate ? projectPath(other, alternate) : projectsPath(other))

  useDocumentMeta(
    data ? `${data.seo.title} · ${t.meta.siteName}` : notFound ? `${t.projectDetail.notFoundTitle} · ${t.meta.siteName}` : null,
    data?.seo.description ?? null,
  )

  return (
    <article aria-labelledby="project-title" className="relative isolate overflow-hidden pt-28 pb-28">
      <div className="grid-backdrop absolute inset-x-0 top-0 -z-10 h-[30rem]" aria-hidden="true" />
      <div
        className="absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(ellipse_at_top,rgba(124,58,237,0.2),transparent_65%)]"
        aria-hidden="true"
      />

      <div className="mx-auto max-w-5xl px-5 sm:px-8">
        <Link
          to={projectsPath(locale)}
          className="group mb-8 inline-flex items-center gap-2 rounded-full px-1 py-1 text-sm text-slate-400 transition hover:text-white"
        >
          <span aria-hidden="true" className="transition group-hover:-translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:translate-x-0.5">
            ←
          </span>
          {t.projectDetail.back}
        </Link>

        {notFound ? (
          <ProjectNotFound />
        ) : status === 'success' && data ? (
          <ProjectContent project={data} />
        ) : (
          <>
            {/* The page keeps one h1 while loading or after an error. */}
            <h1 id="project-title" className="sr-only">
              {t.projectDetail.fallbackTitle}
            </h1>
            {status === 'error' ? <DataError error={error} onRetry={retry} /> : <ProjectSkeleton />}
          </>
        )}
      </div>
    </article>
  )
}

function ProjectNotFound() {
  const { locale, t } = useI18n()
  return (
    <div className="glass rounded-3xl px-6 py-14 text-center">
      <p className="font-display text-6xl font-bold text-gradient" aria-hidden="true">
        404
      </p>
      <h1 id="project-title" className="mt-4 font-display text-2xl font-semibold text-white">
        {t.projectDetail.notFoundTitle}
      </h1>
      <p className="mt-2 text-slate-400">{t.projectDetail.notFoundText}</p>
      <Link
        to={projectsPath(locale)}
        className="glass mt-8 inline-flex rounded-full px-6 py-3 font-medium text-white transition hover:border-cyan-300/40 hover:bg-white/10"
      >
        {t.projects.viewAll}
      </Link>
    </div>
  )
}

function ProjectSkeleton() {
  return (
    <div data-testid="project-loading" className="space-y-6">
      <LoadingLabel />
      <Skeleton className="h-4 w-32" />
      <Skeleton className="h-12 w-3/4" />
      <Skeleton className="h-5 w-full max-w-xl" />
      <Skeleton className="aspect-[16/9] w-full rounded-3xl" />
    </div>
  )
}

function ProjectContent({ project }: { project: ProjectDetail }) {
  const { locale, t } = useI18n()
  const published = formatDate(project.published_at, locale)
  const hasLinks = Boolean(project.links.live || project.links.github)

  return (
    <>
      <header className="max-w-3xl">
        {project.categories.length > 0 && (
          <ul className="mb-4 flex flex-wrap gap-2" aria-label={t.projectDetail.categories}>
            {project.categories.map((category) => (
              <li key={category.slug}>
                <Link to={projectsPath(locale, { category: category.slug })} className="eyebrow rounded-sm transition hover:text-cyan-200">
                  {category.name}
                </Link>
              </li>
            ))}
          </ul>
        )}
        <h1 id="project-title" className="font-display text-4xl leading-tight font-bold tracking-tight text-white sm:text-5xl">
          <span className="text-gradient">{project.title}</span>
        </h1>
        <p className="mt-5 text-lg leading-relaxed text-slate-300">{project.short_description}</p>

        {hasLinks && (
          <div className="mt-8 flex flex-wrap gap-3">
            {project.links.live && (
              <ExternalLink href={project.links.live} primary>
                {t.projects.live}
              </ExternalLink>
            )}
            {project.links.github && <ExternalLink href={project.links.github}>{t.projects.github}</ExternalLink>}
          </div>
        )}
      </header>

      <div className={`relative mt-12 aspect-[16/9] overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br ${projectArt(0)}`}>
        {project.cover ? (
          <img src={project.cover.url} alt={project.cover.alt} decoding="async" className="h-full w-full object-cover" />
        ) : (
          <ProjectArtwork />
        )}
      </div>

      <div className="mt-14 grid gap-12 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div className="space-y-12">
          <TextSection title={t.projectDetail.overview} text={project.description} />
          {project.problem && <TextSection title={t.projectDetail.problem} text={project.problem} />}
          {project.solution && <TextSection title={t.projectDetail.solution} text={project.solution} />}
        </div>

        <aside aria-label={t.projectDetail.details} className="glass h-fit space-y-6 rounded-3xl p-6 text-sm">
          {published && (
            <Fact label={t.projectDetail.published}>
              <time dateTime={project.published_at ?? undefined}>{published}</time>
            </Fact>
          )}
          {project.technologies.length > 0 && (
            <Fact label={t.projectDetail.technologies}>
              <ul className="flex flex-wrap gap-2">
                {project.technologies.map((tech) => (
                  <li key={tech.slug}>
                    <Link
                      to={projectsPath(locale, { technology: tech.slug })}
                      dir="auto"
                      className="inline-block rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-200 transition hover:border-cyan-300/40 hover:text-white"
                    >
                      {tech.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </Fact>
          )}
        </aside>
      </div>

      {project.images.length > 0 && (
        <section aria-labelledby="project-gallery" className="mt-16">
          <h2 id="project-gallery" className="mb-6 font-display text-2xl font-semibold text-white">
            {t.projectDetail.gallery}
          </h2>
          <ul className="grid gap-6 sm:grid-cols-2">
            {project.images.map((image) => (
              <li key={image.url} className="glass overflow-hidden rounded-2xl">
                <img
                  src={image.url}
                  alt={image.alt}
                  width={image.width ?? undefined}
                  height={image.height ?? undefined}
                  loading="lazy"
                  decoding="async"
                  className="h-auto w-full"
                />
              </li>
            ))}
          </ul>
        </section>
      )}
    </>
  )
}

/** Plain text from the API: blank lines separate paragraphs, single line breaks are kept. Never parsed as HTML. */
function TextSection({ title, text }: { title: string; text: string }) {
  const paragraphs = text
    .split(/\r?\n\s*\r?\n/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
  if (paragraphs.length === 0) return null

  return (
    <section>
      <h2 className="mb-4 font-display text-2xl font-semibold text-white">{title}</h2>
      <div className="space-y-4 leading-relaxed text-slate-300">
        {paragraphs.map((paragraph, index) => (
          <p key={index} className="whitespace-pre-line">
            {paragraph}
          </p>
        ))}
      </div>
    </section>
  )
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <h2 className="mb-2 text-xs font-semibold tracking-wider text-slate-400 uppercase rtl:tracking-normal">{label}</h2>
      {children}
    </div>
  )
}

function ExternalLink({ href, primary = false, children }: { href: string; primary?: boolean; children: string }) {
  const { t } = useI18n()
  const className = primary
    ? 'inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-violet-500 to-cyan-400 px-6 py-3 font-semibold text-ink-950 shadow-[0_10px_40px_-10px] shadow-violet-500/70 transition hover:shadow-cyan-400/60'
    : 'glass inline-flex items-center gap-2 rounded-full px-6 py-3 font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/10'

  return (
    <a href={href} target="_blank" rel="noopener noreferrer" aria-label={`${children} (${t.projectDetail.newTab})`} className={className}>
      {children}
      <span aria-hidden="true">↗</span>
    </a>
  )
}
