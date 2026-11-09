import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, logInToContainersPage } from './helpers.js';

// Exercises abandoning a deSEC registration that is stuck awaiting email verification.
//
// The domain is requested at signup and created by deSEC when the user activates the
// account. If the name was taken in the meantime, activation fails and deSEC deletes the
// pending account, so AIO's stored credentials can never log in. The awaiting step offers
// a "Start over" button that clears the pending registration so the user can try again
// and, since deSEC deleted the account, with the very same email address. See
// desec-register.spec.js for the setup notes.
//
// Like the other domain-registering flows, this runs as its own CI step against freshly
// seeded deSEC + AIO state.

test('deSEC start over after a failed activation', async ({ page: setupPage }) => {
  test.setTimeout(5 * 60 * 1000);

  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });

  const containersPage = await logInToContainersPage(setupPage);

  const email = `restart-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const slug = `aio-restart-${Math.floor(Math.random() * 2147483647)}`;

  await containersPage.getByText("Don't have a domain? Get a free one from deSEC").click();
  await containersPage.getByRole('button', { name: 'Register free domain via deSEC' }).click();
  const frame = containersPage.frameLocator('#desec-frame');

  // 1) Request an account with a (currently free) slug -> awaiting verification.
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_slug"]').first().fill(slug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();
  await expect(frame.getByText('check your inbox')).toBeVisible({ timeout: 30 * 1000 });
  await expect(frame.getByText(`${slug}.dedyn.io`)).toBeVisible();

  // 2) Somebody else grabs the name before the user clicks the verification link. On
  //    activation the mock (like deSEC) cannot create the domain and deletes the account.
  await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: `${slug}.dedyn.io`, email: 'someone-else@example.com' }),
  });
  await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });
  const mockState = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
  expect(mockState.accounts).not.toContain(email);

  // 3) Finishing the setup can never succeed now; the hint points at "Start over".
  await frame.getByRole('button', { name: 'I have verified my email – finish setup' }).click();
  await expect(frame.locator('.toast.error')).toContainText('Start over', { timeout: 8 * 1000 });

  // 4) Start over -> the pending registration is cleared and the initial form is back.
  await frame.getByRole('button', { name: 'Start over' }).click();
  await expect(frame.locator('input[name="desec_email"]')).toBeVisible({ timeout: 30 * 1000 });
  await expect(frame.getByText('check your inbox')).toHaveCount(0);

  // 5) The cleared state allows a fresh registration with another slug to go through. The
  //    same address works because deSEC deleted the account (only a still-pending account
  //    blocks further registration mails to its address).
  const secondSlug = `${slug}-2`;
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_slug"]').first().fill(secondSlug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();
  await expect(frame.getByText(`${secondSlug}.dedyn.io`)).toBeVisible({ timeout: 30 * 1000 });
  await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });
  await frame.getByRole('button', { name: 'I have verified my email – finish setup' }).click();
  await expect(containersPage.getByRole('button', { name: 'Download and start containers' })).toBeVisible({ timeout: 60 * 1000 });
});
