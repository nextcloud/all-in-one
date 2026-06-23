# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Project Is

Nextcloud All-in-One (AIO) is a self-hosted Nextcloud distribution that orchestrates a suite of Docker containers via a PHP web interface (the "mastercontainer"). It manages container lifecycle, updates, backups, and optional services through a browser UI running on port 8080/8443.

## PHP Backend (primary development surface)

All application logic lives in `php/`. The backend is a **Slim 4** microframework app that controls Docker containers via the Docker socket.

### Commands (run from `php/`)

```bash
composer install                        # install dependencies
SKIP_DOMAIN_VALIDATION=true composer run dev  # start dev server at http://localhost:8080
composer run psalm                      # static analysis
composer run psalm:strict               # strict mode
composer run psalm:update-baseline      # update psalm-baseline.xml
composer run lint                       # PHP syntax check
composer run lint:twig                  # Twig template syntax check
composer run php-deprecation-detector   # check PHP 8.5 compatibility
```

Dev server requires a running `nextcloud-aio-mastercontainer` container for Docker socket access:
```bash
docker run --rm --name nextcloud-aio-mastercontainer \
  --volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
  --volume /var/run/docker.sock:/var/run/docker.sock \
  ghcr.io/nextcloud-releases/all-in-one:latest
```

### PHP source layout (`php/src/`)

| Directory/File | Role |
|---|---|
| `Controller/` | Slim route handlers — `DockerController`, `ConfigurationController`, `LoginController` |
| `Docker/` | Docker API wrappers — `DockerActionManager`, `DockerHubManager`, `GitHubContainerRegistryManager` |
| `Container/` | Container model and state |
| `Auth/` | Session/authentication middleware |
| `Cron/` | Scheduled task runners |
| `Helper/` | Utility classes |
| `Middleware/` | Slim middleware (auth, CSRF, etc.) |
| `Twig/` | Template extensions |
| `ContainerDefinitionFetcher.php` | Reads `containers.json` to build the container list |
| `DependencyInjection.php` | DI container wiring |

Container definitions live in `php/containers.json` (schema: `php/containers-schema.json`). This is the single source of truth for which containers exist and their metadata.

## Container Images

Each service has a Dockerfile in `Containers/<name>/`. The base image is Alpine Linux.

### Build a container locally

```bash
# mastercontainer
docker buildx build --file Containers/mastercontainer/Dockerfile \
  --tag ghcr.io/nextcloud-releases/all-in-one:develop --load .

# nextcloud container
docker buildx build --file Containers/nextcloud/Dockerfile \
  --tag ghcr.io/nextcloud-releases/aio-nextcloud:develop --load .

# any other container (context = its directory)
docker buildx build --file Containers/<name>/Dockerfile \
  --tag ghcr.io/nextcloud-releases/aio-<name>:develop --load Containers/<name>
```

After building, bypass the update check by appending `?bypass_container_update` to the AIO URL (e.g. `https://localhost:8080/containers?bypass_container_update`).

## E2E Tests

Playwright tests live in `php/tests/`. They require a full container stack.

```bash
cd php/tests && npx playwright test
```

CI runs these via `.github/workflows/playwright-on-push.yml`.

## CI Checks

The following checks run in CI and must pass before merging:

| Workflow | Check |
|---|---|
| `psalm.yml` | Psalm static analysis |
| `lint-php.yml` | PHP syntax |
| `twig-lint.yml` | Twig syntax |
| `docker-lint.yml` | Dockerfile linting |
| `shellcheck.yml` | Shell script linting (config: `.shellcheckrc`) |
| `lint-yaml.yml` | YAML validation |
| `json-validator.yml` | JSON validation |
| `fail-on-prerelease.yml` | No prerelease dependency versions |

## Adding a Community Container

Community-contributed optional services live in `community-containers/`. Each is a directory with a `Dockerfile` and metadata. See existing entries for the expected structure.

## Architecture Notes

- The mastercontainer owns the Docker socket and spawns/stops all other AIO containers. The PHP app talks to Docker via socket, never via Docker Compose.
- Container channels: `develop` → `beta` → `latest`. Promotion is manual via GitHub Actions workflows in the `nextcloud-releases/all-in-one` repo.
- `compose.yaml` in the root is the **reference** compose file for end users — it is not used by the mastercontainer itself.
- Database: PostgreSQL (container `postgresql`). Connect during dev: `docker exec -it nextcloud-aio-database psql -U oc_nextcloud nextcloud_database`
