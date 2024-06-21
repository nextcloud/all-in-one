## DLNA server
This container bundles DLNA server for your Nextcloud files to be accessible by the clients in your local network. Simply run the container and look for a new media server `nextcloud-aio` in your local network.

### Notes
- This container will work only if the Nextcloud installation is in your home network, it is not suitable for installations on remote servers.
- This is not working with Docker Desktop since it requires the `host` networking mode in docker, and it doesn't really share the host's network interfaces in this system
- If you have a firewall like ufw configured, you might need to open at least port 9999 TCP and 1900 UDP first in order to make it work.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/thanek/nextcloud-dlna

### Maintainer
https://github.com/thanek

