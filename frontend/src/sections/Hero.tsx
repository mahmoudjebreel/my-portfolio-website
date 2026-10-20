import { motion } from 'motion/react'
import { HeroVisual } from '../components/three/HeroVisual'
import { DataError, LoadingLabel, Skeleton } from '../components/ui/DataStates'
import { SocialLinks } from '../components/ui/SocialLinks'
import { useSiteSettingsContext } from '../data/SiteSettingsContext'
import { useI18n } from '../i18n/I18nProvider'

const enter = (delay: number) => ({
  initial: { opacity: 0, y: 18 },
  animate: { opacity: 1, y: 0 },
  transition: { duration: 0.7, delay, ease: [0.22, 1, 0.36, 1] as const },
})

export function Hero() {
  const { t } = useI18n()
  const { status, data, error, retry } = useSiteSettingsContext()
  const headline = data?.hero_headline ?? null

  return (
    <section id="top" aria-labelledby="hero-title" className="relative isolate flex min-h-[100svh] items-center overflow-hidden pt-16">
      {/* Background layers */}
      <div className="grid-backdrop absolute inset-0 -z-20" aria-hidden="true" />
      <div
        className="absolute inset-0 -z-20 bg-[radial-gradient(ellipse_at_top,rgba(124,58,237,0.22),transparent_60%),radial-gradient(ellipse_at_bottom_right,rgba(34,211,238,0.12),transparent_55%)]"
        aria-hidden="true"
      />

      {/* 3D scene: full-bleed behind the text on mobile, on the end side on desktop */}
      <div className="absolute inset-0 -z-10 opacity-40 lg:start-[42%] lg:opacity-100">
        <HeroVisual />
      </div>

      <div className="mx-auto w-full max-w-6xl px-5 py-20 sm:px-8">
        <div className="max-w-2xl">
          {status === 'loading' && (
            <div className="space-y-5" data-testid="hero-loading">
              <LoadingLabel />
              <Skeleton className="h-4 w-64" />
              <Skeleton className="h-14 w-full sm:h-16" />
              <Skeleton className="h-14 w-4/5 sm:h-16" />
              <Skeleton className="h-5 w-full max-w-xl" />
            </div>
          )}

          {status === 'error' && <DataError error={error} onRetry={retry} className="mb-8" />}

          {data?.hero_eyebrow && (
            <motion.p {...enter(0.05)} className="eyebrow mb-6 inline-flex items-center gap-3">
              <span className="h-px w-8 bg-gradient-to-r from-violet-400 to-cyan-300" aria-hidden="true" />
              {data.hero_eyebrow}
            </motion.p>
          )}

          {headline ? (
            <motion.h1
              {...enter(0.15)}
              id="hero-title"
              className="font-display text-4xl leading-[1.08] font-bold tracking-tight text-white sm:text-6xl lg:text-7xl"
            >
              <span className="text-gradient">{headline}</span>
            </motion.h1>
          ) : (
            // Every page keeps one h1 for assistive technology, even before a headline is configured.
            <h1 id="hero-title" className="sr-only">
              {data?.display_name ?? t.hero.fallbackTitle}
            </h1>
          )}

          {data?.hero_description && (
            <motion.p {...enter(0.25)} className="mt-6 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
              {data.hero_description}
            </motion.p>
          )}

          <motion.div {...enter(0.35)} className="mt-10 flex flex-wrap items-center gap-4">
            <a
              href="#projects"
              className="group relative inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-violet-500 to-cyan-400 px-7 py-3.5 font-semibold text-ink-950 shadow-[0_10px_40px_-10px] shadow-violet-500/70 transition hover:shadow-cyan-400/60"
            >
              {t.hero.explore}
              <span aria-hidden="true" className="transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5">
                →
              </span>
            </a>
            <a
              href="#contact"
              className="glass inline-flex items-center rounded-full px-7 py-3.5 font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/10"
            >
              {t.hero.contact}
            </a>
            <SocialLinks />
          </motion.div>
        </div>
      </div>

      <a
        href="#about"
        className="absolute bottom-8 left-1/2 hidden -translate-x-1/2 flex-col items-center gap-2 text-xs tracking-widest text-slate-400 uppercase transition hover:text-white sm:flex"
      >
        {t.hero.scroll}
        <span className="h-10 w-px bg-gradient-to-b from-cyan-300 to-transparent motion-safe:animate-pulse" aria-hidden="true" />
      </a>
    </section>
  )
}
