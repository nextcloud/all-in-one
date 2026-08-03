import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, logInToContainersPage } from './helpers.js';

// Exercises the deSEC "I already have a verified account" login path: supplying a valid
// password logs straight in and registers the domain in one step (no email-verification
// round-trip). See desec-register.spec.js for the full setup notes.
//
// As in the register flow, the form is driven inside the modal iframe (the /desec view);
// on success the iframe reloads the parent containers page.
//
// This flow also ends by registering a domain, so it is run as its own CI step with the
// deSEC state re-seeded beforehand (configuration.json must have no domain set for the
// registration UI to render).

test('deSEC existing-account login flow', async ({ page: setupPage }) => {
  test.setTimeout(5 * 60 * 1000);

  // Pre-create a verified account in the mock so the password (login) path is exercised
  // directly, without the verification round-trip.
  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });
  const email = `existing-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const password = 'correct horse battery staple';
  await fetch(`${DESEC_MOCK_URL}/api/v1/auth/`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });

  const containersPage = await logInToContainersPage(setupPage);

  const slug = `aio-existing-${Math.floor(Math.random() * 2147483647)}`;

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
  await expect(
    containersPage.getByText("Don't have a domain? Get a free one from deSEC"),
  ).toHaveCount(0);
});
