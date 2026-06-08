# EuroOffice Default Editor — Implementation Plan

**Status:** Changes implemented, tested locally. Ready to commit.
**Phase:** Testing → Commit → Build → (upstream Caddyfile PR optional)

---

## Security Review (Completed)

**Verdict: no regressions.** The four changed files introduce no new attack surface.

- **Caddyfile `X-Forwarded-Prefix /eurooffice`** — literal string set by Caddy `header_up`, not
  user-controlled. Overwrites any client-supplied value before it reaches EuroOffice nginx.
- **`X-Forwarded-Host {http.request.hostport}`** — appears in the EuroOffice route and was already
  present in existing OnlyOffice and Nextcloud server blocks. Caddy only routes requests matching its
  server-block bindings, and Nextcloud enforces `trusted_domains` downstream. Considered and
  dismissed; not a reflective-Host injection issue in this deployment model.
- **Internal HTTP (DocumentServerInternalUrl / StorageUrl)** — identical threat model to the existing
  Collabora WOPI callback path. `http://nextcloud-aio-apache.nextcloud-aio:23973` is the documented
  internal callback address (used by Collabora in containers.json lines 394/405). Same Docker
  network isolation, same JWT signing (`AuthorizationJwt`). No new port opened.
- **`performMigrations()` in index.php** — runs an idempotent config read on every HTTP request until
  the migration flag is set for the first time. Not a security concern; minor performance overhead
  on first-boot only.

---

## Wider Implications

### 1. Migration blast-radius (decision required)

`performMigrations()` runs on every mastercontainer boot. When `eurooffice_default_migration_v1` is
unset it force-sets:
- `isCollaboraEnabled = false`
- `isOnlyofficeEnabled = false`
- `isEuroofficeEnabled = true`

**This overrides an admin's explicit prior choice**, not just an unset default. An existing install
that deliberately runs Collabora will have its editor swapped silently on the next image pull.

The flag (`eurooffice_default_migration_v1`) prevents re-fighting an admin who switches back after
migration. But there is no rollback: once migrated, Collabora/OnlyOffice must be re-enabled manually.

**Open question for James:** Is the silent force-switch the intended behaviour for all existing forks
of this repo, or should it only apply when no editor has been explicitly set?

If the answer is "force-switch is correct" → current code is right.
If "preserve explicit Collabora installs" → change the migration to check whether an explicit choice
was already made (e.g., only migrate if `isCollaboraEnabled` is still at its factory default).

### 2. Preview provider index fix (bug fixed)

`occ config:system:set enabledPreviewProviders` in AIO seeds indices 1–7 on fresh install, and also
uses index 23 for Imaginary. The previous approach of computing the next index via `wc -l` would
produce 7 on a standard install, which collides with the Krita entry at index 7 — silently removing
Krita previews.

**Fixed:** replaced dynamic `wc -l` with fixed index 50. AIO never exceeds index 23 in its own
seeding; 50 is safe for the foreseeable set of providers.

### 3. Local-only validation gap

All testing was done in a local environment where `nextcloud.test` resolves to `127.0.0.1` inside
containers (host `/etc/hosts` is embedded into Docker DNS). The internal URLs work around this. In a
production install where DNS resolves correctly, the public-domain code path would be used instead
of the internal Docker URLs — but the code we added runs only when `$EUROOFFICE_HOST` matches the
`nextcloud-.*-eurooffice` pattern, which is the AIO default naming. That pattern is stable.

**What is reasoned but not yet tested on a clean prod-like install:** that port 23973 routes
EuroOffice→NC callbacks correctly end-to-end without the local DNS workaround in play. The port is
the documented Collabora WOPI ingress (confirmed in containers.json:394) so the reasoning is sound.

---

## Commits

Three atomic commits in logical order:

### Commit 1 — Caddyfile X-Forwarded-Prefix fix

