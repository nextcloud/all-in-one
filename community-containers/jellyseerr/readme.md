## Jellyseerr
This container bundles Jellyseerr and auto-configures it for you.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:5055 / and access your new Jellyseerr instance, which can be used to manage Plex, Jellyfin, and Emby.
- In order to access your Jellyfin outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) and [Jellyfin's networking documentation](https://jellyfin.org/docs/general/networking/#running-jellyfin-behind-a-reverse-proxy)
- If you have a firewall like ufw configured, you might need to open port 5055 so that you can access the webui from you local network.
- The config of Jellyseerr will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.


### Repository
https://github.com/Fallenbagel/jellyseerr

### Maintainer
https://github.com/Anvil5465
