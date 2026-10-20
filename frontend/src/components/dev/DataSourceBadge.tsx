import { isMockDataSource } from '../../data/source'

/**
 * Visible warning while the app runs on mock data (VITE_DATA_SOURCE=mock, development only).
 * Renders nothing with the API data source, and nothing at all in production builds.
 */
export function DataSourceBadge() {
  if (!import.meta.env.DEV || !isMockDataSource()) return null

  return (
    <p
      role="status"
      data-testid="mock-data-badge"
      lang="en"
      dir="ltr"
      className="fixed start-4 bottom-4 z-[70] rounded-full border border-fuchsia-300/50 bg-fuchsia-600/25 px-3 py-1.5 text-xs font-semibold text-fuchsia-100 shadow-lg backdrop-blur"
    >
      Mock data · development only
    </p>
  )
}
