import type { Locale } from '../i18n/config'

/**
 * Long date in the interface language, always with Latin digits (1, 2, 3) and the
 * Gregorian calendar in both languages. API dates are UTC; they are shown as UTC dates.
 * Returns null for a missing or invalid date (nothing is invented).
 */
export function formatDate(iso: string | null, locale: Locale): string | null {
  if (!iso) return null
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return null

  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    calendar: 'gregory',
    numberingSystem: 'latn',
    timeZone: 'UTC',
  }).format(date)
}
