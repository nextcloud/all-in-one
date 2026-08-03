import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { logInToContainersPage } from './helpers.js';

test('Restore instance', async ({ page: setupPage }) => {
  test.setTimeout(10 * 60 * 1000)

  // Load passwords from previous test
  const {
    initialNextcloudPassword,
    borgBackupLocation,
    borgBackupPassword,
  } = JSON.parse(readFileSync('test_data.json'))

  const containersPage = await logInToContainersPage(setupPage);

  // Reject example.com (requires enabled domain validation)
  await containersPage.locator('#domain').click();
  await containersPage.locator('#domain').fill('example.com');
  await containersPage.getByRole('button', { name: 'Submit domain' }).click();
  await expect(containersPage.locator('body')).toContainText('Domain does not point to this server or the reverse proxy is not configured correctly.', { timeout: 15 * 1000 });

  // Reject invalid backup location
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill('/tmp/test/aio-incorrect-path');
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill(borgBackupPassword);
  await containersPage.getByRole('button', { name: 'Submit location and encryption password' }).click()
  await containersPage.getByRole('button', { name: 'Test path and encryption' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Last test failed!', { timeout: 60 * 1000 });

  // Reject invalid backup password
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill(borgBackupLocation);
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill('foobar');
  await containersPage.getByRole('button', { name: 'Submit location and encryption password' }).click()
  await containersPage.getByRole('button', { name: 'Test path and encryption' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Last test failed!', { timeout: 60 * 1000 });

  // Accept correct backup location and password
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill(borgBackupLocation);
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill(borgBackupPassword);
  await containersPage.getByRole('button', { name: 'Submit location and encryption password' }).click()
  await containersPage.getByRole('button', { name: 'Test path and encryption' }).click();

  // Check integrity and restore backup
  await containersPage.getByRole('button', { name: 'Check backup integrity' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Last check successful!', { timeout: 5 * 60 * 1000 });
  containersPage.once('dialog', dialog => {
    console.log(`Dialog message: ${dialog.message()}`)
    dialog.accept()
  });
  await containersPage.getByRole('button', { name: 'Restore selected backup' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Backup container is currently running:', { timeout: 1 * 60 * 1000 });

  // Verify a successful backup restore
  await expect(containersPage.getByRole('main')).toContainText('Last restore successful!', { timeout: 3 * 60 * 1000 });
  await expect(containersPage.getByRole('main')).toContainText('⚠️ Container updates are available. Click on Stop containers and Start and update containers to update them. You should consider creating a backup first.');
  containersPage.once('dialog', dialog => {
    console.log(`Dialog message: ${dialog.message()}`)
    dialog.accept()
  });
  await containersPage.getByRole('button', { name: 'Start and update containers' }).click();
  await expect(containersPage.getByRole('link', { name: 'Open your Nextcloud ↗' })).toBeVisible({ timeout: 8 * 60 * 1000 });
  await expect(containersPage.getByRole('main')).toContainText(initialNextcloudPassword);

  // Verify that containers are all stopped
  await containersPage.getByRole('button', { name: 'Stop containers' }).click();
  await expect(containersPage.getByRole('button', { name: 'Start containers' })).toBeVisible({ timeout: 60 * 1000 });
});