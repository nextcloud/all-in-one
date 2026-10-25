// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { defineConfig } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';
import { parseEnv } from 'node:util';

const envFile = new URL('../../.env', import.meta.url);
const local = existsSync(envFile) ? parseEnv(readFileSync(envFile, 'utf8')) : {};
process.env.NC_TEST_USER ??= 'admin';
if (!process.env.NC_TEST_PASSWORD && local.NEXTCLOUD_PASSWORD) {
  process.env.NC_TEST_PASSWORD = local.NEXTCLOUD_PASSWORD;
}

export default defineConfig({
  testDir: './context-chat',
  testMatch: 'lifecycle.spec.mjs',
  workers: 1,
  retries: 0,
  timeout: 25 * 60_000,
  expect: { timeout: 30_000 },
  forbidOnly: !!process.env.CI,
  outputDir: './test-results/context-chat',
  reporter: [['list'], ['html', { outputFolder: 'playwright-report/context-chat', open: 'never' }]],
  use: {
    baseURL: process.env.NC_TEST_URL ?? local.NC_TEST_URL ?? 'https://nextcloud.local',
    ignoreHTTPSErrors: true,
    browserName: 'chromium',
    viewport: { width: 1440, height: 1000 },
    actionTimeout: 30_000,
    navigationTimeout: 60_000,
    // Login credentials and authenticated requests must not be saved in traces.
    trace: 'off',
    screenshot: 'only-on-failure',
    video: process.env.NC_TEST_VIDEO ?? 'off',
  },
});
