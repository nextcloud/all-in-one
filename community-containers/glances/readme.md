## Glances
This container starts Glances, a web-based info-board, and auto-configures it for you.
SECURITY WARNING! This container mounts the docker-socket from the host-system.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:61208/ and access your new Glances instance!
- It is recommended to start this container only in home networks, because there is no build-in authentication. But you can do a http-auth with your proxy.
- In order to access your Glances outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md).
- The data of Glances will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/nicolargo/glances

### Maintainer
https://github.com/pi-farm
