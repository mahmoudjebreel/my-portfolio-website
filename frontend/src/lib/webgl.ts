/** True when the browser can create a WebGL context (WebGL2 preferred). */
export function supportsWebGL(): boolean {
  try {
    const canvas = document.createElement('canvas')
    return Boolean(canvas.getContext('webgl2') ?? canvas.getContext('webgl'))
  } catch {
    return false
  }
}

/** Rough device tier so weaker devices get a lighter scene. */
export function isLowPowerDevice(): boolean {
  const nav = navigator as Navigator & { deviceMemory?: number }
  const cores = nav.hardwareConcurrency ?? 8
  const memory = nav.deviceMemory ?? 8
  const coarse = window.matchMedia?.('(pointer: coarse)').matches ?? false
  return cores <= 4 || memory <= 4 || (coarse && window.innerWidth < 768)
}
