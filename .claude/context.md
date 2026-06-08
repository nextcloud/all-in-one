# Euro-Office AIO — Work Context

## Goal
Ship Nextcloud AIO with **EuroOffice as the default document editor** (replacing Collabora).
This is the euro-office-public fork of `nextcloud/all-in-one`. Current phase: **testing**.

---

## What We've Done

### `php/src/Data/ConfigurationManager.php`
- `isEuroofficeEnabled` default: `false` → `true` (+ added missing `(bool)` cast)
- `isCollaboraEnabled` default: `true` → `false`
- `getNextcloudStartupApps()`: added `eurooffice` to the default app list
- Added `performMigrations()`: one-time migration (flag `eurooffice_default_migration_v1`) that forces existing installs to switch to EuroOffice on next mastercontainer start

### `php/public/index.php`
- Calls `$configurationManager->performMigrations()` at bootstrap (before route handling)

### `Containers/apache/Caddyfile` — EuroOffice SDK 404 fix
**Root cause:** EuroOffice nginx maps `$http_x_forwarded_prefix` → `$the_prefix` and uses it to construct SDK asset URLs (e.g. `/eurooffice/sdkjs/...`). Without the header, `$the_prefix` is empty so the browser requests `/sdkjs/...` which Caddy routes to Nextcloud → 404.

**Fix:** Send `X-Forwarded-Prefix` as a separate header instead of appending the path to `X-Forwarded-Host`:
```
route /eurooffice/* {
    uri strip_prefix /eurooffice
    reverse_proxy {$EUROOFFICE_HOST}:80 {
        header_up X-Forwarded-Host {http.request.hostport}
        header_up X-Forwarded-Prefix /eurooffice
    }
}
```
Note: OnlyOffice block uses the old pattern (`X-Forwarded-Host host/onlyoffice`) and was left unchanged — OO worked with it. This fix is also needed upstream in `nextcloud/all-in-one`.

### `Containers/nextcloud/entrypoint.sh` — EuroOffice internal URL and preview fix

**Root cause summary:** Containers resolve `nextcloud.test` → `127.0.0.1` (Docker reads host `/etc/hosts` which has `127.0.0.1 nextcloud.test`). So any container→container call using the public domain fails. This affects:
1. NC → EuroOffice converter calls (preview generation, document editing callbacks)
2. EuroOffice → NC file download calls (EuroOffice needs to fetch the document to convert it)

Three fixes baked into the entrypoint EuroOffice block:

**Fix 1 — DocumentServerInternalUrl** (NC → EuroOffice, bypasses public domain):
```bash
php /var/www/html/occ config:app:set eurooffice DocumentServerInternalUrl --value="http://$EUROOFFICE_HOST:80/"
```
- Must have trailing slash — `getDocumentServerInternalUrl()` returns raw value, concatenated directly with `"converter"` in `DocumentService::getConvertedUri()` (line 124)
- `$EUROOFFICE_HOST` is the Docker container name (e.g. `nextcloud-aio-eurooffice`) before it gets rewritten to `$NC_DOMAIN/eurooffice`

**Fix 2 — StorageUrl** (EuroOffice → NC, bypasses public domain):
```bash
APACHE_CONTAINER_HOST=$(echo "$EUROOFFICE_HOST" | sed 's/-eurooffice$/-apache/')
php /var/www/html/occ config:app:set eurooffice StorageUrl --value="http://$APACHE_CONTAINER_HOST.nextcloud-aio:23973/"
```
- Port 23973 matches Caddy's `http://{$APACHE_HOST}.nextcloud-aio:23973` server block (the WOPI/callback port), which routes to Nextcloud at `127.0.0.1:8000`
- Port 11000 does NOT work even though it's open — Caddy rejects requests where `Host: nextcloud-aio-apache:11000` doesn't match the `nextcloud.test:11000` binding
- Must have trailing slash — `getStorageUrl()` returns raw value; `str_replace("https://nextcloud.test/", storageUrl, $fileUrl)` strips the `/` from the origin, producing `...23973apps/` (broken URL) if StorageUrl has no trailing slash

**Fix 3 — enabledPreviewProviders** (EuroOffice class missing from explicit allowlist):
```bash
if ! php /var/www/html/occ config:system:get enabledPreviewProviders | grep -q "Eurooffice"; then
    php /var/www/html/occ config:system:set enabledPreviewProviders 50 --value="OCA\Eurooffice\Preview"
fi
```
- NC has `enabledPreviewProviders` explicitly set; `registerPreviewProvider()` in `Application.php` is insufficient — the class must also be in this allowlist or the provider is silently skipped
- FQCN: `OCA\Eurooffice\Preview` (namespace `OCA\Eurooffice`, class `Preview`)
- occ stores with `\\` in config.php (PHP string literal for single backslash) — this is correct
- Index 50 chosen to avoid collision with AIO's seeded range (indices 1–7 on fresh install, 23 for Imaginary). Previous `wc -l` approach would have produced index 7 on a standard install, silently overwriting the Krita preview provider.

