# DDclient community container

This container runs [DDclient](https://ddclient.net/) pre-configured for use with [deSEC](https://desec.io/) dynamic DNS.

When you register a free dedyn.io domain through the AIO interface, the `NC_DOMAIN` and `DESEC_TOKEN` environment variables are automatically set from the stored credentials. On first start the linuxserver/ddclient image creates an empty `/config/ddclient.conf` in the `nextcloud_aio_ddclient` volume; you need to populate that file once as described below.

## One-time configuration

After the container has started for the first time, run:

```bash
docker exec -it nextcloud-aio-ddclient sh
```

Then create `/config/ddclient.conf` with the following content (replace the placeholders with the values printed in the container's environment):

```
daemon=300
syslog=yes
ssl=yes

use=web, web=https://checkipv4.dedyn.io/

protocol=dyndns2
server=update.dedyn.io
login=<value of NC_DOMAIN>
password=<value of DESEC_TOKEN>
<value of NC_DOMAIN>
```

You can read the values from the running container:

```bash
docker exec nextcloud-aio-ddclient printenv NC_DOMAIN
docker exec nextcloud-aio-ddclient printenv DESEC_TOKEN
```

Once the file is saved, restart the container:

```bash
docker restart nextcloud-aio-ddclient
```

DDclient will now update the DNS record for your domain every 5 minutes.

## Notes

- The config volume (`nextcloud_aio_ddclient`) is included in AIO backups, so the configuration persists across updates and restores.
- A derivative image that auto-generates the config from `NC_DOMAIN` and `DESEC_TOKEN` without any manual step will be created in a dedicated repository in the future.
- For IPv6 support add a second `use` block pointing to `https://checkipv6.dedyn.io/` in the config file. See the [ddclient documentation](https://ddclient.net/protocols/dyndns2.html) for details.
