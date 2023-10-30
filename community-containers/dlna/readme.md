## DLNA server
This container bundles DLNA server for your Nextcloud files to be accessible by the clients in your local network. Simply run the container and look for a new media server `nextcloud-aio` in your local network.

### Notes
- This container needs the `nextcloud-aio-nextcloud` container to be up and running to work
- This is not working on Docker Desktop since it needs `network_mode: host` in order to work correctly
- This is not working on Mac OS since the `host` networking mode doesn't really share the host's network interfaces in this system
- It is needed to allow TCP network traffic on port 9999 to the AIO instance and the UDP (port 1900) from it to the local network

### Repository
https://github.com/thanek/nextcloud-dlna

### Maintainer
https://github.com/thanek

