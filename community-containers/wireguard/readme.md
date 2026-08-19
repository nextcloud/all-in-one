## WireGuard Easy
This container bundles [wg-easy](https://github.com/wg-easy/wg-easy), a WireGuard VPN server with a web UI for managing client connections, and auto-configures it for you.

### Notes
- ⚠️ This container opens a VPN entry point into your server's network. Only add it if you understand the implications and keep it updated!
- ⚠️ This container is only intended to be used on a server in a trusted home network. Do **not** add it on a public VPS or any other server whose IP address is directly reachable from the internet!
- ⚠️ The web UI on port `51821/tcp` is served via plain HTTP (`INSECURE=true`) and is published on **all** interfaces. So if you still run this on a server that is reachable from the internet, you need to block port `51821/tcp` with a firewall, as otherwise the admin UI is reachable by everyone and the credentials that you enter there are transmitted unencrypted.
- ⚠️ This container only works on Linux hosts with Docker installed natively. Docker Desktop (Windows, macOS and Linux) is **not** supported since the containers run inside a virtual machine there whose kernel does not provide the WireGuard module to the container.
- ⚠️ This container requires the `wireguard` kernel module on the host and fails to start without it. It ships as a loadable module (`CONFIG_WIREGUARD=m`) on all common distributions with kernel 5.6 or later, so you may need to load it once via `sudo modprobe wireguard` and make that persistent, e.g. via `echo wireguard | sudo tee /etc/modules-load.d/wireguard.conf`. If your kernel is older, you need to install the module on the host first.
- The admin account is created automatically on the first start. The username is `admin` and the password is the one that you can see next to the container in the AIO interface.
- After adding and starting the container, you can visit `http://ip.address.of.this.server:51821` in order to log in and create clients. Each client can be exported as a config file or QR code.
- Port `51820/udp` needs to be forwarded in your router to this server in order to be able to connect from the internet. Port `51821/tcp` (the web UI) must **not** be forwarded!
- The VPN host address that clients will connect to is set to your Nextcloud domain by default. You can change this in the web UI if you want to connect via a different address.
- This container may conflict with the fail2ban community container as both modify iptables rules. Watch out for unexpected behavior if you enable both.
- The configuration and clients of WireGuard will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/wg-easy/wg-easy

### Maintainer
https://github.com/szaimen
