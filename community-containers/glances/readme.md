## Glances
This container starts [Glances](https://nicolargo.github.io/glances/), a web-based system monitoring dashboard, and auto-configures it for you.

> [!CAUTION]
> This container mounts the docker-socket from the host-system and thus has unlimited root access to it!

> [!WARNING]
> Port 61208 is published on all network interfaces of the host and has no authentication. Anyone who can reach this port (e.g. the whole LAN, or the whole internet on a server without a firewall) can see all monitoring data of your host and its containers. Make sure to block this port in your firewall if the server is reachable from untrusted networks.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:61208/ and access your new Glances instance!
- if you are using caddy community container, this will be available at `https://glances.your-server.com`, For security reasons, a password will be generated and shown in the AIO interface (if you click on showing the secret for glances). Use `glances` as user name. 
- It is recommended to start this container only in home networks, because there is no built-in authentication. But you can do a http-auth with your proxy.
- In order to access your Glances outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md).
- The data of Glances will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/nicolargo/glances

### Maintainer
https://github.com/pi-farm
