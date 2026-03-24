## OpenVPMS
This container bundles [OpenVPMS](https://openvpms.org) — an open-source veterinary practice management system — and auto-configures it for you. It includes a dedicated MariaDB database container.

### Notes
- After adding and starting the container, you can access the OpenVPMS web interface at `http://ip.address.of.server:11000/openvpms/`.
- The data of OpenVPMS and its database will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-openvpms

### Maintainer
https://github.com/szaimen
