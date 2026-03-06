## Glances
This container starts Glances, a web-based info-board, and auto-configures it for you.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:61208/ and access your new Glances instance!
- In order to access your Glances outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container.
- If you have a firewall like ufw configured, you might need to open all Glances ports in there first in order to make it work. Especially port 61208 is important!
- If you want to secure the installation with fail2ban, you might want to check out https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban
- The data of Glances will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/nicolargo/glances

### Maintainer
https://github.com/pi-farm
