// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const run = promisify(execFile);
const cwd = fileURLToPath(new URL('../', import.meta.url));

test('container entry discovers credentials, waits for the API, and forwards --list', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'context-chat-entry-'));
  let authenticated = false;
  const server = createServer((req, res) => {
    authenticated = req.headers.authorization === `Basic ${Buffer.from('admin:fixture-password').toString('base64')}`;
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify({ ocs: { data: { types: { 'context_chat:context_chat': {} } } } }));
  });
  try {
    await new Promise((resolve, reject) => {
      server.once('error', reject);
      server.listen(0, '127.0.0.1', resolve);
    });
    await writeFile(join(dir, 'docker'), '#!/bin/sh\ncase "$1" in\nps) echo fixture-container;;\nexec) echo fixture-password;;\nesac\n', { mode: 0o700 });
    const options = {
      cwd, timeout: 30000,
      env: { ...process.env, PATH: `${dir}:${process.env.PATH}`, NC_TEST_PROJECT: 'fixture',
        NC_TEST_USER: 'admin', NC_TEST_PASSWORD: '',
        NC_TEST_URL: `http://127.0.0.1:${server.address().port}` },
    };
    const { stdout } = await run(process.execPath, ['context-chat/run-container.mjs', '--list'], options);
    assert.ok(authenticated);
    assert.match(stdout, /Total: 5 tests in 1 file/);
    await assert.rejects(run(process.execPath, ['context-chat/run-container.mjs', '--unknown-option'], options),
      error => error.code === 1 && /unknown option/.test(error.stderr));
  } finally {
    await new Promise(resolve => server.close(resolve));
    await rm(dir, { recursive: true, force: true });
  }
});

test('container entry rejects an invalid URL before accessing Docker', async () => {
  await assert.rejects(run(process.execPath, ['context-chat/run-container.mjs'], {
    cwd, env: { ...process.env, NC_TEST_URL: 'not-a-url' }, timeout: 10000,
  }), error => error.code === 1 && /Invalid URL/.test(error.stderr));
});
