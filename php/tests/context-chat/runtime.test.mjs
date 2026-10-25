// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { taskErrorCommand, readTaskError, findNextcloud, containerPassword, waitForContextChat } from './runtime.mjs';

test('task diagnostics select the runner container without depending on host paths', () => {
  const args = taskErrorCommand(42, 'container-id');
  assert.deepEqual(args.slice(0, 4), ['exec', '-u', 'www-data', 'container-id']);
  assert.equal(args.at(-1), '42');
  assert.ok(!args.includes('compose'));
});

test('host diagnostics retain the existing Compose execution path', () => {
  const args = taskErrorCommand(42, '');
  assert.equal(args[0], 'compose');
  assert.ok(args.includes('nextcloud-aio-nextcloud'));
  assert.ok(args.includes('-T'));
});

test('invalid task IDs are rejected before invoking Docker', () => {
  for (const id of ['42; exit', -1, 0, 1.5, undefined]) {
    assert.throws(() => taskErrorCommand(id, 'container'), /Invalid task ID/);
  }
});

test('task diagnostics parse only the selected task error with a command timeout', async () => {
  const result = await readTaskError(42, async (cmd, args, options) => {
    assert.equal(cmd, 'docker');
    assert.equal(args.at(-1), '42');
    assert.equal(options.timeout, 30000);
    return { stdout: JSON.stringify('No documents retrieved') };
  });
  assert.equal(result, 'No documents retrieved');
  await assert.rejects(readTaskError(42, async () => ({ stdout: 'not JSON' })), SyntaxError);
});

test('container lookup is scoped by both Compose labels', async () => {
  const id = await findNextcloud('custom-project', async (cmd, args) => {
    assert.equal(cmd, 'docker');
    assert.ok(args.includes('label=com.docker.compose.project=custom-project'));
    assert.ok(args.includes('label=com.docker.compose.service=nextcloud-aio-nextcloud'));
    return { stdout: 'abc123\n' };
  });
  assert.equal(id, 'abc123');
});

test('missing or ambiguous Nextcloud containers fail instead of selecting another stack', async () => {
  for (const stdout of ['', 'first\nsecond\n']) {
    await assert.rejects(findNextcloud('project', async () => ({ stdout })), /Expected one/);
  }
});

test('runtime password is passed as data with only the trailing newline removed', async () => {
  const value = await containerPassword('abc', async (cmd, args) => {
    assert.equal(cmd, 'docker');
    assert.deepEqual(args, ['exec', 'abc', 'printenv', 'ADMIN_PASSWORD']);
    return { stdout: ' example $value \n' };
  });
  assert.equal(value, ' example $value ');
});

test('empty runtime password gives an actionable error', async () => {
  await assert.rejects(containerPassword('abc', async () => ({ stdout: '\n' })), /Set NC_TEST_PASSWORD/);
});

function response(status, ready = false) {
  return { status: () => status, ok: () => status === 200,
    json: async () => ({ ocs: { data: { types: ready ? { 'context_chat:context_chat': {} } : {} } } }) };
}

test('readiness retries network errors, startup errors, and missing providers', async () => {
  let calls = 0;
  let time = 0;
  await waitForContextChat({ get: async path => {
    assert.equal(path, '/ocs/v2.php/taskprocessing/tasktypes');
    calls++;
    if (calls === 1) throw new Error('connection refused');
    return calls === 2 ? response(503) : response(200, calls === 4);
  } }, { timeout: 10000, now: () => time, pause: async ms => { time += ms; } });
  assert.equal(calls, 4);
});

test('bad credentials fail immediately without exposing password values', async () => {
  for (const status of [401, 403]) {
    await assert.rejects(waitForContextChat({ get: async () => response(status) }), /rejected the test credentials/);
  }
});

test('readiness has a bounded startup timeout', async () => {
  let time = 0;
  await assert.rejects(waitForContextChat({ get: async () => response(503) }, {
    timeout: 4000, now: () => time, pause: async ms => { time += ms; },
  }), /startup timeout/);
  assert.equal(time, 4000);
});
