# Setup steps

1. `cp .env.example .env` and fill in real values (passwords, hostname, S3 keys).
2. `docker compose up -d`
   - The `ceph-demo` image is already pinned by digest in `docker-compose.yml`
     (not `:latest`) — see the comment above that line for why, and re-check
     https://quay.io/repository/ceph/demo?tab=tags before bumping it.
   - Host port `18080` is used for `ceph-demo`'s RGW instead of the default `8080`,
     because something else commonly holds `8080` on a dev machine. If `18080` is
     also taken on yours, change the left side of that port mapping only — internal
     container-to-container traffic still uses `8080` regardless.
3. `docker compose logs nextcloud` — confirm it installed cleanly and picked up
   the S3 object store (look for object store setup lines, no S3 connection errors).
4. Point the existing host nginx at it using `nginx-nextcloud.conf.sample` as a
   starting point, reload nginx.
5. Visit the configured hostname, log in with NEXTCLOUD_ADMIN_USER / PASSWORD.

## Verifying it actually works

Checking that Nextcloud installed isn't enough on its own — the S3 storage backend
can be silently misconfigured (wrong host, unreachable bucket) while Nextcloud still
reports healthy. Confirm with a real file round-trip:

```sh
# 1. Nextcloud is installed and responding
curl http://127.0.0.1:11000/status.php
# expect: "installed":true

# 2. Upload a file through the real API a user would hit
echo "test file" > /tmp/test.txt
curl -u admin:<NEXTCLOUD_ADMIN_PASSWORD> -T /tmp/test.txt \
  http://127.0.0.1:11000/remote.php/dav/files/admin/test.txt
# expect: HTTP 201

# 3. Read it back
curl -u admin:<NEXTCLOUD_ADMIN_PASSWORD> \
  http://127.0.0.1:11000/remote.php/dav/files/admin/test.txt
# expect: HTTP 200, body "test file"
```

If step 2 or 3 fails, check `docker compose logs nextcloud` for
`StorageNotAvailableException` — that's the object-store connection failing, not a
Nextcloud problem. The comments in `docker-compose.yml` document the specific
gotchas already worked around here (RGW hostname parsing, image pinning, port
conflicts) — worth checking if a symptom looks familiar before re-diagnosing it.

## Known limitations (accepted for this stage)

- Single-node Ceph demo container: no redundancy. Losing this container's
  volume loses all files.
- No backup solution configured.
- Self-signed/no TLS handled here — assumes nginx (or you) handles that
  separately if needed for the chosen local hostname.
