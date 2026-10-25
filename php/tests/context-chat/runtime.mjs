// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { setTimeout as sleep } from 'node:timers/promises';

const run = promisify(execFile);
const root = fileURLToPath(new URL('../../../', import.meta.url));

export function taskErrorCommand(id, container = process.env.NC_TEST_CONTAINER) {
  if (!Number.isSafeInteger(Number(id)) || Number(id) < 1) throw new Error('Invalid task ID');
  const prefix = container
    ? ['exec', '-u', 'www-data', container]
    : ['compose', '--project-directory', root, '-f', `${root}/docker-compose.yml`,
      'exec', '-T', '-u', 'www-data', 'nextcloud-aio-nextcloud'];
  return [...prefix, 'php', '-r',
    "require '/var/www/html/lib/base.php'; echo json_encode(\\OCP\\Server::get(\\OCP\\TaskProcessing\\IManager::class)->getTask((int)$argv[1])->getErrorMessage());",
    String(id)];
}

export async function readTaskError(id, execute = run) {
  const { stdout } = await execute('docker', taskErrorCommand(id), { timeout: 30_000 });
  return JSON.parse(stdout);
}

export async function findNextcloud(project, execute = run) {
  const { stdout } = await execute('docker', ['ps',
    '--filter', `label=com.docker.compose.project=${project}`,
    '--filter', 'label=com.docker.compose.service=nextcloud-aio-nextcloud',
    '--format', '{{.ID}}'], { timeout: 30_000 });
  const ids = stdout.trim().split(/\s+/).filter(Boolean);
  if (ids.length !== 1) throw new Error('Expected one running Nextcloud container in the Compose project');
  return ids[0];
}

export async function containerPassword(container, execute = run) {
  const { stdout } = await execute('docker', ['exec', container, 'printenv', 'ADMIN_PASSWORD'], { timeout: 30_000 });
  const password = stdout.replace(/\r?\n$/, '');
  if (!password) throw new Error('Set NC_TEST_PASSWORD: the container has no ADMIN_PASSWORD');
  return password;
}

export async function waitForContextChat(api, {
  timeout = 180_000, now = Date.now, pause = sleep,
} = {}) {
  const deadline = now() + timeout;
  while (now() < deadline) {
    let response;
    try {
      response = await api.get('/ocs/v2.php/taskprocessing/tasktypes', { timeout: 10_000 });
    } catch {
      // The HTTP frontend may still be starting after container health checks pass.
    }
    if (response?.status() === 401 || response?.status() === 403) {
      throw new Error('Nextcloud rejected the test credentials; set NC_TEST_USER and NC_TEST_PASSWORD');
    }
    if (response?.ok()) {
      const body = await response.json();
      if (body.ocs?.data?.types?.['context_chat:context_chat']) return;
    }
    await pause(2000);
  }
  throw new Error('Nextcloud Context Chat did not become ready within the startup timeout');
}