### Local `aio-apache:beta` image
Built from repo to test the Caddyfile fix:
```bash
docker buildx build --file Containers/apache/Dockerfile --tag ghcr.io/nextcloud-releases/aio-apache:beta --load Containers/apache
```
AIO uses the `:beta` tag in this test environment; local image takes precedence over remote pull.

After the build, `/Caddyfile` inside the image still had the old content (build context was captured before the edit was saved). The runtime `/tmp/Caddyfile` was manually correct. Fixed with:
```bash
docker exec nextcloud-aio-apache caddy reload --config /tmp/Caddyfile
```

---

## Status as of 2026-06-08

- ✅ **EuroOffice documents open** — Caddyfile X-Forwarded-Prefix fix working, SDK assets load correctly
- ✅ **Preview generation works** — preview PNG files stored at `/mnt/ncdata/appdata_*/preview/.../107/` (confirmed valid)
- ⚠️ **Preview display** — browser/service worker caches stale 404; hard reload (`Cmd+Shift+R`) clears it
- ⬜ **Commit all changes** — entrypoint.sh, Caddyfile, ConfigurationManager.php, index.php
- ⬜ **Upstream PR** to `nextcloud/all-in-one` for the Caddyfile `X-Forwarded-Prefix` fix
- ⬜ **Docker Desktop restart** — still needed for `daemon.json` DNS change; currently working around it with internal URLs

---

## Caveats & Local Environment Notes

### Docker DNS — containers resolving `nextcloud.test`

**Why internal URLs are needed:** `/etc/hosts` on the macOS host has `127.0.0.1 nextcloud.test`. Docker embeds these in container DNS, so containers resolve `nextcloud.test` → `127.0.0.1` (the container's own loopback) rather than the host Caddy. This breaks any container→host→container call.

**Workaround (in place):** Both `DocumentServerInternalUrl` and `StorageUrl` bypass `nextcloud.test` entirely by using Docker-internal container names.

**Permanent fix (not yet applied):** Restart Docker Desktop to load `daemon.json` (`"dns": ["192.168.97.1"]`). Then dnsmasq at `192.168.97.1` will resolve `.test` → `0.250.250.254` (macOS `host.docker.internal`) and `nextcloud.test` will resolve correctly from containers.

Setup:
- **dnsmasq** (Homebrew): `/opt/homebrew/etc/dnsmasq.conf` has:
  ```
  address=/.test/0.250.250.254
  listen-address=127.0.0.1,192.168.97.1
  ```
  (`192.168.97.1` is the Docker bridge gateway — containers use it as DNS)
- **`~/.docker/daemon.json`**: `"dns": ["192.168.97.1"]` — containers get dnsmasq as DNS resolver
- **`/etc/hosts`**: `127.0.0.1 nextcloud.test` — browser resolves directly (Caddy only listens on loopback, not the Docker bridge)
- Docker Desktop restart required for daemon.json to take effect — **still pending**

### Apache container — patching Caddyfile at runtime
- `/Caddyfile` is baked into the image; cannot be edited in a running container (image rootfs is read-only for that path)
- `/tmp/Caddyfile` is the runtime copy generated by `start.sh`; it is writable
- Caddy loads from `/tmp/Caddyfile` at startup (`caddy run --config /tmp/Caddyfile`)
- To apply a Caddyfile fix without a full container restart:
  ```bash
  # edit /tmp/Caddyfile, then:
  docker exec nextcloud-aio-apache caddy reload --config /tmp/Caddyfile
  ```

### EuroOffice NC connector app
- App ID: `eurooffice`, FQCN for preview provider: `OCA\Eurooffice\Preview`
- Available on the Nextcloud appstore: https://apps.nextcloud.com/apps/eurooffice (v11.0.0, NC 33/34)
- Added to `STARTUP_APPS` so it installs automatically on first Nextcloud start
- Sibling repo: `/Users/jamesmanuel/PhpstormProjects/euro-office-public/eurooffice-nextcloud`
- Uses OnlyOffice `/converter` API (not WOPI) for preview generation and document editing
- JWT: shared secret configured via `jwt_secret` in both `config:system:set eurooffice jwt_secret` and `config:app:set eurooffice jwt_secret`; header is `AuthorizationJwt`

### AIO UI & access
- Mastercontainer: `https://localhost:9090`
- Nextcloud: `https://nextcloud.test`
- Stop/start containers via the AIO UI — not `docker stop` directly
- Upstream beta tag being tested: `v13.2.0` (first version with EuroOffice support merged via PR #8052)

### Local images pulled
- `ghcr.io/euro-office/documentserver:v9.3.1-beta.1` — 7.14 GB
- `ghcr.io/nextcloud-releases/all-in-one:latest` — 386 MB
- `ghcr.io/nextcloud-releases/aio-apache:beta` — 289 MB (locally built)

### Building locally
```bash
# apache image (Caddyfile changes):
docker buildx build --file Containers/apache/Dockerfile --tag ghcr.io/nextcloud-releases/aio-apache:beta --load Containers/apache

# mastercontainer (PHP/config changes):
docker buildx build --file Containers/mastercontainer/Dockerfile --tag ghcr.io/nextcloud-releases/all-in-one:develop --load .
# then add ?bypass_mastercontainer_update to the URL to skip self-update prompt
```
