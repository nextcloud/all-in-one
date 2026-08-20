import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { logInToContainersPage } from './helpers.js';
import { freshCode } from './totp-helper.js';

const CONFIG_FILE = '/mnt/docker-aio-config/data/configuration.json';
const GENERIC_LOGIN_ERROR = 'Login failed. Please check your credentials';

function readConfig() {
  return JSON.parse(readFileSync(CONFIG_FILE, 'utf8'));
}

// Re-open the AIO login page and log in, filling the second factor when asked.
async function login(page, { password, totp }) {
  await page.goto('./login');
  await page.locator('#master-password').fill(password);
  if (totp !== undefined) {
    await page.locator('#totp-code').fill(totp);
  }
  await page.getByRole('button', { name: 'Log in' }).click();
}

test('Optional TOTP second factor: enable, enforce, disable', async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);

  const containersPage = await logInToContainersPage(page);
  const password = readConfig().password;

  // While 2FA is off, a permanent warning nags the user to set it up, with a link
  // to the 2FA section further down the page.
  await expect(containersPage.locator('.notification--warning')).toContainText('Two-factor authentication is not set up');
  const nagLink = containersPage.locator('.notification--warning a[href="#two-factor-auth-setup"]');
  await expect(nagLink).toHaveText('enabling it below');

  // Following the link anchors to the 2FA headline (which becomes the :target for
  // the highlight) and expands the section below it.
  await nagLink.click();
  await expect(containersPage).toHaveURL(/#two-factor-auth-setup$/);
  expect(await containersPage.evaluate(() =>
    document.querySelector('#two-factor-auth-setup') === document.querySelector('h2:target'))).toBe(true);
  await expect(containersPage.locator('h2#two-factor-auth-setup + details')).toHaveJSProperty('open', true);

  // --- Enable --- (the section is already expanded via the anchor above)
  const secret = (await containersPage.locator('#totp-setup-secret').innerText()).trim();
  expect(secret).toMatch(/^[A-Z2-7]{32}$/);
  // The QR is rendered client-side from the otpauth URI onto a <canvas>.
  await expect(containersPage.locator('#totp-qr canvas')).toBeVisible();

  // A wrong code must be rejected and must not enable 2FA.
  await containersPage.locator('input[name="totp_code"]').fill('000000');
  await containersPage.getByRole('button', { name: 'Enable two-factor authentication' }).click();
  await expect(containersPage.locator('body')).toContainText('The entered code is not correct');
  expect(readConfig().totp_secret ?? '').toBe('');

  // Confirm with a correct code → enabled. The 422 above did not reload the page,
  // so the same secret and the open <details> are still in place.
  const enable = await freshCode(secret);
  await containersPage.locator('input[name="totp_code"]').fill(enable.code);
  await containersPage.getByRole('button', { name: 'Enable two-factor authentication' }).click();
  await expect(containersPage.locator('body')).toContainText('Two-factor authentication is currently');
  // A temporary notice confirms the change (asserted before it auto-dismisses).
  await expect(containersPage.locator('.notification--notice')).toContainText('has been enabled');
  // ...and the permanent nag is gone now that 2FA is enabled.
  await expect(containersPage.locator('.notification--warning')).toHaveCount(0);
  expect(readConfig().totp_secret).toBe(secret);

  // --- Enforce on login ---
  await containersPage.getByRole('button', { name: 'Log out' }).click();
  await containersPage.waitForURL('./login');
  // The code field only appears once 2FA is enabled.
  await expect(containersPage.locator('#totp-code')).toBeVisible();

  // Right password + wrong code → generic error, nothing is consumed.
  await login(containersPage, { password, totp: '000000' });
  await expect(containersPage.locator('body')).toContainText(GENERIC_LOGIN_ERROR);

  // One fresh code, reused across the next two attempts. Enabling consumed the
  // current window's code, so use a window past that one.
  const fresh = await freshCode(secret, enable.counter);

  // Wrong password + this valid code → the SAME generic error (no factor is
  // singled out), and the code must NOT be consumed by this failed attempt...
  await login(containersPage, { password: 'definitely-the-wrong-passphrase', totp: fresh.code });
  await expect(containersPage.locator('body')).toContainText(GENERIC_LOGIN_ERROR);

  // ...so fixing the password and re-submitting the SAME code succeeds.
  await login(containersPage, { password, totp: fresh.code });
  await containersPage.waitForURL('./containers');

  // --- Disable --- (needs a code from a window past the one the login consumed)
  await containersPage.getByText('disable the authenticator-app second factor').click();
  const disable = await freshCode(secret, fresh.counter);
  await containersPage.locator('input[name="totp_code"]').fill(disable.code);
  await containersPage.getByRole('button', { name: 'Disable two-factor authentication' }).click();
  await expect(containersPage.locator('body')).toContainText('Scan this QR code');
  await expect(containersPage.locator('.notification--notice')).toContainText('has been disabled');
  // The permanent nag reappears now that 2FA is off again.
  await expect(containersPage.locator('.notification--warning')).toContainText('Two-factor authentication is not set up');
  expect(readConfig().totp_secret ?? '').toBe('');

  // Login no longer asks for a code.
  await containersPage.getByRole('button', { name: 'Log out' }).click();
  await containersPage.waitForURL('./login');
  await expect(containersPage.locator('#totp-code')).toHaveCount(0);
});
