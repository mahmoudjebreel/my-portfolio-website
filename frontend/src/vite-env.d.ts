/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base URL of the public API. Default: "/api" (same origin / Vite proxy). */
  readonly VITE_API_BASE_URL?: string
  /** "api" (default) or "mock" (development only). */
  readonly VITE_DATA_SOURCE?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
