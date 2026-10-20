import { useSiteSettingsContext } from '../../data/SiteSettingsContext'

const ICONS = {
  github:
    'M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.89 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02a9.6 9.6 0 0 1 5 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.93.36.31.68.92.68 1.85v2.75c0 .27.18.58.69.48A10 10 0 0 0 12 2Z',
  linkedin:
    'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.34-.03-3.06-1.86-3.06-1.87 0-2.16 1.46-2.16 2.96V21h-4V9Z',
  email: 'M3 6h18v12H3V6Zm1.5 1.5v.3L12 13l7.5-5.2v-.3h-15Zm15 2.1L12 14.8 4.5 9.6v6.9h15V9.6Z',
}

/** Renders only the links that are actually configured in the site settings — never placeholders. */
export function SocialLinks() {
  const contact = useSiteSettingsContext().data?.contact

  const links = [
    contact?.github_url && { href: contact.github_url, label: 'GitHub', path: ICONS.github },
    contact?.linkedin_url && { href: contact.linkedin_url, label: 'LinkedIn', path: ICONS.linkedin },
    contact?.email && { href: `mailto:${contact.email}`, label: 'Email', path: ICONS.email },
  ].filter((link): link is { href: string; label: string; path: string } => Boolean(link))

  if (links.length === 0) return null

  return (
    <ul className="flex items-center gap-2">
      {links.map((link) => (
        <li key={link.label}>
          <a
            href={link.href}
            target={link.href.startsWith('mailto:') ? undefined : '_blank'}
            rel="noopener noreferrer"
            aria-label={link.label}
            className="glass grid size-10 place-items-center rounded-full text-slate-300 transition hover:text-white"
          >
            <svg viewBox="0 0 24 24" className="size-4" fill="currentColor" aria-hidden="true">
              <path d={link.path} />
            </svg>
          </a>
        </li>
      ))}
    </ul>
  )
}
