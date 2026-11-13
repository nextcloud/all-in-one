// Shared helpers for the deSEC Playwright scenarios.
//
// The deSEC mock is wired up by seeding configuration.json (see seed-desec-mock-config.php),
// which makes AIO consider itself already installed: /setup no longer renders the
// initial-password page. The seed step therefore writes a known master password (AIO_TEST_PASSWORD)
// that we log in with directly here instead of scraping it from /setup.

import { expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { request } from 'node:http';

export const DESEC_MOCK_URL = process.env.DESEC_MOCK_URL ?? 'http://localhost:8090';

// The test runner mounts the mastercontainer's config volume read-only at /mnt/docker-aio-config.
export function readMastercontainerConfig() {
  return JSON.parse(readFileSync('/mnt/docker-aio-config/data/configuration.json', 'utf8'));
}

function dockerRequest(method, path, body) {
  return new Promise((resolve, reject) => {
    const payload = body === undefined ? undefined : JSON.stringify(body);
    const headers = payload === undefined ? {} : { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) };
    const req = request({ socketPath: '/var/run/docker.sock', path, method, headers }, (res) => {
      let data = '';
      res.setEncoding('utf8');
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => resolve({ status: res.statusCode, body: data }));
    });
    req.on('error', reject);
    if (payload !== undefined) req.write(payload);
    req.end();
  });
}

// Runs a command inside the mastercontainer via the Docker Engine API (the runner has the
// docker socket mounted) and waits for it to finish. Used to trigger the periodic deSEC IP
// update on demand instead of waiting for its 10-minute interval.
export async function execInMastercontainer(cmd) {
  const created = await dockerRequest('POST', '/containers/nextcloud-aio-mastercontainer/exec', {
    Cmd: cmd, AttachStdout: true, AttachStderr: true,
  });
  if (created.status !== 201) throw new Error(`docker exec create failed: HTTP ${created.status} ${created.body}`);
  const { Id } = JSON.parse(created.body);
  // Starting without Detach streams the output until the command exits.
  const started = await dockerRequest('POST', `/exec/${Id}/start`, { Detach: false, Tty: false });
  const inspected = await dockerRequest('GET', `/exec/${Id}/json`);
  return { exitCode: JSON.parse(inspected.body).ExitCode, output: started.body };
}

export async function logInToContainersPage(setupPage) {
  // Extract initial password
  await setupPage.goto('./setup');
  const password = await setupPage.locator('#initial-password').innerText()
  const containersPagePromise = setupPage.waitForEvent('popup');
  await setupPage.getByRole('link', { name: 'Open Nextcloud AIO login ↗' }).click();
  const containersPage = await containersPagePromise;

  // Typing must not reveal the passphrase; only the reveal button toggles it
  const passwordField = containersPage.locator('#master-password');
  await passwordField.click();
  await passwordField.fill(password);
  await expect(passwordField).toHaveAttribute('type', 'password');
  await containersPage.getByRole('button', { name: 'Show passphrase' }).click();
  await expect(passwordField).toHaveAttribute('type', 'text');
  await containersPage.getByRole('button', { name: 'Hide passphrase' }).click();
  await expect(passwordField).toHaveAttribute('type', 'password');

  // Log in and wait for redirect
  await containersPage.getByRole('button', { name: 'Log in' }).click();
  await containersPage.waitForURL('./containers');
  return containersPage;
}
