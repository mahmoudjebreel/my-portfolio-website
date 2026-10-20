import { useEffect } from 'react'

/**
 * Sets the document title and meta description while the calling page is mounted, and
 * restores the previous values afterwards. Null values leave the current ones untouched.
 */
export function useDocumentMeta(title: string | null, description: string | null = null): void {
  useEffect(() => {
    if (!title) return
    const previous = document.title
    document.title = title
    return () => {
      document.title = previous
    }
  }, [title])

  useEffect(() => {
    if (!description) return
    const meta = document.querySelector<HTMLMetaElement>('meta[name="description"]')
    if (!meta) return
    const previous = meta.content
    meta.content = description
    return () => {
      meta.content = previous
    }
  }, [description])
}
