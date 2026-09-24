## Overleaf (Community Edition)
This community container deploys Overleaf CE with its required MongoDB and Redis services.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:8050/ to access your new Overleaf instance.
- To access Overleaf outside your local network with HTTPS, set up a reverse proxy in front of AIO. You can either follow the generic reverse proxy guide: https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md or use the community Caddy container which can be adjusted to forward a subdomain like `overleaf.$NC_DOMAIN` to port 8050 on this server.
- Initial user/admin creation is handled inside Overleaf. This container does not integrate Overleaf authentication with Nextcloud users.
- If you run a firewall (e.g., ufw), ensure port 8050 is allowed or that your reverse proxy can reach it locally.
- The data directories for Overleaf and MongoDB are persisted and included in AIO backups automatically.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers for how to add it to the AIO stack.

### Repository
https://github.com/overleaf/overleaf

### Maintainer
https://github.com/docjyj
