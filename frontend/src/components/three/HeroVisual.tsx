import { Component, Suspense, lazy, useEffect, useRef, useState, type ReactNode } from 'react'
import { isLowPowerDevice, supportsWebGL } from '../../lib/webgl'
import { usePrefersReducedMotion } from '../../lib/useMediaQuery'
import { HeroFallback } from './HeroFallback'

const HeroScene = lazy(() => import('./HeroScene'))

/**
 * Chooses between the lazy WebGL scene and a lightweight CSS fallback:
 *  - no WebGL support            → fallback
 *  - scene throws while loading  → fallback (error boundary)
 *  - before the browser is idle  → fallback (keeps first paint fast)
 *  - prefers-reduced-motion      → scene renders a static frame
 *  - hero scrolled out of view   → scene stops rendering frames
 */
export function HeroVisual() {
  const reducedMotion = usePrefersReducedMotion()
  const [webgl] = useState(supportsWebGL)
  const [lowPower] = useState(isLowPowerDevice)
  const [ready, setReady] = useState(false)
  const [active, setActive] = useState(true)
  const wrapper = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!webgl) return
    const idle = window.requestIdleCallback ?? ((cb: () => void) => window.setTimeout(cb, 250))
    const cancel = window.cancelIdleCallback ?? window.clearTimeout
    const handle = idle(() => setReady(true))
    return () => cancel(handle)
  }, [webgl])

  useEffect(() => {
    const node = wrapper.current
    if (!node || typeof IntersectionObserver === 'undefined') return
    const observer = new IntersectionObserver(([entry]) => setActive(Boolean(entry?.isIntersecting)), { threshold: 0.05 })
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  return (
    <div ref={wrapper} className="absolute inset-0" aria-hidden="true" data-testid="hero-visual">
      {webgl && ready ? (
        <SceneErrorBoundary fallback={<HeroFallback />}>
          <Suspense fallback={<HeroFallback />}>
            <HeroScene reducedMotion={reducedMotion} lowPower={lowPower} active={active} />
          </Suspense>
        </SceneErrorBoundary>
      ) : (
        <HeroFallback />
      )}
    </div>
  )
}

class SceneErrorBoundary extends Component<{ fallback: ReactNode; children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  componentDidCatch(error: unknown) {
    if (import.meta.env.DEV) console.warn('Hero 3D scene failed, showing fallback.', error)
  }

  render() {
    return this.state.failed ? this.props.fallback : this.props.children
  }
}
