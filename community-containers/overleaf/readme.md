## Overleaf (Community Edition)
This community container deploys Overleaf CE with its required MongoDB and Redis services.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:8050/ to access your new Overleaf instance.
- To access Overleaf outside your local network with HTTPS, set up a reverse proxy in front of AIO. You can either follow the generic reverse proxy guide: https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md or use the community Caddy container which can be adjusted to forward a subdomain like `overleaf.$NC_DOMAIN` to port 8050 on this server.
- Initial user/admin creation is handled inside Overleaf. This container does not integrate Overleaf authentication with Nextcloud users.
- If you run a firewall (e.g., ufw), ensure port 8050 is allowed or that your reverse proxy can reach it locally.
- The data directories for Overleaf and MongoDB are persisted and included in AIO backups automatically.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers for how to add it to the AIO stack.

### Nextcloud integration (auto-config)
When this container is added, AIO automatically:
- Installs and enables the Nextcloud app `integration_overleaf`.
- Sets its `base_url` to `http://%NC_DOMAIN%:8050` by default (reachable when accessing the server directly).

If you publish Overleaf behind a reverse proxy on a subdomain (recommended), change the URL in Nextcloud accordingly, for example:
```bash
sudo docker exec --user www-data -it nextcloud-aio-nextcloud php /var/www/html/occ config:app:set integration_overleaf base_url --value "https://overleaf.%NC_DOMAIN%"
```

### Data and backups
- Overleaf data: stored in the volume `nextcloud_aio_overleaf` (mounted at `/var/lib/sharelatex`).
- MongoDB data: stored in the volume `nextcloud_aio_overleaf_mongo` (mounted at `/data/db`).
- Both are included in AIO backup/restore. Redis data is ephemeral by design and not included.

### Advanced configuration
- The container uses the official `sharelatex/sharelatex` image and configures minimal required environment variables (MongoDB/Redis/Time zone). You can adjust Overleaf settings from the web UI afterwards.
- If you plan to expose Overleaf publicly, consider setting up rate-limiting and fail2ban in front of it. See the community Fail2ban container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban

### Repository
https://github.com/overleaf/overleaf

### Maintainer
https://github.com/docjyj
