## Plex
This container bundles Plex and auto-configures it for you.

### Notes
- This container is incompatible with the [Jellyfin](https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyfin) community container. So make sure that you do not enable both at the same time!
- This is not working on arm64 since Plex does only provide x64 docker images.
- This container should usually only be run in home networks as it exposes unencrypted services like DLNA by default which can be disabld via the web interface though.
- If you have a firewall like ufw configured, you might need to open all Plex ports in there first in order to make it work. Especially port 32400 is important!
- After adding and starting the container, you need to visit http://ip.address.of.server:32400/manage in order to claim your server with a plex account
- The data of Plex will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/plexinc/pms-docker

### Maintainer
https://github.com/szaimen
