import { Canvas, useFrame } from '@react-three/fiber'
import { useMemo, useRef, type ReactNode } from 'react'
import * as THREE from 'three'
import { seededRandom } from '../../lib/random'
import { Float } from './Float'

interface SceneProps {
  reducedMotion: boolean
  lowPower: boolean
  active: boolean
}

/**
 * Decorative hero scene: a faceted glass core inside a wireframe shell, orbiting
 * shapes and a particle field. Procedural geometry only (no model/HDR downloads).
 * Loaded lazily, so three.js never blocks the first paint.
 */
export default function HeroScene({ reducedMotion, lowPower, active }: SceneProps) {
  const animate = active && !reducedMotion

  return (
    <Canvas
      // Pixel ratio capped by device tier to keep weaker GPUs smooth.
      dpr={lowPower ? 1 : [1, 1.5]}
      frameloop={animate ? 'always' : 'demand'}
      camera={{ position: [0, 0, 7.5], fov: 42 }}
      gl={{ antialias: !lowPower, alpha: true, powerPreference: 'high-performance' }}
      style={{ background: 'transparent' }}
    >
      <ambientLight intensity={0.35} />
      <directionalLight position={[3, 4, 5]} intensity={1.1} />
      <pointLight position={[-4, 2, 3]} intensity={30} color="#8b5cf6" />
      <pointLight position={[4, -2, 2]} intensity={24} color="#22d3ee" />

      <Rig animate={animate}>
        <Core animate={animate} />
        <Orbiters animate={animate} />
        <Particles count={lowPower ? 260 : 800} animate={animate} />
      </Rig>
    </Canvas>
  )
}

/** Gentle pointer parallax for the whole scene. */
function Rig({ animate, children }: { animate: boolean; children: ReactNode }) {
  const group = useRef<THREE.Group>(null)
  useFrame((state, delta) => {
    if (!group.current || !animate) return
    const targetY = state.pointer.x * 0.35
    const targetX = -state.pointer.y * 0.2
    group.current.rotation.y = THREE.MathUtils.damp(group.current.rotation.y, targetY, 3, delta)
    group.current.rotation.x = THREE.MathUtils.damp(group.current.rotation.x, targetX, 3, delta)
  })
  return <group ref={group}>{children}</group>
}

function Core({ animate }: { animate: boolean }) {
  const core = useRef<THREE.Mesh>(null)
  const shell = useRef<THREE.Mesh>(null)
  useFrame((_, delta) => {
    if (!animate) return
    if (core.current) core.current.rotation.y += delta * 0.25
    if (shell.current) {
      shell.current.rotation.y -= delta * 0.12
      shell.current.rotation.x += delta * 0.05
    }
  })

  return (
    <Float speed={animate ? 1.4 : 0} rotationIntensity={0.4} floatIntensity={0.8}>
      <mesh ref={core}>
        <icosahedronGeometry args={[1.25, 1]} />
        <meshPhysicalMaterial
          color="#7c3aed"
          emissive="#2e1065"
          emissiveIntensity={0.55}
          metalness={0.25}
          roughness={0.12}
          clearcoat={1}
          clearcoatRoughness={0.1}
          iridescence={1}
          iridescenceIOR={1.35}
          flatShading
        />
      </mesh>
      <mesh ref={shell} scale={1.65}>
        <icosahedronGeometry args={[1.25, 1]} />
        <meshBasicMaterial color="#22d3ee" wireframe transparent opacity={0.22} />
      </mesh>
    </Float>
  )
}

function Orbiters({ animate }: { animate: boolean }) {
  const speed = animate ? 1 : 0
  return (
    <>
      <Float speed={1.8 * speed} rotationIntensity={1.2} floatIntensity={1.4} phase={1.1}>
        <mesh position={[2.6, 1.3, -0.6]} rotation={[0.6, 0.2, 0]}>
          <torusGeometry args={[0.42, 0.13, 24, 64]} />
          <meshStandardMaterial color="#22d3ee" emissive="#0e7490" emissiveIntensity={0.6} metalness={0.4} roughness={0.25} />
        </mesh>
      </Float>
      <Float speed={1.5 * speed} rotationIntensity={1.5} floatIntensity={1.2} phase={2.7}>
        <mesh position={[-2.7, -1.2, 0.2]}>
          <octahedronGeometry args={[0.5, 0]} />
          <meshStandardMaterial color="#e879f9" emissive="#86198f" emissiveIntensity={0.5} metalness={0.3} roughness={0.2} flatShading />
        </mesh>
      </Float>
      <Float speed={2.1 * speed} rotationIntensity={1} floatIntensity={1.6} phase={4.2}>
        <mesh position={[-2.1, 1.7, -1.2]} scale={0.32}>
          <torusKnotGeometry args={[0.8, 0.26, 96, 14]} />
          <meshStandardMaterial color="#a78bfa" emissive="#4c1d95" emissiveIntensity={0.5} metalness={0.5} roughness={0.2} />
        </mesh>
      </Float>
      <Float speed={1.2 * speed} rotationIntensity={0.8} floatIntensity={1} phase={5.9}>
        <mesh position={[2.2, -1.6, 0.6]} rotation={[0.4, 0.7, 0.2]}>
          <boxGeometry args={[0.55, 0.55, 0.55]} />
          <meshStandardMaterial color="#38bdf8" emissive="#075985" emissiveIntensity={0.4} metalness={0.2} roughness={0.35} wireframe />
        </mesh>
      </Float>
    </>
  )
}

function Particles({ count, animate }: { count: number; animate: boolean }) {
  const points = useRef<THREE.Points>(null)
  const positions = useMemo(() => {
    const random = seededRandom(1337)
    const data = new Float32Array(count * 3)
    for (let i = 0; i < count; i++) {
      // Points on a thick spherical shell around the core.
      const radius = 3.2 + random() * 3.5
      const theta = random() * Math.PI * 2
      const phi = Math.acos(2 * random() - 1)
      data[i * 3] = radius * Math.sin(phi) * Math.cos(theta)
      data[i * 3 + 1] = radius * Math.sin(phi) * Math.sin(theta) * 0.7
      data[i * 3 + 2] = radius * Math.cos(phi) - 1.5
    }
    return data
  }, [count])

  useFrame((_, delta) => {
    if (animate && points.current) points.current.rotation.y += delta * 0.02
  })

  return (
    <points ref={points}>
      <bufferGeometry>
        <bufferAttribute attach="attributes-position" args={[positions, 3]} />
      </bufferGeometry>
      <pointsMaterial
        size={0.03}
        color="#c4b5fd"
        transparent
        opacity={0.75}
        sizeAttenuation
        depthWrite={false}
        blending={THREE.AdditiveBlending}
      />
    </points>
  )
}
