## Fail2ban
This container bundles fail2ban and auto-configures it for you in order to block ip-addresses automatically. It also covers https://github.com/nextcloud/all-in-one/tree/main/community-containers/vaultwarden, https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyfin, and https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyseerr, if installed.

### Notes
- If you get an error like `"ip6tables v1.8.9 (legacy): can't initialize ip6tables table filter': Table does not exist (do you need to insmod?)"`, you need to enable ip6tables on your host via `sudo modprobe ip6table_filter`.
- If you get an error like  `stderr: 'iptables: No chain/target/match by that name.'` and `stderr: 'ip6tables: No chain/target/match by that name.'`, you need to follow https://github.com/szaimen/aio-fail2ban/issues/9#issuecomment-2026898790 in order to resolve this.
- You can unban ip addresses like so for example: `docker exec -it nextcloud-aio-fail2ban fail2ban-client set nextcloud unbanip 203.113.167.162`.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-fail2ban

### Maintainer
https://github.com/szaimen
