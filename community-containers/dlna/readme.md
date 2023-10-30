## DLNA server
This container bundles DLNA server for your Nextcloud files to be accessible by the clients in your local network. Simply run the container and look for a new media server `nextcloud-aio` in your local network.

### Notes
- This is not working on Mac OS since it requires the `host` networking mode in docker, and it doesn't really share the host's network interfaces in this system
- If you have a firewall like ufw configured, you might need to open at least port 9999 TCP and 1900 UDP first in order to make it work.

### Repository
https://github.com/thanek/nextcloud-dlna

### Maintainer
https://github.com/thanek

