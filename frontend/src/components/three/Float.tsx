import { useFrame } from '@react-three/fiber'
import { useRef, type ReactNode } from 'react'
import type { Group } from 'three'

interface FloatProps {
  children: ReactNode
  /** Animation speed; 0 freezes the object (reduced motion). */
  speed?: number
  rotationIntensity?: number
  floatIntensity?: number
  /** Phase offset so sibling objects don't move in sync. */
  phase?: number
}

/** Minimal floating/bobbing wrapper (small replacement for drei's <Float>). */
export function Float({ children, speed = 1, rotationIntensity = 1, floatIntensity = 1, phase = 0 }: FloatProps) {
  const group = useRef<Group>(null)

  useFrame((state) => {
    if (!group.current || speed === 0) return
    const t = phase + state.clock.elapsedTime * speed
    group.current.rotation.x = (Math.cos(t / 4) / 8) * rotationIntensity
    group.current.rotation.y = (Math.sin(t / 4) / 8) * rotationIntensity
    group.current.rotation.z = (Math.sin(t / 4) / 20) * rotationIntensity
    group.current.position.y = (Math.sin(t) / 10) * floatIntensity
  })

  return <group ref={group}>{children}</group>
}
