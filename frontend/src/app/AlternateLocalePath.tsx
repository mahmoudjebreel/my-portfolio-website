import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'

/**
 * Lets a page tell the language switcher where "this page in the other language" is when
 * that is not simply the same path with the locale swapped: project pages use the API
 * `alternates` (and fall back to the other language's project list — decision S1), the
 * project list keeps only the language-neutral filters.
 */
interface AlternateLocalePathValue {
  path: string | null
  setPath: (path: string | null) => void
}

const AlternateLocalePathContext = createContext<AlternateLocalePathValue | null>(null)

export function AlternateLocalePathProvider({ children }: { children: ReactNode }) {
  const [path, setPath] = useState<string | null>(null)
  return <AlternateLocalePathContext value={{ path, setPath }}>{children}</AlternateLocalePathContext>
}

/** The override set by the current page, or null for the default locale swap. */
export function useAlternateLocalePath(): string | null {
  return useContext(AlternateLocalePathContext)?.path ?? null
}

/** Sets the other-language path while the calling page is mounted. */
export function useSetAlternateLocalePath(path: string | null): void {
  const setPath = useContext(AlternateLocalePathContext)?.setPath

  useEffect(() => {
    if (!setPath) return
    setPath(path)
    return () => setPath(null)
  }, [path, setPath])
}
