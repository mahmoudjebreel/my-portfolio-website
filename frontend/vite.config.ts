/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'
import { parseDataSource } from './src/data/sourceConfig.ts'

const DEFAULT_PROXY_TARGET = 'http://127.0.0.1:8000'

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  // Fails fast on an invalid VITE_DATA_SOURCE, and refuses "mock" for production builds.
  parseDataSource(env.VITE_DATA_SOURCE, { production: command === 'build' && mode === 'production' })

  // The Laravel API of this project runs on `php artisan serve` (127.0.0.1:8000 by default;
  // set VITE_API_PROXY_TARGET to use another address).
  // The dev server proxies API, Sanctum and storage requests, and the Filament administration
  // (/admin), so the SPA, the API and the panel share one origin.
  const backend = env.VITE_API_PROXY_TARGET?.trim() || DEFAULT_PROXY_TARGET
  if (!/^https?:\/\/[^\s/]+$/.test(backend)) {
    throw new Error(`VITE_API_PROXY_TARGET must be an origin like ${DEFAULT_PROXY_TARGET} (got "${backend}").`)
  }

  const panel = { target: backend, changeOrigin: false }

  return {
    plugins: [react(), tailwindcss()],
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      proxy: {
        '/api': backend,
        '/sanctum': backend,
        '/storage': backend,
        // Administration: the Laravel Filament panel (pages, Livewire requests and its
        // published assets). changeOrigin is off so the Host header stays this dev server:
        // Laravel then builds its links and redirects for this origin, not for the backend.
        '/admin': panel,
        '^/livewire': panel,
        '/filament': panel,
        '/js/filament': panel,
        '/css/filament': panel,
        '/fonts/filament': panel,
      },
    },
    build: {
      target: 'es2022',
      sourcemap: false,
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      css: false,
      restoreMocks: true,
      unstubGlobals: true,
      unstubEnvs: true,
      // Worker threads start reliably on this Windows setup (forked workers timed out on startup).
      pool: 'threads',
    },
  }
})
