> [!NOTE]
> Unless the starting script tells you, you have no action to do to update.

# UPGRADING

During a major server update, this message will be displayed:

> Your data is in an old format.
> 
> Make a backup and see https://github.com/nextcloud/all-in-one/blob/main/community-containers/stalwart/upgrading.md
> 
> To avoid any loss of data, Stalwart will not launch.

If there is no update, delete the `/opt/stalwart-mail/aio.lock` file from the container. Beware of data loss.

See https://github.com/stalwartlabs/mail-server/blob/main/UPGRADING.md

> [!CAUTION]
> Before each update don't forget to make a backup.

## Upgrading from 0.7.x to 0.8.x

Before upgrading, do a backup of your data !

```bash
sudo docker run --rm -v nextcloud_aio_stalwart:/opt/stalwart-mail -it --entrypoint /usr/local/bin/stalwart-mail stalwartlabs/mail-server:v0.7.3 --config /opt/stalwart-mail/etc/config.toml --export /opt/stalwart-mail/export
sudo docker run --rm -v nextcloud_aio_stalwart:/opt/stalwart-mail -it --entrypoint /usr/local/bin/stalwart-mail stalwartlabs/mail-server:v0.8.0 --config /opt/stalwart-mail/etc/config.toml --import /opt/stalwart-mail/export
sudo docker run --rm -v nextcloud_aio_stalwart:/opt/stalwart-mail -it --entrypoint /bin/rm alpine /opt/stalwart-mail/aio.lock
```
