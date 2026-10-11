import { defineConfig, devices } from '@playwright/test'

// Run only against an explicitly prepared local PHP/MySQL test installation.
const baseURL = process.env.OPOMATIC_E2E_BASE_URL
if (!baseURL || !['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) {
  throw new Error('Set OPOMATIC_E2E_BASE_URL to a local OpoMatic-2000 test installation')
}
if (!process.env.OPOMATIC_E2E_GOGLOGLO_PASSWORD || !process.env.OPOMATIC_E2E_VIRGINIA_PASSWORD) {
  throw new Error('Set both OPOMATIC_E2E_*_PASSWORD values for the isolated test accounts')
}

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  reporter: [['list']],
  timeout: 30_000,
  expect: { timeout: 8_000 },
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
})
