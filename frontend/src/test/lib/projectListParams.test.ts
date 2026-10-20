import { describe, expect, it } from 'vitest'
import { pageItems } from '../../components/projects/Pagination'
import { formatDate } from '../../lib/formatDate'
import { isProjectSlug, parseProjectListParams, projectListSearch, projectPath, projectsPath } from '../../lib/projectListParams'

const parse = (search: string) => parseProjectListParams(new URLSearchParams(search))

describe('parseProjectListParams', () => {
  it('reads valid values', () => {
    expect(parse('?category=web-apps&technology=react&page=3')).toEqual({ page: 3, category: 'web-apps', technology: 'react' })
  })

  it('defaults to page 1 without filters', () => {
    expect(parse('')).toEqual({ page: 1, category: null, technology: null })
  })

  it('drops values the API would reject anyway', () => {
    expect(parse('?page=abc&category=Bad_Slug&technology=-x')).toEqual({ page: 1, category: null, technology: null })
    expect(parse('?page=0')).toMatchObject({ page: 1 })
    expect(parse('?page=-2')).toMatchObject({ page: 1 })
    expect(parse('?page=2.5')).toMatchObject({ page: 1 })
    expect(parse('?page=100001')).toMatchObject({ page: 1 })
    expect(parse(`?category=${'a'.repeat(101)}`)).toMatchObject({ category: null })
    expect(parse(`?technology=${'a'.repeat(81)}`)).toMatchObject({ technology: null })
    expect(parse('?category=%D9%88%D9%8A%D8%A8')).toMatchObject({ category: null }) // non-ASCII
  })
})

describe('projectListSearch / paths', () => {
  it('builds a canonical query: fixed order, page 1 and empty values omitted', () => {
    expect(projectListSearch({ page: 2, technology: 'react', category: 'web' })).toBe('?category=web&technology=react&page=2')
    expect(projectListSearch({ page: 1, category: null, technology: null })).toBe('')
    expect(projectListSearch()).toBe('')
  })

  it('builds locale paths', () => {
    expect(projectsPath('ar', { technology: 'laravel' })).toBe('/ar/projects?technology=laravel')
    expect(projectPath('en', 'my-project')).toBe('/en/projects/my-project')
  })

  it('validates project slugs like the API route', () => {
    expect(isProjectSlug('mawqi-shakhsi')).toBe(true)
    expect(isProjectSlug('Not_A_Slug')).toBe(false)
    expect(isProjectSlug(undefined)).toBe(false)
  })
})

describe('pageItems', () => {
  it('shows every page when there are few', () => {
    expect(pageItems(1, 3)).toEqual([1, 2, 3])
  })

  it('collapses distant pages into gaps', () => {
    expect(pageItems(5, 10)).toEqual([1, 'gap', 4, 5, 6, 'gap', 10])
    expect(pageItems(1, 10)).toEqual([1, 2, 'gap', 10])
    expect(pageItems(10, 10)).toEqual([1, 'gap', 9, 10])
  })
})

describe('formatDate', () => {
  it('uses Latin digits and the Gregorian calendar in both languages', () => {
    expect(formatDate('2026-10-20T10:00:00Z', 'en')).toBe('October 20, 2026')
    const ar = formatDate('2026-10-20T10:00:00Z', 'ar')!
    expect(ar).toContain('20')
    expect(ar).toContain('2026')
    expect(ar).not.toMatch(/[٠-٩]/)
  })

  it('returns null for missing or invalid dates', () => {
    expect(formatDate(null, 'en')).toBeNull()
    expect(formatDate('not a date', 'en')).toBeNull()
  })
})
