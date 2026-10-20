import { DataError, LoadingLabel, Skeleton } from '../components/ui/DataStates'
import { Reveal } from '../components/ui/Reveal'
import { SectionHeading } from '../components/ui/SectionHeading'
import { useSiteSettingsContext } from '../data/SiteSettingsContext'
import { useI18n } from '../i18n/I18nProvider'

export function About() {
  const { t } = useI18n()
  const { status, data, error, retry } = useSiteSettingsContext()

  return (
    <section id="about" aria-labelledby="about-title" className="relative py-28">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading id="about-title" eyebrow={t.about.eyebrow} title={t.about.title} />
        <div className="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
          <div>
            {status === 'loading' && (
              <div className="space-y-3" data-testid="about-loading">
                <LoadingLabel />
                <Skeleton className="h-5 w-full" />
                <Skeleton className="h-5 w-11/12" />
                <Skeleton className="h-5 w-4/5" />
              </div>
            )}
            {status === 'error' && <DataError error={error} onRetry={retry} />}
            {data?.about_text && (
              <Reveal>
                <p className="text-lg leading-relaxed text-slate-300 sm:text-xl sm:leading-relaxed">{data.about_text}</p>
              </Reveal>
            )}
          </div>
          <Reveal delay={0.1} className="grid gap-4">
            <div className="glass rounded-2xl p-6">
              <p className="text-sm text-slate-400">{t.about.focusTitle}</p>
              <p className="mt-2 font-display text-xl font-semibold text-white">{t.about.focus}</p>
              <div className="mt-4 h-1 w-16 rounded-full bg-gradient-to-r from-violet-400 to-cyan-300" aria-hidden="true" />
            </div>
            <div className="glass rounded-2xl p-6">
              <p className="text-sm text-slate-400">{t.about.alsoTitle}</p>
              <p className="mt-2 font-display text-xl font-semibold text-white">{t.about.also}</p>
            </div>
          </Reveal>
        </div>
      </div>
    </section>
  )
}
