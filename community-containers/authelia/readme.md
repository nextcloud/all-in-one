## Authelia
This container bundles [Authelia](https://www.authelia.com/), a single sign-on provider with two-factor authentication. Once added, a **Log in with Authelia** button appears on the Nextcloud login page. AIO installs and configures the [user_oidc](https://apps.nextcloud.com/apps/user_oidc) app for that automatically.

Logging in with the normal Nextcloud accounts (e.g. `admin`) keeps working.

### Requirements
- **lldap community container:** Authelia uses [lldap](https://github.com/nextcloud/all-in-one/tree/main/community-containers/lldap) to manage its users, so you need to enable both containers. Authelia waits until lldap is available.
- **Reverse proxy:** Authelia needs its own subdomain, which must be `auth.$NC_DOMAIN`. So if Nextcloud uses `your-domain.com`, Authelia uses `auth.your-domain.com`. The easiest way is the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), which sets this up automatically. If you use your own reverse proxy (see [reverse-proxy.md](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md)), point `auth.$NC_DOMAIN` to port `9091`, serve it via https and send the `X-Forwarded-Proto` and `X-Forwarded-Host` headers.
- **Reachability:** The Nextcloud container must be able to reach `https://auth.$NC_DOMAIN` itself, since Nextcloud fetches the login configuration and tokens from there.

For security reasons, port `9091` is only published on `127.0.0.1` of the host. Your reverse proxy therefore needs to run on the same host or inside the AIO docker network (as the Caddy community container does). Reverse proxies on a different host are not supported.

### Setup
1. Enable the lldap and the Authelia community containers in the AIO interface and start the containers. See [how to add community containers](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers).
2. Open the lldap web interface and create your users. Give every user an email address, since Authelia sends one-time codes by mail (see below).
3. Open Nextcloud and click **Log in with Authelia**.

### Users and accounts
Users are managed in the lldap web interface only.

When a user logs in to Nextcloud via Authelia for the first time, a new Nextcloud account is created for them. Its user id is derived from a hash of the Authelia id, so it can never take over an existing Nextcloud account like `admin`.

> [!WARNING]
> Do **not** configure the user_ldap app as described in the lldap documentation when using Authelia. The lldap container installs user_ldap but does not configure it, so by default every lldap user gets exactly one Nextcloud account via Authelia. Configuring user_ldap in addition would create a second, separate Nextcloud account for every user. For the same reason, do not add Authelia to an instance that already uses user_ldap with lldap.

### Two-factor authentication
Two-factor authentication is enforced. On their first login, users are asked to register a second factor, e.g. a TOTP app or a security key.

Before they can do that, Authelia sends them a one-time code by mail to verify their identity. This check is important: without it, anyone who knows a user's password could register their own second factor for that user. How that mail is delivered is described in the next section.

#### Setting up TOTP for a user without mail
As an admin, you can also set up TOTP for a user directly, so that no mail is needed. Replace `USERNAME` with the lldap username and `your-domain.com` with your Nextcloud domain:
```bash
sudo docker exec nextcloud-aio-authelia authelia storage user totp generate USERNAME --issuer your-domain.com --config /config/configuration.yml
```
This prints an `otpauth://` link that contains the secret. Pass it to the user over a secure channel; they can add it to their TOTP app by entering the secret manually or by turning the link into a QR code. Treat it like a password.

### Mails
**With the [Stalwart](https://github.com/nextcloud/all-in-one/tree/main/community-containers/stalwart) community container:** Authelia sends mails as `authelia@$NC_DOMAIN` via Stalwart. For that to work:
1. Create an account `authelia` for the domain `$NC_DOMAIN` in Stalwart.
2. Set its password to the secret that you can see next to the Authelia container in the AIO interface.

If Stalwart is started after Authelia, Authelia restarts itself once to switch to Stalwart.

**Without Stalwart:** All mails are written to a file instead. You can read it with:
```bash
sudo docker exec nextcloud-aio-authelia cat /config/notification.txt
```
You then need to pass the one-time codes on to your users yourself.

### Customizing the configuration
The configuration in `/config/configuration.yml` is regenerated on every container start, so changes to it are lost. To change settings, e.g. to [use another SMTP server](https://www.authelia.com/configuration/notifications/smtp/), create a file `/config/configuration.custom.yml` inside the container with only the values that you want to override and restart the container.

### Backup
The data of Authelia is automatically included in AIOs backup solution.

### Removing the container
After removing the container, also remove the login provider from Nextcloud:
```bash
sudo docker exec --user www-data nextcloud-aio-nextcloud php occ user_oidc:provider:delete Authelia
```

### Repository
https://github.com/szaimen/aio-authelia

### Maintainer
https://github.com/szaimen
