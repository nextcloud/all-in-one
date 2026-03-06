## Vaultwarden
This container bundles vaultwarden and auto-configures it for you.

### Notes
- You need to configure a reverse proxy in order to run this container since vaultwarden needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy or follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md and https://github.com/dani-garcia/vaultwarden/wiki/Proxy-examples. You need to point the reverse proxy at port 8812 of this server.
- Currently, only `bw.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, vaultwarden will use `bw.your-domain.com`. The reverse proxy and domain must be configured accordingly!
- If you want to secure the installation with fail2ban, you might want to check out https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban
- The data of Vaultwarden will be automatically included in AIOs backup solution!
- After adding and starting the container, you need to visit `https://bw.your-domain.com/admin` in order to log in with the admin key that you can see next to the container in the AIO interface. There you can configure smtp first and then invite users via mail. After this is done, you might disable the admin panel via the reverse proxy by blocking connections to the subdirectory.
- If using the caddy community container, the vaultwarden admin interface can be disabled by creating a `block-vaultwarden-admin` file in the `nextcloud-aio-caddy` folder when you open the Nextcloud files app with the default `admin` user. Afterwards restart all containers from the AIO interface and the admin interface should be disabled! You can unlock the admin interface by removing the file again and afterwards restarting the containers via the AIO interface.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/dani-garcia/vaultwarden

### Maintainer
https://github.com/szaimen
