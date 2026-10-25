<!-- SPDX-FileCopyrightText: 2026 Nextcloud contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Context Chat lifecycle tests

## Docker Compose runner

From the repository root, build the runner once and start the stack plus tests:

```sh
docker compose build playwright
docker compose run --rm playwright
```

The `playwright` service is in the optional `tests` profile. It is excluded from
normal `docker compose up`. Targeting it explicitly starts its dependencies,
waits for Nextcloud health checks and the authenticated Context Chat task-type
API, runs the tests once, and returns their exit status. Existing data is retained.
The usual `context-chat` and `ollama` profiles must be enabled, as in the supplied
`.env`. The runner does not install or register missing Nextcloud apps or models.

Reports and screenshots appear under `php/tests/playwright-report` and
`php/tests/test-results` on the host. To retain videos of failed browser tests:

```sh
docker compose run --rm -e NC_TEST_VIDEO=retain-on-failure playwright
```

Use `NC_TEST_VIDEO=on` to retain every browser video. API-only tests have no video.
The runner reaches nginx through the internal `nextcloud.local` network alias;
`NC_TEST_URL` defaults to `https://nextcloud.local`. A URL using `localhost` would
point at the runner itself. Custom URLs must be reachable from
the Compose network and accepted by Nextcloud's trusted-domain configuration.

The runner mounts the Docker socket to discover this project's Nextcloud
container, read its existing admin password, and obtain precise task errors.
This grants Docker access: run only trusted test code. It does not mount the
repository `.env` or use `docker compose` inside the image. Explicit
`NC_TEST_USER`/`NC_TEST_PASSWORD` values override the local account defaults.
The Playwright image and package-lock version must match (currently 1.56.1).

Build/discovery smoke check, without starting the application or changing data:

```sh
docker compose run --rm --no-deps --entrypoint node playwright \
  node_modules/playwright/cli.js test --config=playwright.context-chat.config.mjs --list
```

Runner checks: `node --test php/tests/context-chat/*.test.mjs`.

## Host runner

Run against an existing local Nextcloud with Assistant, Context Chat, its backend,
an LLM, and background jobs running. This is separate from the mastercontainer suite.
Requires Node.js 22 and Docker Compose access to this local stack.
The five serial tests use a random disposable text document;
they never modify Aurora-Test.docx. File mutations use WebDAV, chat uses the real
task API, and one browser check verifies the answer appears in Assistant.
This does not cover the upload menu, editor, selective-context picker, or DOCX/Collabora.
Failed tasks use a read-only `occ` bootstrap through Docker to distinguish a missing
document from a broken provider; Nextcloud 33 does not expose that error in the task API.

```sh
cd php/tests
npm ci
npx playwright install chromium
npm run test:context-chat
```

Defaults: `https://nextcloud.local`, user `admin`. The npm command reads the existing
container's admin password without printing or saving it. Override with `NC_TEST_URL`,
`NC_TEST_USER`, and `NC_TEST_PASSWORD` environment variables. Direct `npx playwright`
runs fall back to `NEXTCLOUD_PASSWORD` from the repository `.env`, which may differ
from the running container. No credentials are written to test artifacts.

For faster chat generation, run the normal worker in a separate terminal at the
repository root. It exits after 15 minutes. This does not trigger document indexing.

```sh
docker compose exec -T -u www-data nextcloud-aio-nextcloud \
  php occ taskprocessing:worker --timeout=900 --taskTypes=core:text2text --quiet
```

Tests allow up to ten minutes for automatic indexing after each mutation, polling
fresh tasks rather than sleeping for a fixed indexing delay. Each task also has a
ten-minute limit to allow for the cron cycle and CPU inference. No manual reindex,
queue injection, or forced cron execution is performed. A stalled restore fails
test 5. Earlier failures skip subsequent tests because this is one lifecycle.
Cleanup moves only this run's fixture to Trash and deletes its chat tasks.
Trash is never emptied; the fixture can be recovered for investigation.

Local verification on 2026-10-23: tests 1–4 passed. Test 5 restored the file
successfully, but repeated queries returned “No documents retrieved”. The run was
interrupted after three minutes of restore retries instead of exhausting the
ten-minute indexing window. Automatic restore indexing remains unverified.

```sh
npx playwright show-report playwright-report/context-chat
```
