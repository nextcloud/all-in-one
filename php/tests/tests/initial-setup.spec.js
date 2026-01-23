import { test, expect } from '@playwright/test';
import { writeFileSync } from 'node:fs'

test('Initial setup', async ({ page: setupPage }) => {
  test.setTimeout(10 * 60 * 1000)

  // Extract initial password
  await setupPage.goto('./setup');
  const password = await setupPage.locator('#initial-password').innerText()
  const containersPagePromise = setupPage.waitForEvent('popup');
  await setupPage.getByRole('link', { name: 'Open Nextcloud AIO login ↗' }).click();
  const containersPage = await containersPagePromise;

  // Log in and wait for redirect
  await containersPage.locator('#master-password').click();
  await containersPage.locator('#master-password').fill(password);
  await containersPage.getByRole('button', { name: 'Log in' }).click();
  await containersPage.waitForURL('./containers');

  // Reject IP addresses
  await containersPage.locator('#domain').click();
  await containersPage.locator('#domain').fill('1.1.1.1');
  await containersPage.getByRole('button', { name: 'Submit domain' }).click();
  await expect(containersPage.locator('body')).toContainText('Please enter a domain and not an IP-address!');

  // Accept example.com (requires disabled domain validation)
  await containersPage.locator('#domain').click();
  await containersPage.locator('#domain').fill('example.com');
  await containersPage.getByRole('button', { name: 'Submit domain' }).click();

  // Disable all additional containers
  await containersPage.locator('#talk').uncheck();
  await containersPage.getByRole('checkbox', { name: 'Whiteboard' }).uncheck();
  await containersPage.getByRole('checkbox', { name: 'Imaginary' }).uncheck();
  await containersPage.getByText('Disable office suite').click();
  await containersPage.getByRole('button', { name: 'Save changes' }).last().click();
  await expect(containersPage.locator('#talk')).not.toBeChecked()
  await expect(containersPage.getByRole('checkbox', { name: 'Whiteboard' })).not.toBeChecked()
  await expect(containersPage.getByRole('checkbox', { name: 'Imaginary' })).not.toBeChecked()
  await expect(containersPage.locator('#office-none')).toBeChecked()

  // Reject invalid time zones
  await containersPage.locator('#timezone').click();
  await containersPage.locator('#timezone').fill('Invalid time zone');
  containersPage.once('dialog', dialog => {
    console.log(`Dialog message: ${dialog.message()}`)
    dialog.accept()
  });
  await containersPage.getByRole('button', { name: 'Submit timezone' }).click();
  await expect(containersPage.locator('body')).toContainText('The entered timezone does not seem to be a valid timezone!')

  // Accept valid time zone
  await containersPage.locator('#timezone').click();
  await containersPage.locator('#timezone').fill('Europe/Berlin');
  containersPage.once('dialog', dialog => {
    console.log(`Dialog message: ${dialog.message()}`)
    dialog.accept()
  });
  await containersPage.getByRole('button', { name: 'Submit timezone' }).click();

  // Start containers and wait for starting message
  await containersPage.getByRole('button', { name: 'Download and start containers' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Containers are currently starting.', { timeout: 5 * 60 * 1000 });
  await expect(containersPage.getByRole('link', { name: 'Open your Nextcloud ↗' })).toBeVisible({ timeout: 3 * 60 * 1000 });
  await expect(containersPage.getByRole('link', { name: 'Open your Nextcloud ↗' })).toHaveAttribute('href', 'https://example.com');

  // Extract initial nextcloud password
  await expect(containersPage.getByRole('main')).toContainText('Initial Nextcloud password:')
  const initialNextcloudPassword = await containersPage.locator('#initial-nextcloud-password').innerText();

  // Set backup location and create backup
  const borgBackupLocation = `/mnt/test/aio-${Math.floor(Math.random() * 2147483647)}`
  await containersPage.locator('#borg_backup_host_location').click();
  await containersPage.locator('#borg_backup_host_location').fill(borgBackupLocation);
  await containersPage.getByRole('button', { name: 'Submit backup location' }).click();
  containersPage.once('dialog', dialog => {
    console.log(`Dialog message: ${dialog.message()}`)
    dialog.accept()
  });
  await containersPage.getByRole('button', { name: 'Create backup' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Backup container is currently running:', { timeout: 3 * 60 * 1000 });
  await expect(containersPage.getByRole('main')).toContainText('Last backup successful on', { timeout: 3 * 60 * 1000 });
  await containersPage.getByText('Click here to reveal all backup options').click();
  await expect(containersPage.locator('#borg-backup-password')).toBeVisible();
  const borgBackupPassword = await containersPage.locator('#borg-backup-password').innerText();

  // Assert that all containers are stopped
  await expect(containersPage.getByRole('button', { name: 'Start containers' })).toBeVisible();

  // Save passwords for restore backup test
  writeFileSync('test_data.json', JSON.stringify({
    initialNextcloudPassword,
    borgBackupLocation,
    borgBackupPassword,
  }))
});
