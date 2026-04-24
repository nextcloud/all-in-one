# Dnsmasq (Local DNS) community container

This container runs [dnsmasq](https://thekelleys.org.uk/dnsmasq/doc.html) pre-configured to resolve your Nextcloud domain (`NC_DOMAIN`) to the server's local LAN IP address.

## Why is this needed?

By default, all devices on your LAN reach Nextcloud via the public internet (or require hairpin NAT on your router). With this container, LAN clients can resolve `NC_DOMAIN` directly to the server's private LAN IP, making local access faster and independent of your internet connection.

This container is automatically enabled when you register a deSEC domain through the AIO interface.

## How it works

On startup the container:
1. Detects the server's primary LAN IP address automatically.
2. Configures dnsmasq to resolve `NC_DOMAIN` (and all its subdomains) to that IP.
3. Forwards all other DNS queries to the upstream nameservers from the host's `/etc/resolv.conf`.
4. Listens only on the LAN interface to avoid conflicts with any system DNS resolver (e.g. `systemd-resolved`).

## Required router configuration

⚠️ **You must change your router's DHCP settings** for this to take effect for LAN clients:

Set the **DNS server** handed out by DHCP to the **local IP address of this server** (the same IP that is printed in the container logs on startup). After saving the change, LAN devices need to renew their DHCP lease (or be rebooted) before the new DNS setting takes effect.

Most routers expose this under **DHCP settings → Primary DNS** or **LAN → DNS Server**.

## Notes

- The container runs in **host network mode** so it can bind directly to port 53 on the LAN interface. No additional port-forwarding is required.
- If `systemd-resolved` (or another DNS resolver) is already listening on port 53 on the LAN IP, there will be a conflict. In that case you need to disable or reconfigure that resolver first.
- IPv6 addresses are not handled by this container; extend the dnsmasq configuration manually if needed.
