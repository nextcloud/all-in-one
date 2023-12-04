## Stalwart mail server
This container bundles stalwart mail server and auto-configures it for you.

### Notes
- This is only intended to run on a VPS with static ip-address.
- Check with `sudo netstat -tulpn` that no other service is using port 25, 143, 465, 578, 993 nor 4190 yet as otherwise the container will fail to start.
- You need to configure a reverse proxy in order to run this container since stalwart needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy.
- Currently, only `mail.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, vaultwarden will use `mail.your-domain.com`.
- The data of Stalwart will be automatically included in AIOs backup solution!
- After adding and starting the container, you need to run `sudo docker exec -it nextcloud-aio-stalwart configure.sh` and follow https://stalw.art/docs/install/docker/#choose-where-to-store-your-data (1. choose `Local disk`, 2. choose `No, create a new directory for me`, 3. type in your `$NC_DOMAIN` as `domain name` and `mail.$NC_DOMAIN` as `server hostname`. 4. add `DKIM, SPF and DMARC` as advised, 5. Take note of the administrator credentials, 6. skip https://stalw.art/docs/install/docker/#add-your-tls-certificate as this is done automatically for you, 7. Review the configuration file, 8. run `sudo docker restart nextcloud-aio-stalwart` in order restart the container and enable the config).
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/marcoambrosini/aio-stalwart

### Maintainer
https://github.com/marcoambrosini
