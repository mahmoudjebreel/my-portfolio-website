/**
 * PREVIEW DATA — three SAMPLE cards to review the card design only.
 * They are labelled "Sample" in the UI, have no links and no images, and are never stored.
 * Real projects will come from the API (published projects, per locale).
 */
import type { Locale } from '../i18n/config'

export interface SampleProject {
  id: string
  isSample: true
  title: Record<Locale, string>
  summary: Record<Locale, string>
  category: Record<Locale, string>
  technologies: string[]
  /** Visual accent used for the generated placeholder artwork. */
  hue: 'violet' | 'cyan' | 'fuchsia'
}

export const sampleProjects: SampleProject[] = [
  {
    id: 'sample-1',
    isSample: true,
    title: { en: 'Sample project — Web application', ar: 'مشروع تجريبي — تطبيق ويب' },
    summary: {
      en: 'Placeholder card showing how a published web application will be presented.',
      ar: 'بطاقة مؤقتة توضّح طريقة عرض تطبيق ويب منشور.',
    },
    category: { en: 'Web Applications', ar: 'تطبيقات الويب' },
    technologies: ['React', 'Laravel', 'MySQL'],
    hue: 'violet',
  },
  {
    id: 'sample-2',
    isSample: true,
    title: { en: 'Sample project — Dashboard', ar: 'مشروع تجريبي — لوحة تحكم' },
    summary: {
      en: 'Placeholder card for a dashboard or admin panel project.',
      ar: 'بطاقة مؤقتة لمشروع لوحة تحكم أو إدارة.',
    },
    category: { en: 'Dashboards & Admin Panels', ar: 'لوحات التحكم والإدارة' },
    technologies: ['Vue.js', 'Tailwind CSS', 'PHP'],
    hue: 'cyan',
  },
  {
    id: 'sample-3',
    isSample: true,
    title: { en: 'Sample project — Landing page', ar: 'مشروع تجريبي — صفحة هبوط' },
    summary: {
      en: 'Placeholder card for a landing page project.',
      ar: 'بطاقة مؤقتة لمشروع صفحة هبوط.',
    },
    category: { en: 'Landing Pages', ar: 'صفحات الهبوط' },
    technologies: ['HTML5', 'CSS3', 'JavaScript'],
    hue: 'fuchsia',
  },
]
