// Shared helpers for the deSEC Playwright scenarios.
//
// The deSEC mock is wired up by seeding configuration.json (see seed-desec-mock-config.php),
// which makes AIO consider itself already installed: /setup no longer renders the
// initial-password page. The seed step therefore writes a known master password (AIO_TEST_PASSWORD)
// that we log in with directly here instead of scraping it from /setup.

export const DESEC_MOCK_URL = process.env.DESEC_MOCK_URL ?? 'http://localhost:8090';

export async function logInToContainersPage(setupPage) {
  // Extract initial password
  await setupPage.goto('./setup');
  const password = await setupPage.locator('#initial-password').innerText()
  const containersPagePromise = setupPage.waitForEvent('popup');
  await setupPage.getByRole('link', { name: 'Open Nextcloud AIO login ↗' }).click();
  const containersPage = await containersPagePromise;

  // Log in and wait for redirect
  await containersPage.locator('#master-password').click();
  await containersPage.locator('#master-password').fill(password);
  await containersPage.getByRole('button', { name: 'Log in' }).click();
  await containersPage.waitForURL('./containers');
  return containersPage;
}
