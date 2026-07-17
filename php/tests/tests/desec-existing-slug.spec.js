import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, logInToContainersPage } from './helpers.js';

// Exercises reusing a slug the user already owns on an existing deSEC account. This is the
// case that previously failed: deSEC answers POST /domains/ with 403 "Domain limit exceeded"
// (the account is at its domain quota) even though the user owns the very slug they typed.
// DesecManager now recovers by checking GET /domains/{name}/ and reusing the owned domain.
//
// We seed the account up to the mock's domain limit, then pre-assign the target slug to that
// same account, so creation is rejected with 403 and the ownership check is what lets it through.
//
// Like the other domain-registering flows, this runs as its own CI step against freshly
// seeded deSEC + AIO state.

test('deSEC reuses a slug the user already owns when over the domain limit', async ({ page: setupPage }) => {
  test.setTimeout(5 * 60 * 1000);

  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });
  const email = `owner-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const password = 'correct horse battery staple';
  await fetch(`${DESEC_MOCK_URL}/api/v1/auth/`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });

  const slug = `aio-owned-${Math.floor(Math.random() * 2147483647)}`;
  const domain = `${slug}.dedyn.io`;

  // Fill the account up to the limit (15) with throwaway domains, then assign the target
  // slug too — so a fresh POST /domains/ for it is rejected with 403, exactly as deSEC does.
  for (let i = 0; i < 15; i++) {
    await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: `filler-${i}-${slug}.dedyn.io`, email }),
    });
  }
  await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: domain, email }),
  });

  const containersPage = await logInToContainersPage(setupPage);

  await containersPage.getByText("Don't have a domain? Get a free one from deSEC").click();
  await containersPage.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  const frame = containersPage.frameLocator('#desec-frame');
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_password"]').fill(password);
  await frame.locator('input[name="desec_slug"]').first().fill(slug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  // Despite the 403 on creation, the domain the user already owns is reused and registration
  // completes; the modal view then reloads the parent containers page.
  await expect(containersPage.getByRole('button', { name: 'Download and start containers' })).toBeVisible({ timeout: 60 * 1000 });
  await expect(
    containersPage.getByText("Don't have a domain? Get a free one from deSEC"),
  ).toHaveCount(0);
});
