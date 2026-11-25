// Shared helpers for the deSEC Playwright scenarios.
//
// The deSEC mock is wired up by seeding configuration.json (see seed-desec-mock-config.php),
// which makes AIO consider itself already installed: /setup no longer renders the
// initial-password page. The seed step therefore writes a known master password (AIO_TEST_PASSWORD)
// that we log in with directly here instead of scraping it from /setup.

import { expect } from '@playwright/test';
import { request } from 'node:http';

export const DESEC_MOCK_URL = process.env.DESEC_MOCK_URL ?? 'http://localhost:8090';

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

// Creates a verified account in the mock and registers the given slug with it through the
// deSEC modal, using the existing-account (password) path.
export async function registerDesecDomainWithExistingAccount(containersPage, email, password, slug) {
  await fetch(`${DESEC_MOCK_URL}/api/v1/auth/`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });

  // Open the deSEC registration entry point and launch the modal.
  await containersPage.getByText("Don't have a domain? Get a free one from deSEC").click();
  await containersPage.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  const frame = containersPage.frameLocator('#desec-frame');
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_password"]').fill(password);
  await frame.locator('input[name="desec_slug"]').first().fill(slug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  // Supplying a valid password logs straight in and registers the domain in one step; the
  // modal view then reloads the parent containers page.
  await expect(containersPage.getByRole('button', { name: 'Download and start containers' })).toBeVisible({ timeout: 60 * 1000 });
}

function dockerApi(method, path, body) {
  return new Promise((resolve, reject) => {
    const req = request({
      socketPath: '/var/run/docker.sock',
      method,
      path,
      headers: { 'Content-Type': 'application/json' },
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(data));
    });
    req.on('error', reject);
    req.end(body === undefined ? undefined : JSON.stringify(body));
  });
}

// Runs a command in the mastercontainer as www-data (like its background services) and returns its output.
export async function execInMastercontainer(cmd) {
  const { Id } = JSON.parse(await dockerApi('POST', '/containers/nextcloud-aio-mastercontainer/exec', {
    Cmd: cmd, User: 'www-data', AttachStdout: true, AttachStderr: true, Tty: true,
  }));
  const output = await dockerApi('POST', `/exec/${Id}/start`, { Detach: false, Tty: true });
  const { ExitCode } = JSON.parse(await dockerApi('GET', `/exec/${Id}/json`));
  if (ExitCode !== 0) {
    throw new Error(`${cmd.join(' ')} exited with ${ExitCode}: ${output}`);
  }
  return output;
}
