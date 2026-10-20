import { useEffect } from 'react'
import { useLocation, useNavigationType } from 'react-router'

/** How long after a `#section` navigation late content may still move the target back into place. */
export const HASH_SETTLE_MS = 4000

const USER_INPUT = ['wheel', 'touchstart', 'keydown', 'pointerdown'] as const

/**
 * Keeps the `#section` target of a link in place while sections above it finish loading.
 *
 * <ScrollRestoration /> scrolls to the target once, when the page renders. A section above it
 * that loads data afterwards can grow (featured project cards replacing shorter skeletons) and
 * push the target down; browser scroll anchoring does not cover this case (it anchors to the
 * growing section itself, or is suppressed while a Reveal animation changes `transform`).
 *
 * For a short time after a link navigation (PUSH/REPLACE) with a hash, every DOM change in
 * <main> re-aligns the target, before the next paint, so nothing visibly moves. It stops at the
 * first user input, on the next navigation, or after HASH_SETTLE_MS. Back/forward (POP) keep the
 * position restored by ScrollRestoration.
 */
export function useSettleHashScroll(): void {
  const { hash, key } = useLocation()
  const navigationType = useNavigationType()

  useEffect(() => {
    if (!hash || navigationType === 'POP') return

    let target: HTMLElement | null
    try {
      target = document.getElementById(decodeURIComponent(hash.slice(1)))
    } catch {
      return
    }
    const main = document.getElementById('main')
    if (!target || !main) return
    const section = target

    const deadline = performance.now() + HASH_SETTLE_MS
    let lastScrollAt = 0
    let ownScroll = false

    const onScroll = () => {
      if (ownScroll) ownScroll = false
      else lastScrollAt = performance.now()
    }

    const realign = () => {
      if (performance.now() > deadline) return stop()

      const expected = parseFloat(getComputedStyle(section).scrollMarginTop) || 0
      const offset = section.getBoundingClientRect().top - expected
      if (Math.abs(offset) <= 1) return
      // Near the end of the page the target may not be reachable: never retry what cannot move.
      const canMove =
        offset > 0 ? window.scrollY + window.innerHeight < document.documentElement.scrollHeight - 1 : window.scrollY > 0
      if (!canMove) return

      if (performance.now() - lastScrollAt < 120) {
        // A smooth scroll is still running: retarget it (keeps the CSS scroll-behavior).
        section.scrollIntoView()
      } else {
        // Already arrived: correct before the next paint, without animation.
        ownScroll = true
        section.scrollIntoView({ behavior: 'instant' })
      }
    }

    const observer = new MutationObserver(realign)
    const timer = window.setTimeout(() => stop(), HASH_SETTLE_MS)
    function stop() {
      observer.disconnect()
      window.clearTimeout(timer)
      window.removeEventListener('scroll', onScroll)
      for (const type of USER_INPUT) window.removeEventListener(type, stop, true)
    }

    observer.observe(main, { childList: true, subtree: true, characterData: true })
    window.addEventListener('scroll', onScroll, { passive: true })
    for (const type of USER_INPUT) window.addEventListener(type, stop, { capture: true, passive: true })
    return stop
  }, [hash, key, navigationType])
}
