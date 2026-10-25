// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { request } from '@playwright/test';
import { spawn } from 'node:child_process';
import { findNextcloud, containerPassword, waitForContextChat } from './runtime.mjs';

try {
  const url = new URL(process.env.NC_TEST_URL);
  process.env.NC_TEST_CONTAINER = await findNextcloud(process.env.NC_TEST_PROJECT);
  process.env.NC_TEST_PASSWORD ||= await containerPassword(process.env.NC_TEST_CONTAINER);
  const api = await request.newContext({
    baseURL: url.href, ignoreHTTPSErrors: true,
    httpCredentials: { username: process.env.NC_TEST_USER, password: process.env.NC_TEST_PASSWORD, send: 'always' },
    extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
  });
  try {
    console.log('Waiting for Nextcloud and the Context Chat task provider…');
    await waitForContextChat(api);
  } finally {
    await api.dispose();
  }
  const child = spawn(process.execPath, [
    'node_modules/playwright/cli.js', 'test', '--config=playwright.context-chat.config.mjs', ...process.argv.slice(2),
  ], { stdio: 'inherit' });
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));
  child.on('error', () => { console.error('Could not start Playwright'); process.exitCode = 1; });
  child.on('exit', (code, signal) => { process.exitCode = code ?? (signal === 'SIGINT' ? 130 : 143); });
} catch (error) {
  // Print only our diagnostic message, never authenticated request details.
  console.error(error.message);
  process.exitCode = 1;
}
