import react from '@vitejs/plugin-react'
import { configDefaults, defineConfig } from 'vitest/config'

const devApiTarget = process.env.OPOMATIC_DEV_API_TARGET || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': devApiTarget,
      '/health': devApiTarget,
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    exclude: [...configDefaults.exclude, 'e2e/**'],
  },
})
