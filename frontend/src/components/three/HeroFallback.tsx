/** Static visual used without WebGL, while the scene loads, or if it fails. */
export function HeroFallback() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" data-testid="hero-fallback">
      <div className="absolute top-1/2 left-1/2 size-[22rem] -translate-1/2 rounded-full bg-violet-600/30 blur-3xl motion-safe:animate-float-slow sm:size-[28rem]" />
      <div className="absolute top-[30%] left-[62%] size-56 rounded-full bg-cyan-400/20 blur-3xl motion-safe:animate-float-slow [animation-delay:-3s]" />
      <svg
        className="absolute top-1/2 left-1/2 w-[min(80%,26rem)] -translate-1/2 opacity-70"
        viewBox="0 0 200 200"
        fill="none"
      >
        <defs>
          <linearGradient id="fallback-stroke" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#a78bfa" />
            <stop offset="1" stopColor="#22d3ee" />
          </linearGradient>
        </defs>
        <g stroke="url(#fallback-stroke)" strokeWidth="0.8" strokeLinejoin="round">
          <polygon points="100,22 168,61 168,139 100,178 32,139 32,61" />
          <polyline points="100,22 100,100 168,139" />
          <polyline points="100,100 32,139" />
          <polygon points="100,52 140,75 140,125 100,148 60,125 60,75" opacity="0.6" />
        </g>
      </svg>
    </div>
  )
}
