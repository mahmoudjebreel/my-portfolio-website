import { createContext, useContext, useEffect, type ReactNode } from 'react'
import { LOCALE_META, type Locale } from './config'
import { ar } from './dictionaries/ar'
import { en, type Dictionary } from './dictionaries/en'

const dictionaries: Record<Locale, Dictionary> = { en, ar }

interface I18nValue {
  locale: Locale
  dir: 'ltr' | 'rtl'
  t: Dictionary
}

const I18nContext = createContext<I18nValue | null>(null)

export function I18nProvider({ locale, children }: { locale: Locale; children: ReactNode }) {
  const dir = LOCALE_META[locale].dir

  useEffect(() => {
    document.documentElement.lang = locale
    document.documentElement.dir = dir
  }, [locale, dir])

  return <I18nContext value={{ locale, dir, t: dictionaries[locale] }}>{children}</I18nContext>
}

export function useI18n(): I18nValue {
  const value = useContext(I18nContext)
  if (!value) throw new Error('useI18n must be used inside <I18nProvider>')
  return value
}
