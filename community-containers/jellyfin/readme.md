## Jellyfin
This container bundles Jellyfin and auto-configures it for you.

### Notes
- This container is incompatible with the [Plex](https://github.com/nextcloud/all-in-one/tree/main/community-containers/plex) community container. So make sure that you do not enable both at the same time!
- After adding and starting the container, you can directly visit http://ip.address.of.server:8096/ and access your new Jellyfin instance!
- This container should usually only be run in home networks as it exposes unencrypted services like DLNA by default which can be disabld via the web interface though.
- In order to access your Jellyfin outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) and [Jellyfin's networking documentation](https://jellyfin.org/docs/general/networking/#running-jellyfin-behind-a-reverse-proxy), OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `media.$NC_DOMAIN` to redirect to your Jellyfin.
- ⚠️ After the initial start, Jellyfin shows a configuration page to set up the root password, etc. **Be careful to initialize your Jellyfin before adding the DNS record.**
- If you have a firewall like ufw configured, you might need to open all Jellyfin ports in there first in order to make it work. Especially port 8096 is important!
- The data of Jellyfin will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.


### Repository
https://github.com/jellyfin/jellyfin

### Maintainer
https://github.com/airopi
