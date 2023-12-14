## Stalwart mail server
This container bundles stalwart mail server and auto-configures it for you.

### Notes
- This is only intended to run on a VPS with static ip-address.
- Check with `sudo netstat -tulpn` that no other service is using port 25, 143, 465, 578, 993 nor 4190 yet as otherwise the container will fail to start.
- You need to configure a reverse proxy in order to run this container since stalwart needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy.
- Currently, only `mail.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, Stalwart will use `mail.your-domain.com`.
- The data of Stalwart will be automatically included in AIOs backup solution!
- After adding and starting the container, you need to run `sudo docker exec -it nextcloud-aio-stalwart configure.sh` and follow https://stalw.art/docs/install/docker/#choose-where-to-store-your-data (1. choose `Local disk using Maildir`, 2. choose `No, create a new directory for me` (or select LDAP if you have an LDAP server), 3. type in your `$NC_DOMAIN` as `domain name` and `mail.$NC_DOMAIN` as `server hostname`. 4. add `DKIM, SPF and DMARC` as advised to your DNS config, 5. Take note of the administrator credentials, 6. Now the config script should exit and automatically restart the container and enable your config.
- See https://stalw.art/docs/directory/types/memory/ how you can easily create new user accounts. (Alternatively see https://stalw.art/docs/directory/types/ldap if you have an LDAP server). You can edit the config file with `sudo docker exec -it nextcloud-aio-stalwart vi /opt/stalwart-mail/etc/config.toml`. Also, you might want to enable logging to stdout so that you can see the stalwart logs in your container logs via `sudo docker exec -it nextcloud-aio-stalwart vi /opt/stalwart-mail/etc/common/tracing.toml` (you need to restart the container afterwards with `sudo docker restart nextcloud-aio-stalwart` in order to apply the settings).
- Afterwards, you can visit the basic admin settings in `https://your-nc-domain.com/settings/admin` and add the your mail server for outgoing mails there.
- Additionally, you might want to install and configure [snappymail](https://apps.nextcloud.com/apps/snappymail) or [mail](https://apps.nextcloud.com/apps/mail) inside Nextcloud in order to use your mail accounts for sending and retrieving mails.
- See https://stalw.art/docs/faq for further faq and docs on the project
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/marcoambrosini/aio-stalwart

### Maintainer
https://github.com/marcoambrosini
