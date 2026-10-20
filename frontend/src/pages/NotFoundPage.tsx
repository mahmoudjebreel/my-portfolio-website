import { Link } from 'react-router'
import { useI18n } from '../i18n/I18nProvider'

export function NotFoundPage() {
  const { locale, t } = useI18n()
  return (
    <section aria-labelledby="not-found-title" className="grid min-h-[100svh] place-items-center px-5 text-center">
      <div>
        <p className="font-display text-7xl font-bold text-gradient">404</p>
        <h1 id="not-found-title" className="mt-4 font-display text-2xl font-semibold text-white">
          {t.notFound.title}
        </h1>
        <p className="mt-2 text-slate-400">{t.notFound.text}</p>
        <Link to={`/${locale}`} className="glass mt-8 inline-flex rounded-full px-6 py-3 font-medium text-white hover:bg-white/10">
          {t.notFound.home}
        </Link>
      </div>
    </section>
  )
}
