import { act, render } from '@testing-library/react'
import { RouterProvider, createMemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { HASH_SETTLE_MS, useSettleHashScroll } from '../lib/useSettleHashScroll'

/*
 * jsdom has no layout: positions are stubbed. The section "lands" 88px from the top
 * (its scroll-margin); `top` simulates where it is after content above it changed.
 */
let top = 88
let pageHeight = 10_000
let scrollY = 4000

function Page() {
  useSettleHashScroll()
  return (
    <main id="main">
      <section id="projects" data-testid="projects" />
      <section id="approach" style={{ scrollMarginTop: '88px' }} />
    </main>
  )
}

function setup(initialEntry = '/en') {
  const router = createMemoryRouter([{ path: '/:locale', element: <Page /> }], { initialEntries: [initialEntry] })
  render(<RouterProvider router={router} />)
  const section = document.getElementById('approach')!
  vi.spyOn(section, 'getBoundingClientRect').mockImplementation(() => ({ top }) as DOMRect)
  const scrollIntoView = vi.spyOn(section, 'scrollIntoView').mockImplementation(() => {})
  return { router, scrollIntoView }
}

/** A section above the target changes (e.g. data arrived), which moves the target. */
async function contentChangesAbove(newTop: number) {
  top = newTop
  await act(async () => {
    document.getElementById('projects')!.append(document.createElement('article'))
  })
}

beforeEach(() => {
  top = 88
  pageHeight = 10_000
  scrollY = 4000
  Object.defineProperty(document.documentElement, 'scrollHeight', { configurable: true, get: () => pageHeight })
  Object.defineProperty(window, 'scrollY', { configurable: true, get: () => scrollY })
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('useSettleHashScroll', () => {
  it('puts the target back in place, without animation, when content above it grows', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    await contentChangesAbove(315)
    expect(scrollIntoView).toHaveBeenCalledTimes(1)
    expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'instant' })
  })

  it('does nothing while the target stays where it landed', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    await contentChangesAbove(88)
    await contentChangesAbove(88.5)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('retargets a smooth scroll that is still running instead of jumping', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    window.dispatchEvent(new Event('scroll'))
    await contentChangesAbove(315)
    expect(scrollIntoView).toHaveBeenCalledWith()
  })

  it('stops as soon as the user scrolls, touches or uses the keyboard', async () => {
    for (const input of ['wheel', 'touchstart', 'keydown', 'pointerdown']) {
      const { router, scrollIntoView } = setup()
      await act(() => router.navigate('/en#approach'))

      window.dispatchEvent(new Event(input))
      await contentChangesAbove(315)
      expect(scrollIntoView, input).not.toHaveBeenCalled()
      document.body.innerHTML = ''
    }
  })

  it('stops after the time limit', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    const now = performance.now()
    vi.spyOn(performance, 'now').mockReturnValue(now + HASH_SETTLE_MS + 1)
    await contentChangesAbove(315)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('never fights back/forward: a POP navigation keeps the restored position', async () => {
    const { router, scrollIntoView } = setup('/en#approach') // initial entry = POP
    await contentChangesAbove(315)
    expect(scrollIntoView).not.toHaveBeenCalled()

    await act(() => router.navigate('/en'))
    await act(() => router.navigate(-1)) // back to /en#approach (POP)
    await contentChangesAbove(400)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('stops at the next navigation', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))
    await act(() => router.navigate('/en'))

    await contentChangesAbove(315)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('does not retry when the page cannot scroll any further (end of the page)', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    pageHeight = scrollY + window.innerHeight // already at the bottom
    await contentChangesAbove(315)
    await contentChangesAbove(320)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('corrects at most once per change, so it cannot loop', async () => {
    const { router, scrollIntoView } = setup()
    await act(() => router.navigate('/en#approach'))

    await contentChangesAbove(315)
    await act(async () => {}) // the correction itself changes nothing in <main>
    expect(scrollIntoView).toHaveBeenCalledTimes(1)
  })
})
