import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { DESEC_MOCK_URL, execInMastercontainer, logInToContainersPage, registerDesecDomainWithExistingAccount } from './helpers.js';

// Checks that dyndns updates rejected by deSEC (here: the domain was deleted at desec.io) are
// not retried on every run of the IP updater, but only after an increasing delay, and that a
// successful update afterwards ends the backoff.
//
// The updater is run on demand in the mastercontainer. Its background loop may also run during
// the test; since it is subject to the same backoff, the assertions hold either way.

const runUpdater = () => execInMastercontainer(['php', '/var/www/docker-aio/php/src/Cron/UpdateDesecIp.php']);

// Moves the last rejection a day back in time, as if the backoff delay had passed.
const elapseBackoff = () => execInMastercontainer(['php', '-r', `
  require '/var/www/docker-aio/php/vendor/autoload.php';
  $config = \\AIO\\DependencyInjection::GetContainer()->get(\\AIO\\Data\\ConfigurationManager::class);
  $backoff = $config->desecIpUpdateBackoff;
  $backoff['last_failed_at'] -= 86400;
  $config->desecIpUpdateBackoff = $backoff;
`]);

const readBackoff = () => JSON.parse(readFileSync('/mnt/docker-aio-config/data/configuration.json', 'utf8')).desec_ip_update_backoff;

const updatesWithStatus = async (domain, status) => {
  const { updates } = await (await fetch(`${DESEC_MOCK_URL}/__control/state`)).json();
  return updates.filter(update => update.hostname === domain && update.status === status);
};

const postToMock = (path, body) => fetch(`${DESEC_MOCK_URL}${path}`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(body),
});

test('deSEC IP updates rejected by deSEC are backed off', async ({ page: setupPage }) => {
  test.setTimeout(5 * 60 * 1000);

  await fetch(`${DESEC_MOCK_URL}/__control/reset`, { method: 'POST' });
  const email = `backoff-${Math.floor(Math.random() * 2147483647)}@example.com`;
  const slug = `aio-backoff-${Math.floor(Math.random() * 2147483647)}`;
  const domain = `${slug}.dedyn.io`;

  const containersPage = await logInToContainersPage(setupPage);
  await registerDesecDomainWithExistingAccount(containersPage, email, 'correct horse battery staple', slug);

  // Registration publishes the current address once.
  expect(await updatesWithStatus(domain, 200)).toHaveLength(1);

  // The domain and with it its records are deleted at deSEC, so an update is due.
  await postToMock('/__control/delete-domain', { name: domain });

  await runUpdater();
  expect(await updatesWithStatus(domain, 404)).toHaveLength(1);
  expect(readBackoff()).toMatchObject({ failed_attempts: 1 });

  // Further runs within the backoff delay do not contact deSEC.
  await runUpdater();
  await runUpdater();
  expect(await updatesWithStatus(domain, 404)).toHaveLength(1);

  // Once the delay has passed, the update is retried and rejected again.
  await elapseBackoff();
  await runUpdater();
  expect(await updatesWithStatus(domain, 404)).toHaveLength(2);
  expect(readBackoff()).toMatchObject({ failed_attempts: 2 });

  // After the domain is re-created at deSEC, the next attempt succeeds and ends the backoff.
  await postToMock('/__control/seed-domain', { name: domain, email });
  await elapseBackoff();
  await runUpdater();
  expect(await updatesWithStatus(domain, 200)).toHaveLength(2);
  expect(readBackoff()).toEqual([]);
});
