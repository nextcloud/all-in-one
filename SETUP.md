# Setup steps

1. `cp .env.example .env` and fill in real values (passwords, hostname, S3 keys).
2. Pin the ceph-demo image tag in `docker-compose.yml` — check
   https://quay.io/repository/ceph/demo for available tags first; don't run `:latest`.
3. `docker compose up -d`
4. `docker compose logs ceph-demo` — confirm the RGW (S3) port it actually bound to.
   If it isn't 8080, update both the `ceph-demo` ports mapping and the
   `OBJECTSTORE_S3_PORT` value on the `nextcloud` service to match, then
   `docker compose up -d` again before Nextcloud's first run (this must be
   right before the first request hits Nextcloud, since primary storage is
   set once on install).
5. `docker compose logs nextcloud` — confirm it installed cleanly and picked up
   the S3 object store (look for object store setup lines, no S3 connection errors).
6. Point the existing host nginx at it using `nginx-nextcloud.conf.sample` as a
   starting point, reload nginx.
7. Visit the configured hostname, log in with NEXTCLOUD_ADMIN_USER / PASSWORD.

## Known limitations (accepted for this stage)

- Single-node Ceph demo container: no redundancy. Losing this container's
  volume loses all files.
- No backup solution configured.
- Self-signed/no TLS handled here — assumes nginx (or you) handles that
  separately if needed for the chosen local hostname.
