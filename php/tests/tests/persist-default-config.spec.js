import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('Initial setup persists default container selections', async ({ page: setupPage }) => {
  test.setTimeout(10 * 60 * 1000);

  await setupPage.goto('./setup');

  await expect.poll(() => {
    try {
        return JSON.parse(readFileSync('/mnt/docker-aio-config/data/configuration.json', 'utf8'));
    } catch {
        return null;
    }
  }, { timeout: 30_000 }).toMatchObject({
    officeSuite: 'eurooffice',
    isClamavEnabled: false,
    isTalkEnabled: true,
    isTalkRecordingEnabled: false,
    isImaginaryEnabled: true,
    isFulltextsearchEnabled: false,
    isDockerSocketProxyEnabled: false,
    isHarpEnabled: false,
    isWhiteboardEnabled: true,
  });
});
