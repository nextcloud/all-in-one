# DDclient community container

This container runs [DDclient](https://ddclient.net/) pre-configured for use with [deSEC](https://desec.io/) dynamic DNS.

## How it works

When you register a free dedyn.io domain through the AIO interface the `NC_DOMAIN` and `DESEC_TOKEN` environment variables are automatically populated from the stored credentials.

On first start, if `/config/ddclient.conf` does not yet exist and both `NC_DOMAIN` and `DESEC_TOKEN` are set, the container automatically generates a ready-to-use `ddclient.conf`:

```
daemon=300
syslog=yes
ssl=yes

use=web, web=https://checkipv4.dedyn.io/

protocol=dyndns2
server=update.dedyn.io
login=<NC_DOMAIN>
password=<DESEC_TOKEN>
<NC_DOMAIN>
```

No manual configuration step is required.

## Relationship to the AIO mastercontainer

The AIO mastercontainer already updates the deSEC DNS record every time containers are started and once per cron cycle (roughly every minute). This container adds a second, independent layer of DNS updates that runs continuously every 5 minutes via the ddclient daemon — useful if the host IP can change while the containers are running between cron cycles.

## Notes

- The config volume (`nextcloud_aio_ddclient`) is included in AIO backups, so the configuration persists across updates and restores.
- If the config file already exists (e.g., you customised it previously), it will **not** be overwritten on restart.
- For IPv6 support, add a second `use` block pointing to `https://checkipv6.dedyn.io/` in the config file. See the [ddclient documentation](https://ddclient.net/protocols/dyndns2.html) for details.
- This image is derived from [ghcr.io/linuxserver/ddclient](https://github.com/linuxserver/docker-ddclient) and adds the auto-configuration script via the linuxserver `/custom-cont-init.d/` mechanism.
