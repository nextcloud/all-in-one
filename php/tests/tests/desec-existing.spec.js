import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, logInToContainersPage, registerDesecDomainWithExistingAccount } from './helpers.js';

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

  // A verified account is pre-created in the mock so the password (login) path is exercised
  // directly, without the verification round-trip.
  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });
  const email = `existing-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const slug = `aio-existing-${Math.floor(Math.random() * 2147483647)}`;

  const containersPage = await logInToContainersPage(setupPage);
  await registerDesecDomainWithExistingAccount(containersPage, email, 'correct horse battery staple', slug);
  await expect(
    containersPage.getByText("Don't have a domain? Get a free one from deSEC"),
  ).toHaveCount(0);
});
