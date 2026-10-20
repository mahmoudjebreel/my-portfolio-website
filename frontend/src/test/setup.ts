import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

afterEach(() => {
  cleanup()
  document.cookie = 'locale=; path=/; max-age=0'
})

// jsdom lacks these browser APIs.
if (!window.matchMedia) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
}

class NoopIntersectionObserver {
  observe = vi.fn()
  unobserve = vi.fn()
  disconnect = vi.fn()
  takeRecords = vi.fn(() => [])
  root = null
  rootMargin = ''
  thresholds = []
}
if (!('IntersectionObserver' in window)) {
  Object.defineProperty(window, 'IntersectionObserver', { writable: true, value: NoopIntersectionObserver })
  Object.defineProperty(globalThis, 'IntersectionObserver', { writable: true, value: NoopIntersectionObserver })
}

window.scrollTo = vi.fn() as unknown as typeof window.scrollTo
// Used by <ScrollRestoration /> to reach `#section` targets.
Element.prototype.scrollIntoView = vi.fn()

// jsdom has no WebGL: behave like a browser without WebGL support (exercises the fallback).
HTMLCanvasElement.prototype.getContext = vi.fn(() => null) as unknown as typeof HTMLCanvasElement.prototype.getContext
