## Jellyfin
This container bundles Jellyfin and auto-configures it for you.

### Notes
- This container is incompatible with the [Plex](https://github.com/nextcloud/all-in-one/tree/main/community-containers/plex) community container. So make sure that you do not enable both at the same time!
- This container does not work on Docker Desktop since it needs `network_mode: host` in order to work correctly.
- After adding and starting the container, you can directly visit http://ip.address.of.server:8096/ and access your new Jellyfin instance!
- In order to access your Jellyfin outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) and [Jellyfin's networking documentation](https://jellyfin.org/docs/general/networking/#running-jellyfin-behind-a-reverse-proxy), OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `media.$NC_DOMAIN` to redirect to your Jellyfin.
- ⚠️ After the initial start, Jellyfin shows a configuration page to set up the root password, etc. **Be careful to initialize your Jellyfin before adding the DNS record.**
- The data of Jellyfin will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.


### Repository
https://github.com/jellyfin/jellyfin

### Maintainer
https://github.com/airopi
