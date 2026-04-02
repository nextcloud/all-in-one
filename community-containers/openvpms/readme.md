## OpenVPMS
This container bundles [OpenVPMS](https://openvpms.org) — an open-source veterinary practice management system — and auto-configures it for you. It includes a dedicated MariaDB database container.

### Notes
- You need to enable the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) as it is required to expose the OpenVPMS web interface. The web interface will be available at `https://vpms.your-nc-domain.com/openvpms` once Caddy is running.
- You need to point `vpms.your-nc-domain.com` to your server using a CNAME or A/AAAA record so that Caddy can obtain a TLS certificate automatically.
- It is recommended to also enable the [Fail2ban community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban) to automatically block IP addresses with too many failed login attempts.
- A dedicated Redis instance is automatically started alongside OpenVPMS to store HTTP sessions externally, reducing JVM heap pressure and improving overall throughput.
- The data of OpenVPMS and its database will be automatically included in AIO's backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-openvpms

### Maintainer
https://github.com/szaimen
