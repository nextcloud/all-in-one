## Jellyseerr
This container bundles Jellyseerr and auto-configures it for you.

### Notes
- This container is only intended to be used inside home networks as it uses http for its management page by default.
- After adding and starting the container, you can directly visit `http://ip.address.of.server:5055` and access your new Jellyseerr instance, which can be used to manage Plex, Jellyfin, and Emby.
- In order to access your Jellyseerr outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) and [Jellyseerr's reverse proxy documentation.](https://docs.jellyseerr.dev/extending-jellyseerr/reverse-proxy), OR use the Caddy community container that will automatically configure requests.$NC_DOMAIN to redirect to your Jellyseerr. Note that it is recommended to [enable CSRF protection in Jellyseerr](https://docs.jellyseerr.dev/using-jellyseerr/settings/general#enable-csrf-protection) for added security if you plan to use Jellyseerr outside the local network, but make sure to read up on it and understand the caveats first.
- If you want to secure the installation with fail2ban, you might want to check out https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban. Note that [enabling the proxy support option in Jellyseerr](https://docs.jellyseerr.dev/using-jellyseerr/settings/general#enable-proxy-support) is required for this to work properly.
- The config of Jellyseerr will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/Fallenbagel/jellyseerr

### Maintainer
https://github.com/Anvil5465
