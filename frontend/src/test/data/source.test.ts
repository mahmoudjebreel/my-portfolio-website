import { describe, expect, it, vi } from 'vitest'
import { isMockDataSource, resolveDataSource } from '../../data/source'
import { DataSourceConfigError, parseDataSource } from '../../data/sourceConfig'

describe('parseDataSource (shared by vite.config.ts and the app)', () => {
  it('defaults to the API', () => {
    for (const value of [undefined, '', '  ', 'api', ' API ']) {
      expect(parseDataSource(value, { production: false })).toBe('api')
      expect(parseDataSource(value, { production: true })).toBe('api')
    }
  })

  it('allows mock outside production builds only', () => {
    expect(parseDataSource('mock', { production: false })).toBe('mock')
    expect(() => parseDataSource('mock', { production: true })).toThrow(DataSourceConfigError)
  })

  it('rejects unknown values instead of guessing', () => {
    expect(() => parseDataSource('fixtures', { production: false })).toThrow(/Invalid VITE_DATA_SOURCE/)
  })
})

describe('resolveDataSource (runtime)', () => {
  it('uses mock only in development', () => {
    expect(resolveDataSource({ value: 'mock', dev: true })).toBe('mock')
    expect(resolveDataSource({ value: 'mock', dev: false })).toBe('api')
    expect(resolveDataSource({ value: undefined, dev: true })).toBe('api')
  })

  it('reads VITE_DATA_SOURCE from the environment', () => {
    expect(isMockDataSource()).toBe(false)
    vi.stubEnv('VITE_DATA_SOURCE', 'mock')
    expect(isMockDataSource()).toBe(true)
  })
})
