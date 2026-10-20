import type { TechnologyGroup } from '../api/types'
import { DataError, EmptyState, LoadingLabel, Skeleton } from '../components/ui/DataStates'
import { Reveal } from '../components/ui/Reveal'
import { SectionHeading } from '../components/ui/SectionHeading'
import { useTechnologies } from '../data/queries'
import { useI18n } from '../i18n/I18nProvider'

const GROUPS: TechnologyGroup[] = ['frontend', 'backend', 'database', 'tools']
const ACCENT: Record<TechnologyGroup, string> = {
  frontend: 'from-violet-500/25 to-fuchsia-500/10 text-violet-200',
  backend: 'from-cyan-500/25 to-sky-500/10 text-cyan-200',
  database: 'from-sky-500/25 to-indigo-500/10 text-sky-200',
  tools: 'from-slate-500/25 to-slate-500/10 text-slate-200',
}

/** Two-character badge derived from the technology name (no brand icons yet). */
function mark(name: string): string {
  const compact = name.replace(/[^\p{L}\p{N}]+/gu, ' ').trim()
  const words = compact.split(' ').filter(Boolean)
  if (words.length > 1) return (Array.from(words[0]!)[0]! + Array.from(words[1]!)[0]!).toUpperCase()
  const chars = Array.from(compact)
  const digit = chars.slice(1).find((char) => /\p{N}/u.test(char))
  return digit ? `${chars[0]!.toUpperCase()}${digit}` : `${chars[0]?.toUpperCase() ?? ''}${chars[1] ?? ''}`
}

export function Skills() {
  const { locale, t } = useI18n()
  const { status, data, error, retry } = useTechnologies(locale)
  const groups = GROUPS.map((group) => ({ group, items: (data ?? []).filter((tech) => tech.group === group) })).filter(
    ({ items }) => items.length > 0,
  )

  return (
    <section id="skills" aria-labelledby="skills-title" className="relative py-28">
      <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" aria-hidden="true" />
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading id="skills-title" eyebrow={t.skills.eyebrow} title={t.skills.title} />

        {status === 'loading' && (
          <div className="grid gap-6 md:grid-cols-3" data-testid="skills-loading">
            <LoadingLabel />
            {[0, 1, 2].map((i) => (
              <div key={i} className="glass space-y-4 rounded-3xl p-6">
                <Skeleton className="h-5 w-28" />
                <div className="flex flex-wrap gap-3">
                  <Skeleton className="h-12 w-28 rounded-2xl" />
                  <Skeleton className="h-12 w-24 rounded-2xl" />
                  <Skeleton className="h-12 w-32 rounded-2xl" />
                </div>
              </div>
            ))}
          </div>
        )}

        {status === 'error' && <DataError error={error} onRetry={retry} />}

        {status === 'success' && groups.length === 0 && <EmptyState>{t.skills.empty}</EmptyState>}

        {groups.length > 0 && (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {groups.map(({ group, items }, index) => (
              <Reveal key={group} delay={index * 0.08}>
                <div className="glass h-full rounded-3xl p-6">
                  <h3 className="mb-5 font-display text-lg font-semibold text-white">{t.skills.groups[group]}</h3>
                  <ul className="flex flex-wrap gap-3">
                    {items.map((tech) => (
                      <li
                        key={tech.slug}
                        className="group flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] py-2 ps-2 pe-4 transition hover:-translate-y-0.5 hover:border-white/20 hover:bg-white/[0.07]"
                      >
                        <span
                          className={`grid size-9 place-items-center rounded-xl bg-gradient-to-br font-display text-xs font-bold ${ACCENT[group]}`}
                          aria-hidden="true"
                        >
                          {mark(tech.name)}
                        </span>
                        <span dir="auto" className="text-sm font-medium text-slate-100">
                          {tech.name}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              </Reveal>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}
