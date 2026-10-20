import type { ApiError } from '../../api/client'
import { useI18n } from '../../i18n/I18nProvider'

/** Placeholder block while data loads. Static (no pulse) for reduced motion. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <span aria-hidden="true" className={`block rounded-lg bg-white/[0.07] motion-safe:animate-pulse ${className}`} />
}

/** Screen-reader announcement for a loading region (the skeletons themselves are hidden). */
export function LoadingLabel() {
  const { t } = useI18n()
  return (
    <span role="status" className="sr-only">
      {t.data.loading}
    </span>
  )
}

/** Error message with a retry button. Never falls back to sample data. */
export function DataError({ error, onRetry, className = '' }: { error: ApiError | undefined; onRetry: () => void; className?: string }) {
  const { t } = useI18n()
  // 502/503/504 come from a proxy or gateway that could not reach the API server.
  const unreachable =
    error?.kind === 'network' || error?.kind === 'timeout' || (error?.kind === 'http' && [502, 503, 504].includes(error.status ?? 0))
  const message = unreachable ? t.data.unreachable : error?.kind === 'rate_limited' ? t.data.rateLimited : t.data.failed

  return (
    <div role="alert" className={`glass flex flex-wrap items-center gap-4 rounded-2xl border-rose-300/25 px-5 py-4 text-sm text-rose-100 ${className}`}>
      <p className="flex-1">{message}</p>
      <button
        type="button"
        onClick={onRetry}
        className="rounded-full border border-white/15 bg-white/5 px-4 py-2 font-medium text-white transition hover:border-cyan-300/40 hover:bg-white/10"
      >
        {t.data.retry}
      </button>
    </div>
  )
}

/** Honest empty state: nothing published yet. */
export function EmptyState({ children }: { children: string }) {
  return <p className="glass rounded-2xl px-6 py-8 text-center text-slate-400">{children}</p>
}
