// Shared helpers for the deSEC Playwright scenarios.
//
// The deSEC mock is wired up by seeding configuration.json (see seed-desec-mock-config.php),
// which makes AIO consider itself already installed: /setup no longer renders the
// initial-password page. The seed step therefore writes a known master password (AIO_TEST_PASSWORD)
// that we log in with directly here instead of scraping it from /setup.

export const DESEC_MOCK_URL = process.env.DESEC_MOCK_URL ?? 'http://localhost:8090';

const AIO_PASSWORD = process.env.AIO_TEST_PASSWORD;

export async function logInToContainersPage(page) {
  if (!AIO_PASSWORD) {
    throw new Error('AIO_TEST_PASSWORD must be set to the master password seeded into configuration.json');
  }
  await page.goto('./');
  await page.locator('#master-password').click();
  await page.locator('#master-password').fill(AIO_PASSWORD);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL('./containers');
  return page;
}
