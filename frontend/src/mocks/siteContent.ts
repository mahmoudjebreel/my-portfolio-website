/**
 * PREVIEW DATA — clearly marked placeholders, used only until the Laravel API is connected.
 * Nothing here is final personal content and nothing here is stored anywhere.
 *  - Name/title are placeholders ("Your Name").
 *  - English texts follow the approved brief; Arabic texts are DRAFT proposals awaiting review.
 *  - Contact/social links are intentionally null, so the UI hides them (same rule as missing settings).
 */
import type { Locale } from '../i18n/config'

type Translated = Record<Locale, string>

export interface SiteContent {
  isPlaceholder: true
  displayName: Translated
  initials: string
  heroEyebrow: Translated
  heroHeadline: Translated
  heroDescription: Translated
  aboutText: Translated
  contactEmail: string | null
  githubUrl: string | null
  linkedinUrl: string | null
}

export const mockSiteContent: SiteContent = {
  isPlaceholder: true,
  displayName: { en: 'Your Name', ar: 'اسمك' },
  initials: 'YN',
  heroEyebrow: {
    en: 'SOFTWARE DEVELOPER | FRONT-END FOCUSED',
    ar: 'مطوّر برمجيات | تركيز على الواجهات الأمامية',
  },
  heroHeadline: {
    en: 'Building Digital Experiences That Feel Different.',
    ar: 'أبني تجارب رقمية بإحساس مختلف.',
  },
  heroDescription: {
    en: 'I design and build modern, responsive and interactive web interfaces — backed by practical back-end and database experience that turns them into complete, reliable products.',
    ar: 'أصمّم وأبني واجهات ويب حديثة ومتجاوبة وتفاعلية، مدعومة بخبرة عملية في تقنيات الخادم وقواعد البيانات تحوّلها إلى منتجات متكاملة وموثوقة.',
  },
  aboutText: {
    en: "I'm a software developer focused on building modern, responsive, and interactive web experiences. My primary interest is front-end development, complemented by practical experience with back-end technologies and relational databases. I enjoy connecting user interfaces with reliable application logic to build useful digital products.",
    ar: 'أنا مطوّر برمجيات أركّز على بناء تجارب ويب حديثة ومتجاوبة وتفاعلية. اهتمامي الأساسي هو تطوير الواجهات الأمامية، وتدعمه خبرة عملية في تقنيات الخادم وقواعد البيانات العلائقية. أستمتع بربط واجهات المستخدم بمنطق تطبيقات موثوق لبناء منتجات رقمية مفيدة.',
  },
  contactEmail: null,
  githubUrl: null,
  linkedinUrl: null,
}
