/**
 * The technologies the developer actually uses (from the project brief) — real data,
 * kept here only until the /api/technologies endpoint exists. No proficiency levels by design.
 */
export type TechnologyGroup = 'frontend' | 'backend' | 'database' | 'tools'

export interface Technology {
  slug: string
  name: string
  group: TechnologyGroup
  /** Short monogram shown in the badge until brand icons are added. */
  mark: string
}

export const technologies: Technology[] = [
  { slug: 'html5', name: 'HTML5', group: 'frontend', mark: 'H5' },
  { slug: 'css3', name: 'CSS3', group: 'frontend', mark: 'C3' },
  { slug: 'javascript', name: 'JavaScript', group: 'frontend', mark: 'JS' },
  { slug: 'vue-js', name: 'Vue.js', group: 'frontend', mark: 'Vu' },
  { slug: 'tailwind-css', name: 'Tailwind CSS', group: 'frontend', mark: 'Tw' },
  { slug: 'react', name: 'React', group: 'frontend', mark: 'Re' },
  { slug: 'php', name: 'PHP', group: 'backend', mark: 'Ph' },
  { slug: 'laravel', name: 'Laravel', group: 'backend', mark: 'La' },
  { slug: 'mysql', name: 'MySQL', group: 'database', mark: 'My' },
]
