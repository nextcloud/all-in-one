## Fail2ban
This container bundles fail2ban and auto-configures it for you in order to block ip-addresses automatically.

### Notes
- This is not working on Docker Desktop since it needs `network_mode: host` in order to work correctly.
- If you get an error like `"ip6tables v1.8.9 (legacy): can't initialize ip6tables table filter': Table does not exist (do you need to insmod?)"`, you need to enable ip6tables on your host via `sudo modprobe ip6table_filter`.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-fail2ban

### Maintainer
https://github.com/szaimen
