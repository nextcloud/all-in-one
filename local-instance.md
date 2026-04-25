# Local instance
It is possible due to several reasons that you do not want or cannot open Nextcloud to the public internet. Perhaps you were hoping to access AIO directly from an `ip.add.r.ess` (unsupported) or without a valid domain.  However, AIO requires a valid certificate to work correctly. Below is discussed how you can achieve both: Having a valid certificate for Nextcloud and only using it locally.

### Content
- [1. Tailscale](#1-tailscale)
- [2. deSEC free domain](#2-desec-free-domain)
- [3. The normal way](#3-the-normal-way)
- [4. Use the ACME DNS-challenge](#4-use-the-acme-dns-challenge)
- [5. Use Cloudflare](#5-use-cloudflare)
- [6. Buy a certificate and use that](#6-buy-a-certificate-and-use-that)

## 1. Tailscale
This is the recommended way. For a reverse proxy example guide for Tailscale, see this guide by [@Perseus333](https://github.com/Perseus333): https://github.com/nextcloud/all-in-one/discussions/6817

## 2. deSEC free domain
[deSEC](https://desec.io) offers free dynamic-DNS subdomains under `dedyn.io`. AIO can register an account and a subdomain for you automatically — directly from the domain-entry page of the AIO interface. After registration:
- The [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container is enabled automatically as a reverse proxy and handles TLS via Let's Encrypt.
- The [dnsmasq](https://github.com/nextcloud/all-in-one/tree/main/community-containers/dnsmasq) community container is enabled automatically so that LAN clients resolve your Nextcloud domain to the server's local IP address — no separate Pi-hole or local DNS server required.
- The mastercontainer keeps the DNS record up to date automatically when your public IP changes.

**How to set it up:** Open the AIO interface, expand the **"Don't have a domain? Get a free one from deSEC"** section, enter your email and an optional subdomain slug, and click **Register free domain via deSEC**. See the full documentation at [How to get a free domain via deSEC](https://github.com/nextcloud/all-in-one#how-to-get-a-free-domain-via-desec).

After registration, follow the [dnsmasq documentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers/dnsmasq) to point your router's DHCP DNS server to the AIO host so that all LAN devices resolve the domain locally.

## 3. The normal way
The normal way is the following:
1. Set up your domain correctly to point to your home network
1. Set up a reverse proxy by following the [reverse proxy documentation](./reverse-proxy.md) but only open port 80 (which is needed for the ACME challenge to work - however no real traffic will use this port).
1. Set up a local DNS-server like a pi-hole and configure it to be your local DNS-server for the whole network. Then in the Pi-hole interface, add a custom DNS-record for your domain and overwrite the A-record (and possibly the AAAA-record, too) to point to the private ip-address of your reverse proxy (see https://github.com/nextcloud/all-in-one#how-can-i-access-nextcloud-locally)
1. Enter the ip-address of your local dns-server in the daemon.json file for docker so that you are sure that all docker containers use the correct local dns-server.
1. Now, entering the domain in the AIO-interface should work as expected and should allow you to continue with the setup

**Hint:** You may have a look at [this video](https://youtu.be/zk-y2wVkY4c) for a more complete but possibly outdated example.

## 4. Use the ACME DNS-challenge
You can alternatively use the ACME DNS-challenge to get a valid certificate for Nextcloud. Here is described how to set it up using an external caddy reverse proxy: https://github.com/nextcloud/all-in-one#how-to-get-nextcloud-running-using-the-acme-dns-challenge

## 5. Use Cloudflare
If you do not have any control over the network, you may think about using Cloudflare Tunnel to get a valid certificate for your Nextcloud. However it will be opened to the public internet then. See https://github.com/nextcloud/all-in-one#how-to-run-nextcloud-behind-a-cloudflare-tunnel how to set this up.

## 6. Buy a certificate and use that
If none of the above ways work for you, you may simply buy a certificate from an issuer for your domain. You then download the certificate onto your server, configure AIO in [reverse proxy mode](./reverse-proxy.md) and use the certificate for your domain in your reverse proxy config.

