# Local instance
It is possible due to several reasons that you do not want or cannot open Nextcloud to the public internet. Perhaps you were hoping to access AIO directly from an `ip.add.r.ess` (unsupported) or without a valid domain.  However, AIO requires a valid certificate to work correctly. Below is discussed how you can achieve both: Having a valid certificate for Nextcloud and only using it locally.

### Content
- [1. The recommended way](#1-the-recommended-way)
- [2. Use the ACME DNS-challenge](#2-use-the-acme-dns-challenge)
- [3. Use Cloudflare](#3-use-cloudflare)
- [4. Buy a certificate and use that](#4-buy-a-certificate-and-use-that)
- [5. Tailscale network](#5-tailscale-network)

## 1. The recommended way
The recommended way is the following:
1. Set up your domain correctly to point to your home network
1. Set up a reverse proxy by following the [reverse proxy documentation](./reverse-proxy.md) but only open port 80 (which is needed for the ACME challenge to work - however no real traffic will use this port).
1. Set up a local DNS-server like a pi-hole and configure it to be your local DNS-server for the whole network. Then in the Pi-hole interface, add a custom DNS-record for your domain and overwrite the A-record (and possibly the AAAA-record, too) to point to the private ip-address of your reverse proxy (see https://github.com/nextcloud/all-in-one#how-can-i-access-nextcloud-locally)
1. Enter the ip-address of your local dns-server in the daemon.json file for docker so that you are sure that all docker containers use the correct local dns-server.
1. Now, entering the domain in the AIO-interface should work as expected and should allow you to continue with the setup

**Hint:** You may have a look at [this video](https://youtu.be/zk-y2wVkY4c) for a more complete but possibly outdated example.

## 2. Use the ACME DNS-challenge
You can alternatively use the ACME DNS-challenge to get a valid certificate for Nextcloud. Here is described how to set it up: https://github.com/nextcloud/all-in-one#how-to-get-nextcloud-running-using-the-acme-dns-challenge

## 3. Use Cloudflare
If you do not have any control over the network, you may think about using Cloudflare Tunnel to get a valid certificate for your Nextcloud. However it will be opened to the public internet then. See https://github.com/nextcloud/all-in-one#how-to-run-nextcloud-behind-a-cloudflare-tunnel how to set this up.

## 4. Buy a certificate and use that
If none of the above ways work for you, you may simply buy a certificate from an issuer for your domain. You then download the certificate onto your server, configure AIO in [reverse proxy mode](./reverse-proxy.md) and use the certificate for your domain in your reverse proxy config.

## 5. Tailscale network
For a reverse proxy example guide for Tailscale, see this guide by @flll: https://github.com/nextcloud/all-in-one/discussions/5439
