import { test, expect } from '@playwright/test';
import { DESEC_MOCK_URL, execInMastercontainer, logInToContainersPage, readMastercontainerConfig } from './helpers.js';

// Drives the real AIO interface through the full deSEC "register a free domain" flow
// against the local mock (php/tests/desec-mock.mjs). The mastercontainer's
// configuration.json must point desec_api_base / desec_update_url at the mock (see the
// Playwright CI workflow). The mock's control endpoint is reachable from the test runner
// on the host at DESEC_MOCK_URL (default http://localhost:8090).
//
// The domain is requested together with the account at signup: deSEC rejects an
// unavailable name right away (no account is created) and creates the domain when the
// user activates the account via the emailed link. After verification AIO only has to log
// in and confirm the domain exists.
//
// The registration runs inside a modal iframe (the /desec view): the multi-step
// register -> verify -> finish flow re-renders inside the iframe so the user can complete
// email verification without reloading the whole containers page. Only once the domain
// is fully registered does the iframe reload the parent page.
//
// This flow ends by registering a domain, which persists in configuration.json. It is
// therefore run as its own CI step; the deSEC state is reset (re-seeded) before the
// separate existing-account flow runs.

test('deSEC register -> verify -> domain flow', async ({ page: setupPage }) => {
  test.setTimeout(5 * 60 * 1000);

  // Start from a clean mock state so this test is independent of any other run.
  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });

  const containersPage = await logInToContainersPage(setupPage);

  const email = `e2e-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const slug = `aio-e2e-${Math.floor(Math.random() * 2147483647)}`;
  const takenSlug = `taken-${slug}`;

  // Somebody else already owns this name.
  await fetch(`${DESEC_MOCK_URL}/__control/seed-domain`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: `${takenSlug}.dedyn.io`, email: 'someone-else@example.com' }),
  });

  // Open the deSEC registration entry point and launch the modal.
  await containersPage.getByText("Don't have a domain? Get a free one from deSEC").click();
  await containersPage.getByRole('button', { name: 'Register free domain via deSEC' }).click();

  // The flow lives inside the modal iframe (the /desec view).
  const frame = containersPage.frameLocator('#desec-frame');

  // 1) Submit with a taken slug -> deSEC rejects the signup (mock 400 keyed "domain"), no
  //    account is created, and the user gets immediate feedback while staying on the form.
  await frame.locator('input[name="desec_email"]').fill(email);
  await frame.locator('input[name="desec_slug"]').first().fill(takenSlug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();
  await expect(frame.locator('.toast.error')).toContainText(`"${takenSlug}.dedyn.io" is not available`, { timeout: 8 * 1000 });
  await expect(frame.locator('input[name="desec_email"]')).toBeVisible();
  let mockState = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
  expect(mockState.accounts).not.toContain(email);

  // 2) Submit with a free slug -> a new account is "created" (mock 202) with the domain
  //    requested along with it, and AIO asks the user to verify their email. This is a
  //    normal (non-error) state transition: the iframe reloads into the
  //    awaiting-verification step inside the modal, which names the requested domain.
  await frame.locator('input[name="desec_slug"]').first().fill(slug);
  await frame.getByRole('button', { name: 'Register free domain via deSEC' }).click();
  await expect(frame.getByText('check your inbox')).toBeVisible({ timeout: 30 * 1000 });
  await expect(frame.getByText(`${slug}.dedyn.io`)).toBeVisible();
  await expect(
    frame.getByRole('button', { name: 'I have verified my email – finish setup' }),
  ).toBeVisible();

  // 3) Re-submit BEFORE verifying -> login still fails (mock 403) -> friendly hint shown.
  //    The error is a transient toast rendered inside the iframe.
  await frame.getByRole('button', { name: 'I have verified my email – finish setup' }).click();
  await expect(frame.locator('.toast.error')).toContainText('Could not log in to deSEC', { timeout: 8 * 1000 });

  // 4) Simulate the user clicking the verification link in their email. Activation creates
  //    the domain requested at signup.
  const verifyResponse = await fetch(`${DESEC_MOCK_URL}/__control/verify`, { method: 'POST' });
  expect(verifyResponse.status).toBe(200);
  mockState = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
  expect(mockState.domains[`${slug}.dedyn.io`]).toBe(email);

  // 5) Re-submit after verification -> login succeeds, AIO confirms the domain and the
  //    modal view reloads the parent page, where the deSEC entry point is gone and the
  //    container-start UI appears.
  await frame.getByRole('button', { name: 'I have verified my email – finish setup' }).click();
  await expect(containersPage.getByRole('button', { name: 'Download and start containers' })).toBeVisible({ timeout: 60 * 1000 });
  await expect(
    containersPage.getByText("Don't have a domain? Get a free one from deSEC"),
  ).toHaveCount(0);

  // 5) AIO must not keep the login token (it expires after 7 days and may manage tokens and
  //    domains). It exchanges it for a long-lived API token and logs the login token out, so
  //    the account is left with exactly one token: the API token that is stored in AIO's config.
  const tokensOf = async (address) => {
    const state = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
    return state.tokens.filter((t) => t.email === address);
  };
  const tokens = await tokensOf(email);
  expect(tokens.map((t) => t.kind)).toEqual(['api']);
  expect(readMastercontainerConfig().secrets.DESEC_TOKEN).toBe(tokens[0].token);
  // ... and that token is scoped to what the dynDNS update needs: the apex A and AAAA
  //     records of the registered domain, nothing else (deny-by-default policy first).
  const dynDnsPolicies = [
    { domain: null, subname: null, type: null, perm_write: false },
    { domain: `${slug}.dedyn.io`, subname: '', type: 'A', perm_write: true },
    { domain: `${slug}.dedyn.io`, subname: '', type: 'AAAA', perm_write: true },
  ];
  expect(tokens[0].policies).toEqual(dynDnsPolicies);

  // 6) Token renewal: an instance set up before the exchange existed still holds a login token
  //    that eventually expires. Simulate deSEC rejecting the stored token, then run the periodic
  //    IP update: for an AIO-created account the stored password allows logging in again, and
  //    the rejected token is replaced with a fresh API token.
  await fetch(`${DESEC_MOCK_URL}/__control/invalidate-tokens`, { method: 'POST' });
  const run = await execInMastercontainer(['php', '/var/www/docker-aio/php/src/Cron/UpdateDesecIp.php']);
  expect(run.exitCode, run.output).toBe(0);
  const renewed = (await tokensOf(email)).filter((t) => t.valid);
  expect(renewed.map((t) => t.kind)).toEqual(['api']);
  expect(renewed[0].token).not.toBe(tokens[0].token);
  expect(renewed[0].policies).toEqual(dynDnsPolicies);
  expect(readMastercontainerConfig().secrets.DESEC_TOKEN).toBe(renewed[0].token);
});
