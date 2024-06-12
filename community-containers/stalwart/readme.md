> [!WARNING]
> The Stalwart server is under development.
> 
> The stability of Stalwart services is not guaranteed.
> Do not use this feature as a main mail server without a redundancy system and without knowledge.
> 
> To learn or use as a secondary server enjoy it and please report bugs at [docjyj/aio-stalwart](https://github.com/docjyj/aio-stalwart/issues).

## Stalwart mail server
This container bundles stalwart mail server and auto-configures it for you.

### Notes
- This is only intended to run on a VPS with static ip-address.
- Check with `sudo netstat -tulpn` that no other service is using port 25, 143, 465, 587, 993 nor 4190 yet as otherwise the container will fail to start.
- You need to configure a reverse proxy in order to run this container since stalwart needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy.
- Currently, only `mail.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, Stalwart will use `mail.your-domain.com`.
- The data of Stalwart will be automatically included in AIOs backup solution!
- After adding and starting the container, you need to run `docker inspect nextcloud-aio-stalwart  | grep STALWART_USER_PASS` to obtain the system administrator password (username: `admin`). With this information, you can log in to the web interface at `https://mail.your-domain.com/login`
- See https://stalw.art/docs/install/docker/ for next steps.
- Additionally, you might want to install and configure [snappymail](https://apps.nextcloud.com/apps/snappymail) or [mail](https://apps.nextcloud.com/apps/mail) inside Nextcloud in order to use your mail accounts for sending and retrieving mails.
- See https://stalw.art/docs/faq for further faq and docs on the project
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/docjyj/aio-stalwart

### Maintainer
https://github.com/docjyj
