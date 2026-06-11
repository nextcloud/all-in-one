## AzuraCast

This container bundles [AzuraCast](https://www.azuracast.com/), an open-source web radio management suite, and integrates it with Nextcloud AIO.

### Notes

- To access AzuraCast from outside your local network, use the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), which will automatically configure `radio.$NC_DOMAIN` to reverse-proxy the AzuraCast interface. Make sure to point `radio.your-nc-domain.com` to your server using an A or CNAME record before starting.
- The AzuraCast web interface is then available at `https://radio.your-nc-domain.com`. On first start, you will be directed to `/setup/register` to create the admin account.
- **Radio streaming ports** (8000–8046) cover up to 5 stations that are exposed directly on the host without reverse-proxy.
- **SFTP access** is available on port 2022.
- AzuraCast's data (stations, database, backups) is automatically included in AIO's BorgBackup solution.
- **Updater:** The standard AzuraCast `updater` container is omitted — updates are managed through AIO's standard container update mechanism (pulling the new `ghcr.io/azuracast/azuracast:stable` image).
- AzuraCast uses port **10080** (HTTP) and **10443** (HTTPS), following the [ports recommended by AzuraCast](https://www.azuracast.com/docs/administration/multi-site-installation/#edit-azuracast-web-serving-ports) for reverse proxy deployments. Both ports are exposed directly on the host. The recommended way to access the web interface is via `https://radio.your-nc-domain.com` through the Caddy community container. Direct HTTPS access is also available at `https://yourserver:10443` (self-signed certificate — browser warning expected).
- Custom domains can be configured using [caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy)

### Recommended settings after first login

On first access, AzuraCast will prompt you to create an admin account, then redirect to a **System Settings** page. The following settings are recommended when running behind the Caddy community container:

**Settings tab:**
- **Site Base URL**: Set to `https://radio.your-nc-domain.com`
- **Use Web Proxy for Radio**: Enable — all radio traffic is routed securely through Caddy
- **Use High-Performance Now Playing Updates**: Enable — activates WebSocket, SSE, and static JSON updates for metadata (fully supported by Caddy)

**Security & Privacy tab:**
- **Always Use HTTPS**: Enable
- **IP Address Source**: Select `Reverse Proxy (X-Forwarded-For)`

**Services tab:**
- **LetsEncrypt**: Leave empty — TLS certificates are handled by Caddy

### Streaming ports

The following ports are exposed for up to 5 radio stations (3 ports per station: stream, remote DJ, legacy SHOUTcast):

| Station | Stream | Remote DJ | SHOUTcast |
|---------|--------|-------|-----------|
| 1 | 8000 | 8005 | 8006 |
| 2 | 8010 | 8015 | 8016 |
| 3 | 8020 | 8025 | 8026 |
| 4 | 8030 | 8035 | 8036 |
| 5 | 8040 | 8045 | 8046 |

Listeners and live source (remote DJs) can connect to these ports directly. Ports are assigned sequentially to new stations, but can be configured manually in `/admin/stations`.

By enabling **Web Proxy for Radio** (recommended settings), you can create an unlimited number of stations without direct port access. Assign the available ports to stations that actually need them.

### Known limitations

- The `updater` container from the standard AzuraCast docker-compose is not included (requires Docker socket access, incompatible with AIO's security model).
- The `shoutcast2`, `stereo_tool`, `rsas`, and `geolite` optional install directories are part of the unified `storage` volume. The corresponding features (SHOUTcast 2, Stereo Tool, RSAS, GeoLite2 geolocation) must be installed and activated manually from within AzuraCast after the container is running.

#### Nextcloud file integration

By default, AzuraCast runs in an isolated container: Nextcloud files are not accessible from within AzuraCast, and AzuraCast media files are not browsable in Nextcloud.

**Nextcloud External Storage via SFTP**

AzuraCast includes a built-in SFTP server (SFTPGo) on port 2022. SFTP users are managed per-station in AzuraCast under **Media → SFTP Users**. This lets each station operator define their own Nextcloud External Storage mount with their own credentials, without any host-level configuration.

In Nextcloud, add an External Storage mount of type **SFTP** with the following parameters:

| Field | Value |
|---|---|
| Host | `nextcloud-aio-azuracast` (within the Docker network) or your external domain/IP |
| Port | `2022` |
| Username / Password | As configured in AzuraCast's SFTP user settings |
| Root | Leave empty or set to the station's media subfolder |

> **Note:** Do not use `localhost` as the host — within the Nextcloud container, `localhost` refers to Nextcloud itself, not AzuraCast. Use the container name `nextcloud-aio-azuracast` or your server's domain name instead.

This approach supports per-station access control, works with both local and remote AzuraCast instances, and media files remain covered by AIO's BorgBackup solution.

**Advanced: shared filesystem via `NEXTCLOUD_MOUNT`**

`NEXTCLOUD_MOUNT` is an [AIO setting](https://github.com/nextcloud/all-in-one#how-to-allow-the-nextcloud-container-to-access-directories-on-the-host) that exposes an arbitrary host directory to the Nextcloud container, enabling **Nextcloud External Storage** mounts on the host filesystem. When set, the same path is automatically mounted in AzuraCast at the same location, giving both processes direct access to the same files — with significantly better performance than SFTP when managing large media libraries.

To set this up:
1. Set `NEXTCLOUD_MOUNT` to a host directory path in AIO settings (e.g. `/data/media`).
2. In AzuraCast, configure a station to use a subdirectory of that path as its media storage (e.g. `/data/media/stations/mystation/media`).
3. In Nextcloud, optionally add a **Local** External Storage mount pointing to the same path to browse and manage the files from Nextcloud.

If `NEXTCLOUD_MOUNT` is not configured in AIO, this volume is silently skipped and AzuraCast starts normally.

> **Note:** Files in the shared directory are not included in AIO's BorgBackup. Back up this directory separately if needed.

**Default access**

Both processes run as different users — Nextcloud as `www-data` (UID 33) and AzuraCast as `azuracast` (UID 1000) — with no group membership in common.

- Files created by either process (mode `644`) are readable by the other as world-readable, but cannot be modified.
- Directories created by Nextcloud (mode `755`) are browsable by AzuraCast.
- **Some AzuraCast directories are created with mode `700`** (user-created subdirectories). These cannot be listed or entered by Nextcloud without intervention.

**Unlocking access**

The following commands are run **on the host**. To grant access to a specific path — for example, after creating a new station — run:

```bash
# Install ACL tools if not already present (Debian/Ubuntu)
sudo apt install acl

# Read-only access for Nextcloud to an AzuraCast directory
sudo setfacl -R -m u:33:rX /data/media/stations/mystation/

# Bidirectional read/write access
sudo setfacl -R -m u:33:rwX,u:1000:rwX /data/media/stations/mystation/
sudo setfacl -R -d -m u:33:rwX,u:1000:rwX /data/media/stations/mystation/
```

> **Note:** These commands apply to the current directory tree. They need to be re-run when AzuraCast creates new subdirectories within the affected path.

### Repository

https://github.com/AzuraCast/AzuraCast

### Maintainer

https://github.com/biguenique
