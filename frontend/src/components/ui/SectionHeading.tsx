import { Reveal } from './Reveal'

export function SectionHeading({ id, eyebrow, title }: { id: string; eyebrow: string; title: string }) {
  return (
    <Reveal className="mb-12 max-w-2xl">
      <p className="eyebrow mb-3">{eyebrow}</p>
      <h2 id={id} className="font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
        {title}
      </h2>
    </Reveal>
  )
}
