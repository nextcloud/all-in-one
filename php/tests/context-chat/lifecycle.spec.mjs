// SPDX-FileCopyrightText: 2026 Nextcloud contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';
import { randomUUID } from 'node:crypto';
import { readTaskError } from './runtime.mjs';

// Serial by design: these tests follow one disposable document through its lifecycle.
test.describe.serial('Context Chat document lifecycle', () => {
  const name = `ContextChat-QA-${randomUUID()}.txt`;
  const user = process.env.NC_TEST_USER;
  const password = process.env.NC_TEST_PASSWORD;
  const dav = `/remote.php/dav/files/${encodeURIComponent(user)}/${name}`;
  const trash = `/remote.php/dav/trashbin/${encodeURIComponent(user)}`;
  const taskApi = '/ocs/v2.php/taskprocessing';
  const original = 'ORCHID-5831';
  const updated = 'MAPLE-9264';
  let api;
  let fileId;
  let initialAnswer;
  const tasks = [];

  async function checked(method, path, options = {}, statuses = [200]) {
    const response = await api.fetch(path, { method, ...options });
    expect(statuses, `${method} ${path}: HTTP ${response.status()}`).toContain(response.status());
    return response;
  }

  async function put(code) {
    const content = `Project Aurora automated QA brief.\nThe project owner is Mira.\nThe launch date is 18 November 2026.\nThe approved budget is 7420 credits.\nThe verification code is ${code}.\n`;
    await checked('PUT', dav, { data: content, headers: { 'Content-Type': 'text/plain' } }, [201, 204]);
    expect(await (await checked('GET', dav)).text()).toBe(content);
    const properties = await checked('PROPFIND', dav, {
      headers: { Depth: '0', 'Content-Type': 'application/xml' },
      data: '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>',
    }, [207]);
    fileId = (await properties.text()).match(/<(?:\w+:)?fileid>(\d+)</)?.[1];
    expect(fileId, 'WebDAV must return a file ID').toBeTruthy();
  }

  async function ask(prompt) {
    const scheduled = await checked('POST', `${taskApi}/schedule`, { data: {
      type: 'context_chat:context_chat', appId: 'assistant',
      input: { prompt, scopeType: 'source', scopeList: [`files__default: ${fileId}`], scopeListMeta: '' },
    } });
    const id = (await scheduled.json()).ocs.data.task.id;
    tasks.push(id);
    let task;
    try {
      await expect.poll(async () => {
        task = (await (await checked('GET', `${taskApi}/task/${id}`)).json()).ocs.data.task;
        return task.status;
      }, { message: `Context Chat task ${id} must finish`, timeout: 600_000, intervals: [2000, 5000] })
        .toMatch(/^STATUS_(SUCCESSFUL|FAILED|CANCELLED|UNKNOWN)$/);
    } catch (error) {
      await checked('POST', `${taskApi}/tasks/${id}/cancel`);
      throw error;
    }
    if (task.status === 'STATUS_FAILED') {
      // Nextcloud 33 omits internal errors from its task API. Read this task's
      // error only, so a provider outage cannot pass the deletion assertion.
      task.diagnosticError = await readTaskError(id);
    }
    return task;
  }

  async function eventuallyAnswer(code) {
    let result;
    await expect(async () => {
      result = await ask('What is the verification code in the provided document? Reply with only the code.');
      expect(result.status, JSON.stringify(result)).toBe('STATUS_SUCCESSFUL');
      expect(result.output.output).toContain(code);
      expect(JSON.stringify(result.output)).toContain(`files__default: ${fileId}`);
    }).toPass({ timeout: 10 * 60_000, intervals: [15_000, 30_000] });
    return result;
  }

  test.beforeAll(async ({ playwright, baseURL }) => {
    expect(password, 'Set NC_TEST_PASSWORD or NEXTCLOUD_PASSWORD in the repository .env').toBeTruthy();
    api = await playwright.request.newContext({
      baseURL, ignoreHTTPSErrors: true,
      httpCredentials: { username: user, password, send: 'always' },
      extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
    });
    const types = (await (await checked('GET', `${taskApi}/tasktypes`)).json()).ocs.data.types;
    expect(types).toHaveProperty('context_chat:context_chat');
    console.log(`Disposable test file: /${name}`);
  });

  test.afterAll(async () => {
    if (!api) return;
    try {
      // Only this run's random fixture is touched; deletion remains recoverable in Trash.
      await checked('DELETE', dav, {}, [204, 404]);
      for (const id of tasks) await checked('DELETE', `${taskApi}/task/${id}`, {}, [200, 404]);
    } finally {
      await api.dispose();
    }
  });

  test('1. Add a document and wait until Context Chat retrieves it', async () => {
    await put(original);
    initialAnswer = await eventuallyAnswer(original);
  });

  test('2. Display the correct document answer and citation in Assistant', async ({ page }) => {
    expect(initialAnswer.output.output).toContain(original);
    await page.goto('/login');
    await page.locator('#user').fill(user);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /^(Log|Sign) in$/ }).click();
    await expect(page).not.toHaveURL(/\/login/);
    await page.goto(`/apps/assistant/task/view/${initialAnswer.id}`);
    await expect(page.locator('#cc-output-text-input')).toContainText(original);
    await expect(page.locator(`a[href$="/f/${fileId}"]`).first()).toBeVisible();
  });

  test('3. Edit the document and retrieve the updated code', async () => {
    await put(updated);
    const result = await eventuallyAnswer(updated);
    expect(result.output.output).not.toContain(original);
  });

  test('4. Delete the document and stop retrieving its content', async () => {
    await checked('DELETE', dav, {}, [204]);
    await checked('GET', dav, {}, [404]);
    await expect(async () => {
      const result = await ask('What is the verification code in the provided document?');
      // A targeted query for a removed source is expected to fail with no documents.
      // Authentication, provider failures, and cancellations must never count as success.
      expect(result.status).toBe('STATUS_FAILED');
      expect(result.userFacingErrorMessage ?? result.diagnosticError).toMatch(/no documents retrieved/i);
      expect(JSON.stringify(result.output ?? {})).not.toContain(updated);
    }).toPass({ timeout: 10 * 60_000, intervals: [15_000, 30_000] });
  });

  test('5. Restore the document and retrieve it without manual reindexing', async ({ baseURL }) => {
    const response = await checked('PROPFIND', `${trash}/trash`, {
      headers: { Depth: '1', 'Content-Type': 'application/xml' },
      data: '<d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>',
    }, [207]);
    const hrefs = [...(await response.text()).matchAll(/<(?:\w+:)?href>([^<]+)</g)].map(match => match[1]);
    const path = hrefs.find(href => decodeURIComponent(href).split('/').pop().startsWith(`${name}.d`));
    expect(path, 'The exact test document must be present in Trash').toBeTruthy();
    await checked('MOVE', path, { headers: {
      Destination: new URL(`${trash}/restore/${name}`, baseURL).href,
    } }, [201, 204]);
    expect(await (await checked('GET', dav)).text()).toContain(updated);
    // Restore may assign a different file ID. Always resolve it again.
    const properties = await checked('PROPFIND', dav, {
      headers: { Depth: '0', 'Content-Type': 'application/xml' },
      data: '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>',
    }, [207]);
    fileId = (await properties.text()).match(/<(?:\w+:)?fileid>(\d+)</)?.[1];
    expect(fileId).toBeTruthy();
    await eventuallyAnswer(updated);
  });
});
