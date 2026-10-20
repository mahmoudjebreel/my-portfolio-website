import { Link, useLocation } from 'react-router'
import { useAlternateLocalePath } from '../../app/AlternateLocalePath'
import { LOCALE_META, otherLocale, rememberLocale } from '../../i18n/config'
import { useI18n } from '../../i18n/I18nProvider'

/**
 * Links to the same page in the other language. By default the locale segment is swapped;
 * a page can override the target (see AlternateLocalePath): a project page links to its
 * translation from the API `alternates`, or to the other language's project list when
 * there is none (decision S1).
 */
export function LanguageSwitcher({ className = '' }: { className?: string }) {
  const { locale, t } = useI18n()
  const { pathname, hash } = useLocation()
  const override = useAlternateLocalePath()
  const target = otherLocale(locale)
  const rest = pathname.replace(/^\/(en|ar)(?=\/|$)/, '')
  const meta = LOCALE_META[target]

  return (
    <Link
      to={override ?? `/${target}${rest}${hash}`}
      onClick={() => rememberLocale(target)}
      hrefLang={target}
      lang={target}
      aria-label={`${t.language.switchTo} ${meta.label}`}
      className={`glass inline-flex h-10 min-w-10 items-center justify-center gap-2 rounded-full px-3 text-sm font-medium text-white transition hover:border-cyan-300/40 hover:bg-white/10 ${className}`}
    >
      <svg viewBox="0 0 24 24" className="size-4 text-cyan-300" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z" />
      </svg>
      <span>{meta.label}</span>
    </Link>
  )
}
