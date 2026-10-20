import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { useSiteSettingsContext } from '../../data/SiteSettingsContext'
import { useI18n } from '../../i18n/I18nProvider'
import { LanguageSwitcher } from './LanguageSwitcher'

// Home page sections. Absolute links (`/en#about`) so they also work from the project pages.
const SECTIONS = ['about', 'skills', 'projects', 'approach', 'contact'] as const

/** Up to two initials from the configured display name; null when no name is set. */
function initials(name: string | null | undefined): string | null {
  const letters = (name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => Array.from(word)[0] ?? '')
    .join('')
  return letters ? letters.toLocaleUpperCase() : null
}

export function Header() {
  const { locale, t } = useI18n()
  const displayName = useSiteSettingsContext().data?.display_name ?? null
  const [scrolled, setScrolled] = useState(false)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  return (
    <header
      className={`fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,backdrop-filter] duration-300 ${
        scrolled || open ? 'border-b border-white/10 bg-ink-950/70 backdrop-blur-xl' : 'border-b border-transparent'
      }`}
    >
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
        <Link to={`/${locale}`} className="group flex items-center gap-3 rounded-lg" aria-label={displayName ?? t.nav.home}>
          <span
            aria-hidden="true"
            className="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 font-display text-sm font-bold text-ink-950 shadow-[0_0_24px_-4px] shadow-violet-500/60"
          >
            {initials(displayName) ?? '</>'}
          </span>
          <span className="hidden font-display text-sm font-semibold tracking-wide text-white sm:block">&lt;dev /&gt;</span>
        </Link>

        <nav aria-label={t.nav.label} className="hidden md:block">
          <ul className="flex items-center gap-1">
            {SECTIONS.map((id) => (
              <li key={id}>
                <Link to={`/${locale}#${id}`} className="rounded-full px-4 py-2 text-sm text-slate-300 transition hover:bg-white/5 hover:text-white">
                  {t.nav[id]}
                </Link>
              </li>
            ))}
          </ul>
        </nav>

        <div className="flex items-center gap-2">
          <LanguageSwitcher />
          <button
            type="button"
            className="glass inline-grid size-10 place-items-center rounded-full text-white md:hidden"
            aria-expanded={open}
            aria-controls="mobile-menu"
            aria-label={open ? t.nav.closeMenu : t.nav.openMenu}
            onClick={() => setOpen((value) => !value)}
          >
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
              {open ? <path d="M6 6l12 12M18 6 6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
            </svg>
          </button>
        </div>
      </div>

      {open && (
        <nav id="mobile-menu" aria-label={t.nav.label} className="border-t border-white/10 px-5 pb-6 md:hidden">
          <ul className="flex flex-col pt-3">
            {SECTIONS.map((id) => (
              <li key={id}>
                <Link
                  to={`/${locale}#${id}`}
                  onClick={() => setOpen(false)}
                  className="block rounded-xl px-3 py-3 text-base text-slate-200 transition hover:bg-white/5 hover:text-white"
                >
                  {t.nav[id]}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      )}
    </header>
  )
}
