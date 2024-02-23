## Jellyfin
This container bundles Jellyfin and auto-configures it for you.

### Notes
- This is not working on Docker Desktop since it needs `network_mode: host` in order to work correctly.
- After adding and starting the container, you can directly visit http://ip.address.of.server:8096/ and access your new Jellyfin instance!
- The data of Jellyfin will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/jellyfin/jellyfin

### Maintainer
https://github.com/airopi
