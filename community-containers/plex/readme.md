## Plex
This container bundles Plex and auto-configures it for you.

### Notes
- This container is incompatible with the [Jellyfin](https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyfin) community container. So make sure that you do not enable both at the same time!
- This is not working on arm64 since Plex does only provide x64 docker images.
- This is not working on Docker Desktop since it needs `network_mode: host` in order to work correctly.
- If you have a firewall like ufw configured, you might need to open all Plex ports in there first in order to make it work. Especially port 32400 is important!
- After adding and starting the container, you need to visit http://ip.address.of.server:32400/manage in order to claim your server with a plex account
- The data of Plex will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/plexinc/pms-docker

### Maintainer
https://github.com/szaimen
