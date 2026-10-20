import { Reveal } from '../components/ui/Reveal'
import { SectionHeading } from '../components/ui/SectionHeading'
import { useI18n } from '../i18n/I18nProvider'

export function Approach() {
  const { t } = useI18n()

  return (
    <section id="approach" aria-labelledby="approach-title" className="relative py-28">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading id="approach-title" eyebrow={t.approach.eyebrow} title={t.approach.title} />
        <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {t.approach.steps.map((step, index) => (
            <li key={step.title}>
              <Reveal delay={index * 0.08} className="h-full">
                <div className="glass relative h-full overflow-hidden rounded-3xl p-6">
                  <span className="font-display text-5xl font-bold text-white/5" aria-hidden="true">
                    0{index + 1}
                  </span>
                  <h3 className="mt-2 font-display text-lg font-semibold text-white">{step.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-400">{step.text}</p>
                  <div
                    className="absolute -end-10 -top-10 size-28 rounded-full bg-gradient-to-br from-violet-500/20 to-cyan-400/10 blur-2xl"
                    aria-hidden="true"
                  />
                </div>
              </Reveal>
            </li>
          ))}
        </ol>
      </div>
    </section>
  )
}
