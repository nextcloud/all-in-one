## Gitea
This container bundles [Gitea](https://about.gitea.com/), a lightweight self-hosted Git service, and auto-configures it for you. It ships with its own PostgreSQL database container.

### Notes
- You need to configure a reverse proxy in order to run this container since Gitea needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy or follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md. You need to point the reverse proxy at port 3000 of this server.
- Currently, only `git.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, Gitea will use `git.your-domain.com`. The reverse proxy and domain must be configured accordingly!
- If you use the [caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), the `git.your-domain.com` subdomain is configured automatically. You only need to make sure that the DNS record for `git.your-domain.com` points at your server.
- Make sure that no other service is using port `2222/tcp` on your host as otherwise the container will fail to start. You can check this with `sudo netstat -tulpn | grep 2222` before installing this container. Note that this port is always exposed on all interfaces and is not affected by AIOs `APACHE_IP_BINDING`.
- If you want to secure the installation with fail2ban, you might want to check out https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban. It picks up Gitea's log automatically and bans ip-addresses after failed sign-in attempts.
- The data of Gitea and its database will be automatically included in AIOs backup solution!
- Registration of new users is disabled by default (`DISABLE_REGISTRATION=true`) so that your instance is not open to the public. See below on how to create the first user.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### How to create the first user
Since the web installer and registration are disabled, you need to create the first admin user manually after the container was started. Make sure to wait until the container is fully up and running (you should be able to open `https://git.your-domain.com` in your browser) as the command will otherwise fail with a database connection error:

```
sudo docker exec -it nextcloud-aio-gitea gitea admin user create \
    --admin --username "your-username" --email "your@email.com" --random-password
```

Afterwards you can log in at `https://git.your-domain.com` with the printed password and change it in the user settings. Further users can be invited by the admin user from the web interface.

> [!Note]
> If you do not have CLI access to the server, you can run docker commands via a web session by using this community container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/container-management

### How to use Git over SSH
Port `2222/tcp` is exposed on the host for Git over SSH.

If you want to access Gitea via SSH from outside your local network, you need to forward port `2222/tcp` in your router to your server. Note that this is not needed if you only want to use Gitea via the web interface or clone via HTTPS.

Clone urls will use port `2222`, e.g.:

```
git clone ssh://git@git.your-domain.com:2222/your-username/your-repo.git
```

You can also add this to your `~/.ssh/config` so that the port can be omitted:

```
Host git.your-domain.com
    Port 2222
    User git
```

### Repository
https://github.com/go-gitea/gitea

### Maintainer
https://github.com/szaimen
