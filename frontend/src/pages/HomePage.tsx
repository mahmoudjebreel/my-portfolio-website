import { useSettleHashScroll } from '../lib/useSettleHashScroll'
import { About } from '../sections/About'
import { Approach } from '../sections/Approach'
import { Contact } from '../sections/Contact'
import { FeaturedProjects } from '../sections/FeaturedProjects'
import { Hero } from '../sections/Hero'
import { Skills } from '../sections/Skills'

export function HomePage() {
  // Links like /en#approach: keep the section in place while the sections above it load.
  useSettleHashScroll()

  return (
    <>
      <Hero />
      <About />
      <Skills />
      <FeaturedProjects />
      <Approach />
      <Contact />
    </>
  )
}