```
fix(apache): send X-Forwarded-Prefix for EuroOffice SDK assets

EuroOffice nginx uses $http_x_forwarded_prefix to construct SDK
asset URLs (e.g. /eurooffice/sdkjs/...). Without this header the
prefix is empty and the browser requests /sdkjs/... which Caddy
routes to Nextcloud → 404.

Send X-Forwarded-Prefix as a separate header (not appended to
X-Forwarded-Host as OnlyOffice does) to match EuroOffice nginx
expectations. X-Forwarded-Host continues to carry host only.
```

**Files:** `Containers/apache/Caddyfile`

Note: this fix is also applicable upstream to `nextcloud/all-in-one` as a standalone PR (no other
changes needed).

### Commit 2 — Make EuroOffice the default editor

```
feat: make EuroOffice the default editor for new and existing installs

- isEuroofficeEnabled default: false → true
- isCollaboraEnabled default: true → false
- Add eurooffice to STARTUP_APPS
- performMigrations(): one-time force-switch for existing installs
  (guarded by eurooffice_default_migration_v1 flag to prevent
  re-fighting an admin who switches back)
- Call performMigrations() at index.php bootstrap
```

**Files:** `php/src/Data/ConfigurationManager.php`, `php/public/index.php`

### Commit 3 — Configure EuroOffice internal URLs and preview provider

```
fix(nextcloud): configure EuroOffice internal URLs and preview provider

Three fixes applied in entrypoint.sh when $EUROOFFICE_HOST matches
the default AIO container naming pattern:

1. DocumentServerInternalUrl → http://$EUROOFFICE_HOST:80/
   Bypasses the public domain (which containers cannot resolve in
   many setups) for NC→EuroOffice converter calls. Trailing slash
   required; DocumentService.php concatenates the raw value with
   "converter" (no separator).

2. StorageUrl → http://$APACHE_CONTAINER_HOST.nextcloud-aio:23973/
   Bypasses the public domain for EuroOffice→NC file fetch calls.
   Port 23973 is the Collabora WOPI ingress (server block matches
   the container FQDN; port 11000 rejects the wrong Host header).
   Trailing slash required; str_replace strips the / from the origin.

3. enabledPreviewProviders index 50 → OCA\Eurooffice\Preview
   NC's allowlist is explicit; registerPreviewProvider() alone is
   insufficient. Index 50 avoids collision with AIO's seeded range
   (1-7, 23).
```

**Files:** `Containers/nextcloud/entrypoint.sh`

---

## Build and Deploy

After committing, the apache image must be rebuilt for the Caddyfile change to take effect:

```bash
# Rebuild apache image
docker buildx build \
  --file Containers/apache/Dockerfile \
  --tag ghcr.io/nextcloud-releases/aio-apache:beta \
  --load \
  Containers/apache

# Rebuild mastercontainer (for ConfigurationManager + index.php changes)
docker buildx build \
  --file Containers/mastercontainer/Dockerfile \
  --tag ghcr.io/nextcloud-releases/all-in-one:develop \
  --load .
```

Then restart the AIO stack via the AIO UI at `https://localhost:9090`.
Use `?bypass_mastercontainer_update` in the URL to skip self-update prompt.

---

## Upstream PR (Optional)

The Caddyfile fix (commit 1) is a clean, standalone bugfix that applies to
`nextcloud/all-in-one` regardless of EuroOffice. The other two commits are
fork-specific and should not go upstream.

Before opening the upstream PR, confirm that OnlyOffice's `X-Forwarded-Host
host/onlyoffice` pattern is intentional for OO (it works differently) so the
PR can be scoped to EuroOffice only.

---

## Remaining Items

- [ ] Docker Desktop restart — apply `daemon.json` `"dns": ["192.168.97.1"]` for
  permanent DNS fix. Non-urgent: internal URL workaround is in place.
- [ ] Decision on migration behaviour (see §1 above) — confirm or adjust
  `performMigrations()` before tagging a release.
- [ ] Validate on a clean install without the local DNS workaround.
