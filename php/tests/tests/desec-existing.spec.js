import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, execInMastercontainer, logInToContainersPage, readMastercontainerConfig } from './helpers.js';

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

  // The login token is exchanged for a long-lived API token here as well; only that one
  // remains on the account and it is what AIO stores.
  const tokensOf = async (address) => {
    const state = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
    return state.tokens.filter((t) => t.email === address);
  };
  const tokens = await tokensOf(email);
  expect(tokens.map((t) => t.kind)).toEqual(['api']);
  expect(readMastercontainerConfig().secrets.DESEC_TOKEN).toBe(tokens[0].token);
  expect(tokens[0].policies).toEqual([
    { domain: null, subname: null, type: null, perm_write: false },
    { domain: `${slug}.dedyn.io`, subname: '', type: 'A', perm_write: true },
    { domain: `${slug}.dedyn.io`, subname: '', type: 'AAAA', perm_write: true },
  ]);

  // The user's own password is never stored, so if deSEC rejects the token it cannot be
  // renewed automatically: the IP update logs how to fix it and leaves the config untouched.
  await fetch(`${DESEC_MOCK_URL}/__control/invalidate-tokens`, { method: 'POST' });
  const run = await execInMastercontainer(['php', '/var/www/docker-aio/php/src/Cron/UpdateDesecIp.php']);
  expect(run.exitCode, run.output).toBe(0);
  expect(run.output).toContain('cannot be renewed automatically');
  expect((await tokensOf(email)).filter((t) => t.valid)).toHaveLength(0);
  expect(readMastercontainerConfig().secrets.DESEC_TOKEN).toBe(tokens[0].token);
});
