/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, logInToContainersPage } from './helpers.js';

// Exercises logging in to an existing deSEC account that already has a dedyn.io domain
// while asking for a different subdomain. deSEC allows only one dedyn.io domain per
// account and answers POST /domains/ with 400, naming the existing domain. AIO must pass
// that explanation on instead of claiming the requested name is taken, and entering the
// existing subdomain must then complete the registration.
//
// Like the other domain-registering flows, this runs as its own CI step against freshly
// seeded deSEC + AIO state.

test('deSEC explains that the account already has another dedyn.io domain', async ({ page: setupPage }) => {
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

  const ownedSlug = `aio-owned-${Math.floor(Math.random() * 2147483647)}`;
  const ownedDomain = `${ownedSlug}.dedyn.io`;
  await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: ownedDomain, email }),
  });

  const containersPage = await logInToContainersPage(setupPage);

  await containersPage.getByText("Don't have a domain? Get a free one from deSEC").click();
  await containersPage.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  const frame = containersPage.frameLocator('#desec-frame');
  const slugField = frame.locator('input[name="desec_slug"]').first();
  const submit = frame.getByRole('button', { name: 'Register free domain via deSEC' });
  const reason = `You already have ${ownedDomain}. Only one domain under dedyn.io can be registered per account`;

  // 1) A different subdomain is rejected with deSEC's own explanation.
  const otherSlug = `aio-other-${Math.floor(Math.random() * 2147483647)}`;
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_password"]').fill(password);
  await slugField.fill(otherSlug);
  await submit.click();
  await expect(containersPage.locator('.toast.error')).toContainText(
    `deSEC could not register "${otherSlug}.dedyn.io": ${reason}`,
    { timeout: 8 * 1000 },
  );
  const mockState = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
  expect(Object.keys(mockState.domains)).toEqual([ownedDomain]);

  // 2) So is a random one (blank subdomain), without retrying with further random names.
  await slugField.fill('');
  await submit.click();
  await expect(containersPage.locator('.toast.error')).toContainText(reason, { timeout: 8 * 1000 });

  // 3) A name that belongs to somebody else is reported with deSEC's explanation, too.
  const takenSlug = `aio-taken-${Math.floor(Math.random() * 2147483647)}`;
  await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: `${takenSlug}.dedyn.io`, email: 'someone-else@example.com' }),
  });
  await slugField.fill(takenSlug);
  await submit.click();
  await expect(containersPage.locator('.toast.error')).toContainText(
    `deSEC could not register "${takenSlug}.dedyn.io": This domain name conflicts with an existing domain`,
    { timeout: 8 * 1000 },
  );

  // 4) Entering the subdomain the account already has completes the registration.
  await slugField.fill(ownedSlug);
  await submit.click();
  await expect(containersPage.getByRole('button', { name: 'Download and start containers' })).toBeVisible({ timeout: 60 * 1000 });
  await expect(
    containersPage.getByText("Don't have a domain? Get a free one from deSEC"),
  ).toHaveCount(0);
});
