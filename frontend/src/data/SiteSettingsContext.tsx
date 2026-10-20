import { createContext, useContext, type ReactNode } from 'react'
import type { SiteSettings } from '../api/types'
import { useI18n } from '../i18n/I18nProvider'
import { useSiteSettings } from './queries'
import type { QueryResult } from './useApiQuery'

const SiteSettingsContext = createContext<QueryResult<SiteSettings> | null>(null)

/** Loads the public site settings once per locale and shares them with the layout and sections. */
export function SiteSettingsProvider({ children }: { children: ReactNode }) {
  const { locale } = useI18n()
  const result = useSiteSettings(locale)
  return <SiteSettingsContext value={result}>{children}</SiteSettingsContext>
}

export function useSiteSettingsContext(): QueryResult<SiteSettings> {
  const value = useContext(SiteSettingsContext)
  if (!value) throw new Error('useSiteSettingsContext must be used inside <SiteSettingsProvider>')
  return value
}
