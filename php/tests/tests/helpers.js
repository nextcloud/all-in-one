// Shared helpers for the deSEC Playwright scenarios.
//
// The deSEC mock is wired up by seeding configuration.json (see seed-desec-mock-config.php),
// which makes AIO consider itself already installed: /setup no longer renders the
// initial-password page. The seed step therefore writes a known master password (AIO_TEST_PASSWORD)
// that we log in with directly here instead of scraping it from /setup.

import { expect } from '@playwright/test';

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
