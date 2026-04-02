## Bitwarden Lite
This container bundles Bitwarden Lite (the official Bitwarden unified self-hosted container) and auto-configures it for you.

### Notes
- You need to configure a reverse proxy in order to run this container since Bitwarden Lite needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy or follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md. You need to point the reverse proxy at port 8813 of this server.
- Currently, only `bw.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, Bitwarden Lite will use `bw.your-domain.com`. The reverse proxy and domain must be configured accordingly!
- **This container is incompatible with the [vaultwarden](https://github.com/nextcloud/all-in-one/tree/main/community-containers/vaultwarden) community container since both use `bw.$NC_DOMAIN` as subdomain. Make sure that you do not enable both at the same time!**
- If you want to secure the installation with fail2ban, you might want to check out https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban
- The data of Bitwarden Lite will be automatically included in AIOs backup solution!
- After adding and starting the container, you need to visit `https://bw.your-domain.com/admin` in order to log in with your admin email address. Note that the admin login requires a working SMTP/mail configuration since it sends a one-time-password to the admin email.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/bitwarden/self-host

### Maintainer
https://github.com/szaimen
