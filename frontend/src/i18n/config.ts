export const LOCALES = ['en', 'ar'] as const
export type Locale = (typeof LOCALES)[number]

export const DEFAULT_LOCALE: Locale = 'en'
const COOKIE = 'locale'

export const LOCALE_META: Record<Locale, { dir: 'ltr' | 'rtl'; label: string; short: string }> = {
  en: { dir: 'ltr', label: 'English', short: 'EN' },
  ar: { dir: 'rtl', label: 'العربية', short: 'ع' },
}

export function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (LOCALES as readonly string[]).includes(value)
}

export function otherLocale(locale: Locale): Locale {
  return locale === 'en' ? 'ar' : 'en'
}

/** Preferred locale: saved cookie, then the browser languages, then the default. */
export function detectPreferredLocale(): Locale {
  const saved = document.cookie
    .split('; ')
    .find((part) => part.startsWith(`${COOKIE}=`))
    ?.split('=')[1]
  if (isLocale(saved)) return saved

  for (const tag of navigator.languages ?? [navigator.language]) {
    const base = tag?.toLowerCase().split('-')[0]
    if (isLocale(base)) return base
  }
  return DEFAULT_LOCALE
}

export function rememberLocale(locale: Locale): void {
  document.cookie = `${COOKIE}=${locale}; path=/; max-age=31536000; samesite=lax`
}
