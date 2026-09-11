# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this checkout is

This is a fork of [nextcloud/all-in-one](https://github.com/nextcloud/all-in-one) (the
"manual install" method) used two ways at once:

1. **A live local deployment** — `docker-compose.yml` (not the upstream `compose.yaml`,
   which is just AIO's own reference/template file) brings up a real Nextcloud stack on
   this Mac: Talk, Talk Recording, Collabora, ClamAV, Imaginary, Fulltextsearch,
   Whiteboard, James (mail), Ollama, context-chat, and a self-hosted Ceph cluster for S3
   primary storage. See `SETUP.md` for the full local setup (TLS cert generation, cert
   trust distribution to containers, env vars) and `ARCHITECTURE.md` for the network
   diagram and the gotchas baked into this specific setup (image pinning, `.local` mDNS
   penalty, Docker Desktop mount restrictions, etc.) — both are living docs, read them
   before changing compose/container config.
2. **A branding fork** ("BharatSuite") — `nextcloud-custom-apps/nc_aio_tools` is a custom
   Nextcloud app, not part of upstream AIO, that reskins the instance and adds a
   per-instance user cap. PRs against this work target `anirbandas18/all-in-one`, not
   upstream `nextcloud/all-in-one`.

Everything under `Containers/`, `community-containers/`, `app/`, `php/`, `manual-install/`,
`nextcloud-aio-helm-chart/`, and the top-level `*.md` docs (other than `SETUP.md` and
`ARCHITECTURE.md`) is upstream AIO source — treat changes there as contributions to the
real open-source project and follow `AGENTS.md` / `CONTRIBUTING.md` (below).

## Commands

**PHP (mastercontainer app, `php/`)**
```sh
cd php
composer install
composer run lint              # php -l over src/*.php
composer run psalm             # static analysis (psalm --threads=1)
composer run psalm:strict      # psalm --show-info=true
composer run lint:twig         # twig-linter over templates/
composer run php-deprecation-detector
composer run dev               # php -S localhost:8080 -t public
```
CI mirrors these exactly (`.github/workflows/lint-php.yml`, `psalm.yml`, `twig-lint.yml`,
`php-deprecation-detector.yml`) — run them before pushing changes under `php/`.

**Playwright E2E (mastercontainer setup flows, `php/tests/`)**
```sh
./php/tests/run.sh ./php/tests/tests/<spec-file>.spec.js
```
Each spec spins up its own containers via `php/tests/compose.yaml`; see
`.github/workflows/playwright-on-push.yml` for the full list of specs CI runs
(initial-setup, persist-default-config, restore-instance, desec-register/existing/
existing-slug) and any spec-specific env vars (e.g. `SKIP_DOMAIN_VALIDATION=true`).

**Local stack (docker-compose.yml, this deployment only)**
```sh
docker compose up -d
docker compose logs -f <service>
docker exec -it nextcloud-aio-nextcloud php occ <command>
docker exec -it nextcloud-aio-database psql -U oc_nextcloud nextcloud_database
```
Enabled containers are controlled by two independent knobs — a `--profile`/
`COMPOSE_PROFILES` entry AND a `*_ENABLED=yes` env var; a container can be running
without its Nextcloud app being active if only one is set. See `.env.example` for every
variable and `SETUP.md` for the cert-trust and mount setup that has to happen before
`up` succeeds on macOS + Docker Desktop.

**nc_aio_tools custom app** — plain PHP (no build step) and vanilla JS/CSS assets (no
bundler, no npm). Edit files directly under `nextcloud-custom-apps/nc_aio_tools/` and
restart/reload the `nextcloud` container to pick up PHP changes; JS/CSS are served
as static assets. There is no unit test suite for this app — verify changes against
the live local instance.

**Other container images (`Containers/{name}/`)** — build and test locally per
`develop.md` ("How to locally build and test changes to other containers"); requires
switching the mastercontainer to the `develop` channel first.

## Architecture

### Mastercontainer app (`php/`)
A Slim 4 app (`slim/slim`, `php-di/slim-bridge`) that serves the AIO admin interface
(the "AIO interface" you access on port 8080) and drives the Docker socket via
`docker-socket-proxy` to manage all the other containers — pulling images, creating/
starting/stopping them, wiring their env vars from the values entered in that UI.
`php/containers.json` (validated against `php/containers-schema.json`) is the source of
truth for what containers exist, their images, and how they relate to each other; the UI
and orchestration logic both read from it. Twig templates live in `php/templates/`.

### The `nextcloud` app (`app/`)
A trivial Nextcloud app (`AllInOne` namespace) whose only job is an admin-settings link
into the AIO interface. Gets baked into the `nextcloud` container image.

### `nc_aio_tools` (`nextcloud-custom-apps/nc_aio_tools/`) — BharatSuite layer
Bind-mounted into the `nextcloud` container as a custom app (not vendored/baked into the
image), separate from the two apps above. Two concerns, wired through
`lib/AppInfo/Application.php`:
- **User cap** (`EnforceUserLimitListener` + `UserLimitService`): blocks user creation
  once the `NC_USER_LIMIT` env var is reached. Hooks `BeforeUserCreatedEvent`, which is
  the single code path shared by the web UI, `occ user:add`, and the provisioning API —
  there is no separate enforcement per entry point. `UserCountAuditListener` keeps the
  count in sync on create/delete. `UsersPageAssetsListener` shows remaining free slots
  in the Settings > Users page.
- **Branding** (`BrandingAssetsListener`): the theme layer. Registered on *two* separate
  events — `BeforeTemplateRenderedEvent` for app pages and `BeforeLoginTemplateRenderedEvent`
  for the login screen, which renders through a different (guest) template pipeline and
  needs its own hook or it stays unstyled. Injects `bharatsuite.css` (rewrites Nextcloud's
  own CSS custom properties, so load order doesn't matter) plus several self-guarding JS
  files (`dashboard-panels.js`, `assistant-fullscreen.js`, `files-workspaces.js`,
  `files-detail-rail.js`, `login-marketing.js`) that each no-op unless their target DOM
  element exists, so they're safe to load unconditionally on every page. Also emits the
  logo `<style>` override (two SVG variants, light/dark ground) and an optional
  "residency" badge driven by the `BRANDING_RESIDENCY_LABEL` env var.

**Design source of truth for BharatSuite**: the rendered screens in
`exports/png/` inside the separate "BharatSuite UI Mockups" project (an additional
working directory, not part of this repo) — not the `.dc.html` design-system token files
in that same project. When in doubt about a visual detail, match the PNG, not the tokens.

### Container images (`Containers/`, `community-containers/`)
Each subdirectory is a Dockerfile (+ supporting scripts/configs) for one AIO-managed
container. `Containers/` holds the "official" containers AIO ships by default;
`community-containers/` holds optional ones users can enable individually. Adding a new
community container means adding both its directory here and an entry in
`php/containers.json`.

## Contribution rules (upstream AIO code)

This repo carries organization-wide AI-agent policy in `AGENTS.md` — read it in full
before generating commits or PRs that touch anything outside the local-only files
(`SETUP.md`, `ARCHITECTURE.md`, `.env`, `docker-compose.yml`,
`nextcloud-custom-apps/nc_aio_tools/`). Key points, condensed:

- Conventional Commits format: `<type>(<scope>): <description>`, with an
  `Assisted-by: AGENT_NAME:MODEL_VERSION` trailer on every AI-assisted commit. Scope
  matches the affected component (`AIO-interface`, `Mastercloud`, `App`,
  `community-containers`, etc.).
- Never add `Signed-off-by` — only the human contributor certifies the DCO.
- Never open issues, PRs, or post review comments autonomously; every contribution is
  human-submitted and human-reviewed.
- New/changed logic needs unit test coverage; PRs touching `php/` without tests won't be
  accepted. New features need manual testing on a live instance before submission —
  providing test steps is not a substitute.
- Every new file needs an SPDX header (`SPDX-FileCopyrightText` / `SPDX-License-Identifier:
  AGPL-3.0-or-later`, MIT for a few docs/workflow files — check the existing header in
  sibling files).
- Keep PRs focused on one concern; don't mix in incidental refactors or unrelated files.
- Report security vulnerabilities via HackerOne, never as a GitHub issue.
