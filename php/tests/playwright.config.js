import { defineConfig, devices } from '@playwright/test'

/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html'],
  ],
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8080',
    trace: 'on',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        ignoreHTTPSErrors: true,
      },
    },
  ],
})
