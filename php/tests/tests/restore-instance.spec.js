import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('Restore instance', async ({ page: setupPage }) => {
  test.setTimeout(10 * 60 * 1000)

  // Load passwords from previous test
  const {
    initialNextcloudPassword,
    borgBackupLocation,
    borgBackupPassword,
  } = JSON.parse(readFileSync('test_data.json'))

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

  // Reject example.com (requires enabled domain validation)
  await containersPage.locator('#domain').click();
  await containersPage.locator('#domain').fill('example.com');
  await containersPage.getByRole('button', { name: 'Submit domain' }).click();
  await expect(containersPage.locator('body')).toContainText('Domain does not point to this server or the reverse proxy is not configured correctly.');

  // Reject invalid backup location
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill('/mnt/test/aio-incorrect-path');
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill(borgBackupPassword);
  // Clear remote path field for local backup test
  await containersPage.locator('input[name="borg_remote_path"]').fill('');
  await containersPage.getByRole('button', { name: 'Submit location and encryption password' }).click()
  await containersPage.getByRole('button', { name: 'Test path and encryption' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Last test failed!', { timeout: 60 * 1000 });

  // Reject invalid backup password
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill(borgBackupLocation);
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill('foobar');
  // Clear remote path field for local backup test
  await containersPage.locator('input[name="borg_remote_path"]').fill('');
  await containersPage.getByRole('button', { name: 'Submit location and encryption password' }).click()
  await containersPage.getByRole('button', { name: 'Test path and encryption' }).click();
  await expect(containersPage.getByRole('main')).toContainText('Last test failed!', { timeout: 60 * 1000 });

  // Accept correct backup location and password
  await containersPage.locator('#borg_restore_host_location').click();
  await containersPage.locator('#borg_restore_host_location').fill(borgBackupLocation);
  await containersPage.locator('#borg_restore_password').click();
  await containersPage.locator('#borg_restore_password').fill(borgBackupPassword);
  // Clear remote path field for local backup test
  await containersPage.locator('input[name="borg_remote_path"]').fill('');
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
  await expect(containersPage.getByRole('link', { name: 'Open your Nextcloud ↗' })).toBeVisible({ timeout: 5 * 60 * 1000 });
  await expect(containersPage.getByRole('main')).toContainText(initialNextcloudPassword);

  // Verify that containers are all stopped
  await containersPage.getByRole('button', { name: 'Stop containers' }).click();
  await expect(containersPage.getByRole('button', { name: 'Start containers' })).toBeVisible({ timeout: 60 * 1000 });
});