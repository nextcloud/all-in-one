## Plex
This container bundles Plex and auto-configures it for you.

### Notes
- This is not working on Docker Desktop since it needs `network_mode: host` in order to work correctly.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack
- After adding the container, you need to visit http://ip.address.of.server:32400 in order to claim your server with a plex account

### Repository
https://github.com/plexinc/pms-docker

### Maintainer
https://github.com/szaimen
