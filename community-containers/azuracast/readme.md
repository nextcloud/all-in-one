## AzuraCast

This container bundles [AzuraCast](https://www.azuracast.com/), an open-source web radio management suite, and integrates it with Nextcloud AIO.

### Notes

- The AzuraCast web interface is available at `https://radio.your-nc-domain.com` when used with the Caddy community container (see below). On first start, you will be directed to `/setup/register` to create the admin account.
- To access AzuraCast from outside your local network, use the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), which will automatically configure `radio.$NC_DOMAIN` to reverse-proxy the AzuraCast interface. Make sure to point `radio.your-nc-domain.com` to your server using an A or CNAME record before starting.
- **Radio streaming ports** (8000–8046, covering up to 5 stations) are exposed directly on the host and cannot be reverse-proxied. Listeners connect to these ports directly. Configure your AzuraCast stations to use ports within this range.
- **SFTP uploads** are available on port 2022.
- AzuraCast's data (stations, database, backups) is automatically included in AIO's BorgBackup solution.
- **Updater:** The standard AzuraCast `updater` container is omitted — updates are managed through AIO's standard container update mechanism (pulling the new `ghcr.io/azuracast/azuracast:stable` image).
- AzuraCast uses port **10080** (HTTP) and **10443** (HTTPS), following the [ports recommended by AzuraCast](https://www.azuracast.com/docs/administration/multi-site-installation/#edit-azuracast-web-serving-ports) for reverse proxy deployments. Both ports are exposed directly on the host. The recommended way to access the web interface is via `https://radio.your-nc-domain.com` through the Caddy community container. Direct HTTPS access is also available at `https://yourserver:10443` (self-signed certificate — browser warning expected).

### Recommended settings after first login

On first access, AzuraCast will prompt you to create an admin account, then redirect to **System Settings**. The following settings are recommended when running behind the Caddy community container:

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

The following ports are exposed for up to 5 radio stations (3 ports per station: stream, admin, legacy SHOUTcast):

| Station | Stream | Admin | SHOUTcast |
|---------|--------|-------|-----------|
| 1 | 8000 | 8005 | 8006 |
| 2 | 8010 | 8015 | 8016 |
| 3 | 8020 | 8025 | 8026 |
| 4 | 8030 | 8035 | 8036 |
| 5 | 8040 | 8045 | 8046 |

If you need more than 5 stations, additional ports can be exposed by forking this community container and extending the port list in `azuracast.json`.

### Known limitations

- The `updater` container from the standard AzuraCast docker-compose is not included (requires Docker socket access, incompatible with AIO's security model).
- The `shoutcast2`, `stereo_tool`, `rsas`, and `geolite` optional install directories are part of the unified `storage` volume. The corresponding features (SHOUTcast 2, Stereo Tool, RSAS, GeoLite2 geolocation) must be installed and activated manually from within AzuraCast after the container is running.

#### Nextcloud file integration

By default, AzuraCast runs in an isolated container: Nextcloud files are not accessible from within AzuraCast, and AzuraCast media files are not browsable in Nextcloud.

**Recommended: Nextcloud External Storage via SFTP**

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

**Advanced: shared bind-mount volume**

For use cases where you want a shared filesystem between AzuraCast and other containers (e.g., Jellyfin), or where you want to manage media outside of AIO backups, this container includes a dedicated `/var/azuracast/shared` volume (`nextcloud_aio_azuracast_shared`). By default it is an empty named volume, excluded from AIO backups.

To replace it with a bind-mount to a host directory:

```bash
# Stop the AzuraCast container first, then:
sudo docker volume rm nextcloud_aio_azuracast_shared
sudo docker volume create \
  --driver local \
  --opt type=none \
  --opt device=/mnt/your-shared-path \
  --opt o=bind \
  nextcloud_aio_azuracast_shared
```

Then configure a storage location in AzuraCast pointing to `/var/azuracast/shared`.

### Repository

https://github.com/AzuraCast/AzuraCast

### Maintainer

https://github.com/biguenique
