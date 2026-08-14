# Setup steps

This is Nextcloud AIO ("manual install" method) with all optional features enabled:
Talk, Talk Recording, Collabora, OnlyOffice, EuroOffice, ClamAV, Imaginary,
Fulltextsearch, Whiteboard. No external storage backend (S3/Ceph) is involved —
AIO's own containers handle everything. See the header comment in
`docker-compose.yml` for why (AIO's S3 support is its own bundled Minio container,
not a way to plug in an external S3 endpoint).

All 14 images are pinned to tag `20260805_083533`, which bundles Nextcloud 33.0.7
(per Anirban's request — confirmed by inspecting the image's baked-in
`NEXTCLOUD_VERSION` before pinning, since AIO's own tags don't carry Nextcloud
version numbers). AIO has never shipped a 34.x build; 33.0.7 is the newest version
it actually provides. `NEXTCLOUD_STARTUP_APPS` also includes `integration_google`
and `integration_onedrive` so both come pre-installed on a fresh install — each
still needs its own OAuth app (Google Cloud Console / Azure AD) registered and
configured in Nextcloud's admin settings before it actually connects to anything.

1. `cp .env.example .env` and fill in real values — all the `change-me` secrets need
   unique, good passwords (avoid `@` and `:` in them), and `NC_DOMAIN` needs to be
   the hostname you'll actually use.
   - **On macOS + Docker Desktop**, also change `NEXTCLOUD_MOUNT` and
     `NEXTCLOUD_TRUSTED_CACERTS_DIR` away from their upstream defaults (`/mnt/` and
     `/usr/local/share/ca-certificates/...`). Docker Desktop only bind-mounts paths
     under locations it shares (your home directory, `/Volumes`, `/private`,
     `/tmp`) — anything else fails on first `up` with `mounts denied`. Point both
     at absolute paths under this checkout instead, e.g.
     `/path/to/this/repo/host-mounts/mount`, and `mkdir -p` them first. Linux hosts
     don't have this restriction and can keep the upstream defaults.

2. Generate a self-signed TLS cert for that hostname (swap for a real cert outside
   local dev):
   ```sh
   sudo mkdir -p /etc/nginx/certs
   sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
     -keyout /etc/nginx/certs/nextcloud.local.key \
     -out /etc/nginx/certs/nextcloud.local.crt \
     -subj "/CN=nextcloud.local"
   ```
   Adjust the filenames/CN if you chose a different `NC_DOMAIN`.

3. Point the existing host nginx at it using `nginx-nextcloud.conf.sample` as a
   starting point (update `server_name` and the cert paths to match), reload nginx.
   Read the comment at the top of that file — HTTPS here isn't optional, AIO assumes
   it unconditionally.

4. Bring the stack up with every optional profile enabled:
   ```sh
   docker compose --profile collabora --profile talk --profile talk-recording \
     --profile clamav --profile onlyoffice --profile eurooffice --profile imaginary \
     --profile fulltextsearch --profile whiteboard up -d
   ```
   First boot pulls a lot of images (Collabora, Elasticsearch for Fulltextsearch,
   OnlyOffice, EuroOffice, ClamAV's virus DB, etc.) — expect this to take a while
   and use several GB of disk/RAM. Always pass the same `--profile` flags on
   subsequent `up`/`down` calls, or Compose will stop containers it thinks you no
   longer want.

5. `docker compose logs nextcloud-aio-nextcloud` — confirm it installed cleanly.
   `docker compose ps` — all containers should reach a healthy state eventually
   (Collabora and ClamAV take the longest to pass their healthcheck on first boot).

6. Add an entry for `NC_DOMAIN` to your machine's `/etc/hosts` pointing at
   `127.0.0.1` (it's not real DNS — nothing else will resolve it), then visit
   `https://<NC_DOMAIN>`, log in with `admin` / `NEXTCLOUD_PASSWORD` from `.env`.

## Verifying it actually works

```sh
# 1. Nextcloud is installed and responding (adjust hostname to your NC_DOMAIN)
curl -k https://nextcloud.local/status.php
# expect: "installed":true

# 2. Upload a file through the real API a user would hit
echo "test file" > /tmp/test.txt
curl -k -u admin:<NEXTCLOUD_PASSWORD> -T /tmp/test.txt \
  https://nextcloud.local/remote.php/dav/files/admin/test.txt
# expect: HTTP 201

# 3. Read it back
curl -k -u admin:<NEXTCLOUD_PASSWORD> \
  https://nextcloud.local/remote.php/dav/files/admin/test.txt
# expect: HTTP 200, body "test file"

# 4. Confirm the optional apps actually got enabled, not just the containers started
docker compose exec -u www-data nextcloud-aio-nextcloud php occ app:list --enabled | \
  grep -iE "talk|richdocuments|onlyoffice|files_fulltextsearch|files_antivirus"
```
(`-k` skips cert validation for the self-signed cert — drop it once you're on a
real one.)

If step 2 or 3 fails, check `docker compose logs nextcloud-aio-nextcloud` and
`docker compose logs nextcloud-aio-apache`. If step 4 shows an app missing despite
its container being healthy, check that its `*_ENABLED` var in `.env` is `"yes"`
(with quotes) and that you passed its `--profile` flag on `up`.

## Known limitations (accepted for this stage)

- No backup solution configured (AIO has an optional Backup container; not enabled
  here).
- Self-signed cert generated manually above — swap for a real one (e.g. Let's
  Encrypt) outside local dev.
- All state lives in named Docker volumes (`nextcloud_aio_*`) with no redundancy —
  losing them loses everything.
- `NC_DOMAIN` being a fake/local-only hostname (not real DNS) means every container
  that needs to resolve it has to be told about it explicitly. The `talk` service
  already has an `extra_hosts` entry for this (its WebRTC/TURN server otherwise
  crash-loops with "Invalid TURN address") — if a real domain with real DNS
  replaces `NC_DOMAIN` later, that `extra_hosts` line becomes unnecessary but
  harmless.
