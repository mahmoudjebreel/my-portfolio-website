import { useSiteSettingsContext } from '../../data/SiteSettingsContext'
import { useI18n } from '../../i18n/I18nProvider'
import { SocialLinks } from '../ui/SocialLinks'

export function Footer() {
  const { t } = useI18n()
  const settings = useSiteSettingsContext().data
  const year = new Date().getFullYear()

  return (
    <footer className="relative border-t border-white/10">
      <div className="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div className="space-y-1">
          {settings?.display_name && <p className="font-display font-semibold text-white">{settings.display_name}</p>}
          {settings?.footer_text && <p className="text-sm text-slate-300">{settings.footer_text}</p>}
          <p className="text-sm text-slate-400">
            © {year} · {t.footer.rights} {t.footer.builtWith}
          </p>
        </div>
        <div className="flex items-center gap-4">
          <SocialLinks />
          <a href="#main" className="text-sm text-slate-400 transition hover:text-white">
            {t.footer.backToTop} ↑
          </a>
        </div>
      </div>
    </footer>
  )
}
