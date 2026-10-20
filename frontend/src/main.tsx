import '@fontsource-variable/inter'
import '@fontsource-variable/space-grotesk'
import '@fontsource-variable/noto-kufi-arabic'
import './styles/index.css'

import { MotionConfig } from 'motion/react'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router'
import { createRouter } from './app/router'

const root = document.getElementById('root')
if (!root) throw new Error('Missing #root element')

createRoot(root).render(
  <StrictMode>
    <MotionConfig reducedMotion="user">
      <RouterProvider router={createRouter()} />
    </MotionConfig>
  </StrictMode>,
)
