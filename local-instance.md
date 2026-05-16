# Local instance

> [!WARNING]
> AIO requires a **real domain name** and a **valid TLS certificate** to function correctly.
> Accessing Nextcloud via a plain IP address or a self-signed certificate is **not supported**.
> The options below describe how to satisfy these requirements while keeping Nextcloud local.

It is possible due to several reasons that you do not want or cannot open Nextcloud to the public internet. Perhaps you were hoping to access AIO directly from an `ip.add.r.ess` (unsupported) or without a valid domain.  However, AIO requires a valid certificate to work correctly. Below is discussed how you can achieve both: Having a valid certificate for Nextcloud and only using it locally.

### Content
- [1. Tailscale](#1-tailscale)
- [2. Pangolin](#2-pangolin)
- [3. The normal way](#3-the-normal-way)
- [4. Use the ACME DNS-challenge](#4-use-the-acme-dns-challenge)
- [5. Use Cloudflare](#5-use-cloudflare)
- [6. Buy a certificate and use that](#6-buy-a-certificate-and-use-that)

## 1. Tailscale
This is the recommended way. For a reverse proxy example guide for Tailscale, see this guide by [@Perseus333](https://github.com/Perseus333): https://github.com/nextcloud/all-in-one/discussions/6817

## 2. Pangolin
[Pangolin](https://pangolin.net/) is an open-source, WireGuard-based remote access platform similar in concept to Tailscale. It uses the **Newt** connector to create outbound-only encrypted tunnels — no inbound ports need to be opened on your firewall. Pangolin handles TLS automatically, providing a valid certificate for your Nextcloud domain.

You can use either [Pangolin Cloud](https://app.pangolin.net/) (free tier available) or [self-host your own Pangolin server](https://docs.pangolin.net/self-host/quick-install) on a VPS. For private/local-only access, self-hosting Pangolin on a machine within your local network means that Nextcloud never needs to be exposed to the public internet.

For the reverse proxy configuration details and a step-by-step setup guide, see the [Pangolin section in the reverse proxy documentation](./reverse-proxy.md#pangolin).

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

