/**
 * Pure validation of VITE_DATA_SOURCE, shared by vite.config.ts (build/dev time)
 * and the app (runtime). No browser or Vite APIs here.
 */
export type DataSource = 'api' | 'mock'

export class DataSourceConfigError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'DataSourceConfigError'
  }
}

export function parseDataSource(raw: string | undefined, { production }: { production: boolean }): DataSource {
  const value = (raw ?? '').trim().toLowerCase()

  if (value === '' || value === 'api') return 'api'

  if (value === 'mock') {
    if (production) {
      throw new DataSourceConfigError(
        'VITE_DATA_SOURCE=mock is not allowed in production builds: sample data must never ship as real content.',
      )
    }
    return 'mock'
  }

  throw new DataSourceConfigError(`Invalid VITE_DATA_SOURCE "${raw}". Use "api" (default) or "mock" (development only).`)
}
