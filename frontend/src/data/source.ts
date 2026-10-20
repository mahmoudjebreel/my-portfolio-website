import { parseDataSource, type DataSource } from './sourceConfig'

export type { DataSource } from './sourceConfig'

/**
 * Which data source the app uses.
 *  - default: "api"
 *  - "mock": development only (`import.meta.env.DEV`); a production bundle always uses the API.
 * The production build itself refuses VITE_DATA_SOURCE=mock (see vite.config.ts).
 */
export function resolveDataSource(
  env: { value: string | undefined; dev: boolean } = { value: import.meta.env.VITE_DATA_SOURCE, dev: import.meta.env.DEV },
): DataSource {
  if (!env.dev) return 'api'
  return parseDataSource(env.value, { production: false })
}

export const isMockDataSource = (): boolean => import.meta.env.DEV && resolveDataSource() === 'mock'
