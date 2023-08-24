# Reverse Proxy Documentation

A [reverse proxy](https://en.wikipedia.org/wiki/Reverse_proxy) is basically a web server that enables computers on the internet to access a service in a [private subnet](https://en.wikipedia.org/wiki/Private_network).

**Please note:** Publishing the AIO interface with a valid certificate to the public internet is **not** the goal of this documentation! Instead, the main goal is to publish Nextcloud with a valid certificate to the public internet which is **not** running inside the mastercontainer but in a different container! If you need a valid certificate for the AIO interface, see [point 5](#5-optional-get-a-valid-certificate-for-the-aio-interface).

In order to run Nextcloud behind a web server or reverse proxy (like Apache, Nginx, Cloudflare Tunnel and else), you need to specify the port that AIO's Apache container shall use, add a specific config to your web server or reverse proxy and modify the startup command a bit. All examples below will use port `11000` as example Apache port which will be exposed on the host. Modify the port to your needings.

**Attention:** The process to run Nextcloud behind a reverse proxy consists of at least steps 1, 2 and 4:
1. **Configure the reverse proxy! See [point 1](#1-add-this-to-your-reverse-proxy-config)**
1. **Use the in this document provided startup command! See [point 2](#2-use-this-startup-command)**
1. Optional: If the reverse proxy is installed on the same host and in the host network, you should limit the apache container to only listen on localhost. See [point 3](#3-limit-the-access-to-the-apache-container)
1. **Open the AIO interface. See [point 4](#4-open-the-aio-interface)**
1. Optional: Get a valid certificate for the AIO interface! See [point 5](#5-optional-get-a-valid-certificate-for-the-aio-interface)
1. Optional: How to debug things? See [point 6](#6-how-to-debug-things)

## 1. Add this to your reverse proxy config

**Please note:** Since the Apache container gets created by the mastercontainer, there is **NO** way to provide custom docker labels or custom environmental variables for the Apache container. So please do not attempt to do this because you will fail! Only the documented way will work!

### Apache

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Add this as a new Apache site config:

(The config below assumes that you are using certbot to get your certificates. You need to create them first in order to make it work.)

```
<VirtualHost *:80>
    ServerName <your-nc-domain>

    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
    RewriteCond %{SERVER_NAME} =<your-nc-domain>
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>

<VirtualHost *:443>
    ServerName <your-nc-domain>

    # Reverse proxy based on https://httpd.apache.org/docs/current/mod/mod_proxy_wstunnel.html
    RewriteEngine On
    ProxyPreserveHost On
    AllowEncodedSlashes NoDecode
    
    ProxyPass / http://localhost:11000/ nocanon
    ProxyPassReverse / http://localhost:11000/
    
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteCond %{THE_REQUEST} "^[a-zA-Z]+ /(.*) HTTP/\d+(\.\d+)?$"
    RewriteRule .? "ws://localhost:11000/%1" [P,L]

    # Enable h2, h2c and http1.1
    Protocols h2 h2c http/1.1
    
    # Solves slow upload speeds caused by http2
    H2WindowSize 5242880

    # TLS
    SSLEngine               on
    SSLProtocol             -all +TLSv1.2 +TLSv1.3
    SSLCipherSuite          ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305
    SSLHonorCipherOrder     off
    SSLSessionTickets       off
    SSLCertificateFile /etc/letsencrypt/live/<your-nc-domain>/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/<your-nc-domain>/privkey.pem

    # Disable HTTP TRACE method.
    TraceEnable off
    <Files ".ht*">
        Require all denied
    </Files>

    # Support big file uploads
    LimitRequestBody 0
</VirtualHost>
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

To make the config work you can run the following command:
`sudo a2enmod rewrite proxy proxy_http proxy_wstunnel ssl headers http2`

</details>

### Caddy (Recommended)

<details>

<summary>click here to expand</summary>

Add this to your Caddyfile:

```
https://<your-nc-domain>:443 {
    reverse_proxy localhost:11000
}
```
The Caddyfile is a text file called `Caddyfile` (no extension) which – if you should be running Caddy inside a container – should usually be created in the same location as your `compose.yaml` file prior to starting the container.

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

**Advice:** You may have a look at [this](https://github.com/nextcloud/all-in-one/discussions/575#discussion-4055615) for a more complete example.

</details>

### Caddy with ACME DNS-challenge

<details>

<summary>click here to expand</summary>

You can get AIO running using the ACME DNS-challenge. Here is how to do it.

1. Follow [this documentation](https://caddy.community/t/how-to-use-dns-provider-modules-in-caddy-2/8148) in order to get a Caddy build that is compatible with your domain provider's DNS challenge.
1. Add this to your Caddyfile:
    ```
    https://<your-nc-domain>:443 {
        reverse_proxy localhost:11000
        tls {
            dns <provider> <key>
        }
    }
    ```
   Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. You also need to adjust `<provider>` and `<key>` to match your case. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***
1. Now continue with [point 2](#2-use-this-startup-command) but additionally, add `--env SKIP_DOMAIN_VALIDATION=true` to the docker run command of the mastercontainer (but before the last line `nextcloud/all-in-one:latest`) which will disable the dommain validation (because it is known that the domain validation will not when using the DNS-challenge since no port is publicly opened.

**Advice:** In order to make it work in your home network, you may add the internal ipv4-address of your reverse proxy as A DNS-record to your domain and disable the dns-rebind-protection in your router. Another way it to set up a local dns-server like a pi-hole and set up a custom dns-record for that domain that points to the internal ip-adddress of your reverse proxy (see https://github.com/nextcloud/all-in-one#how-can-i-access-nextcloud-locally). If both is not possible, you may add the domain to the hosts file which is needed then for any devices that shall use the server.

</details>

### Citrix ADC VPX / Citrix Netscaler 

<details>

<summary>click here to expand</summary>

For a reverse proxy example guide for Citrix ADC VPX / Citrix Netscaler, see this guide by @esmith443: https://github.com/nextcloud/all-in-one/discussions/2452

</details>

### Cloudflare Tunnel

<details>

<summary>click here to expand</summary>

Although it does not seems like it is the case but from AIO perspective a Cloudflare Tunnel works like a reverse proxy. Please see the [caveats](https://github.com/nextcloud/all-in-one#notes-on-cloudflare-proxytunnel) before proceeding. Here is then how to make it work:

1. Install the Cloudflare Tunnel on the same machine where AIO will be running on and point the Tunnel with the domain that you want to use for AIO to `http://localhost:11000`. ***If the Tunnel is running on a different machine, you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***
1. Now continue with [point 2](#2-use-this-startup-command) but additionally, add `--env SKIP_DOMAIN_VALIDATION=true` to the docker run command which will disable the dommain validation (because it is known that the domain validation will not work behind a Cloudflare Tunnel). So you need to ensure yourself that you've configured everything correctly.

**Advice:** Make sure to [disable Cloudflares Rocket Loader feature](https://help.nextcloud.com/t/login-page-not-working-solved/149417/8) as otherwise Nextcloud's login prompt will not be shown.

</details>

### HaProxy

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Here is an example HaProxy config:

```
global
    chroot                      /var/haproxy
    log                         /var/run/log audit debug
    lua-prepend-path            /tmp/haproxy/lua/?.lua
    ssl-default-bind-ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305
    ssl-default-bind-ciphersuites TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256
    ssl-default-bind-options ssl-min-ver TLSv1.2 no-tls-tickets

    ssl-default-server-ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305
    ssl-default-server-ciphersuites TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256
    ssl-default-server-options ssl-min-ver TLSv1.2 no-tls-tickets

defaults
    log     global
    option redispatch -1
    retries 3
    default-server init-addr last,libc

# Frontend: LetsEncrypt_443 ()
frontend LetsEncrypt_443
    bind 0.0.0.0:443 name 0.0.0.0:443 ssl crt-list /tmp/haproxy/ssl/605f6609f106d1.17683543.certlist 
    mode http
    option http-keep-alive
    default_backend acme_challenge_backend
    option forwardfor
    # tuning options
    timeout client 30s

    # logging options
    # ACL: find_acme_challenge
    acl acl_605f6d4b6453d2.03059920 path_beg -i /.well-known/acme-challenge/
    # ACL: Nextcloud
    acl acl_60604e669c3ca4.13013327 hdr(host) -i <your-nc-domain>

    # ACTION: redirect_acme_challenges
    use_backend acme_challenge_backend if acl_605f6d4b6453d2.03059920
    # ACTION: Nextcloud
    use_backend Nextcloud if acl_60604e669c3ca4.13013327


# Frontend: LetsEncrypt_80 ()
frontend LetsEncrypt_80
    bind 0.0.0.0:80 name 0.0.0.0:80 
    mode tcp
    default_backend acme_challenge_backend
    # tuning options
    timeout client 30s

    # logging options
    # ACL: find_acme_challenge
    acl acl_605f6d4b6453d2.03059920 path_beg -i /.well-known/acme-challenge/

    # ACTION: redirect_acme_challenges
    use_backend acme_challenge_backend if acl_605f6d4b6453d2.03059920

# Frontend (DISABLED): 1_HTTP_frontend ()

# Frontend (DISABLED): 1_HTTPS_frontend ()

# Frontend (DISABLED): 0_SNI_frontend ()

# Backend: acme_challenge_backend (Added by Let's Encrypt plugin)
backend acme_challenge_backend
    # health checking is DISABLED
    mode http
    balance source
    # stickiness
    stick-table type ip size 50k expire 30m  
    stick on src
    # tuning options
    timeout connect 30s
    timeout server 30s
    http-reuse safe
    server acme_challenge_host 127.0.0.1:43580 

# Backend: Nextcloud ()
backend Nextcloud
    mode http
    balance source
    server Nextcloud localhost:11000 
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

</details>

### Nginx

<details>

<summary>click here to expand</summary>

**Disclaimer:** This config was tested and should normally work on all modern nginx version if you configure it correctly. Improvements to the config are very welcome!

Add the below template to your nginx config.

**Note:** please check your nginx version by running: `nginx -v` and adjust it the lines marked with version notes, so that they fit your nginx version.

```
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    listen [::]:80;            # comment to disable IPv6

    if ($scheme = "http") {
        return 301 https://$host$request_uri;
    }

    listen 443 ssl http2;      # for nginx versions below v1.25.1
    listen [::]:443 ssl http2; # for nginx versions below v1.25.1 - comment to disable IPv6

    # listen 443 ssl;      # for nginx v1.25.1+
    # listen [::]:443 ssl; # for nginx v1.25.1+ - keep comment to disable IPv6
	
    # http2 on;                                 # uncomment to enable HTTP/2        - supported on nginx v1.25.1+
    # http3 on;                                 # uncomment to enable HTTP/3 / QUIC - supported on nginx v1.25.0+
    # quic_retry on;                            # uncomment to enable HTTP/3 / QUIC - supported on nginx v1.25.0+
    # add_header Alt-Svc 'h3=":443"; ma=86400'; # uncomment to enable HTTP/3 / QUIC - supported on nginx v1.25.0+
    # listen 443 quic reuseport;       # uncomment to enable HTTP/3 / QUIC - supported on nginx v1.25.0+ - please remove "reuseport" if there is already another quic listener on port 443 with enabled reuseport
    # listen [::]:443 quic reuseport;  # uncomment to enable HTTP/3 / QUIC - supported on nginx v1.25.0+ - please remove "reuseport" if there is already another quic listener on port 443 with enabled reuseport - keep comment to disable IPv6

    server_name <your-nc-domain>;

    location / {
        proxy_pass http://127.0.0.1:11000$request_uri;

        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Scheme $scheme;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Accept-Encoding "";
        proxy_set_header Host $host;
    
        client_body_buffer_size 512k;
        proxy_read_timeout 86400s;
        client_max_body_size 0;

        # Websocket
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }

    ssl_certificate /etc/letsencrypt/live/<your-nc-domain>/fullchain.pem;   # managed by certbot on host machine
    ssl_certificate_key /etc/letsencrypt/live/<your-nc-domain>/privkey.pem; # managed by certbot on host machine

    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m; # about 40000 sessions
    ssl_session_tickets off;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers on;

    # Optional settings:

    # OCSP stapling
    # ssl_stapling on;
    # ssl_stapling_verify on;
    # ssl_trusted_certificate /etc/letsencrypt/live/<your-nc-domain>/chain.pem;

    # replace with the IP address of your resolver
    # resolver 127.0.0.1; # needed for oscp stapling: e.g. use 94.140.15.15 for adguard / 1.1.1.1 for cloudflared or 8.8.8.8 for google - you can use the same nameserver as listed in your /etc/resolv.conf file
}

```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `127.0.0.1` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

**Advice:** You may have a look at [this](https://github.com/nextcloud/all-in-one/discussions/588#discussioncomment-2811152) for a more complete example.

</details>

### Nginx-Proxy-Manager

<details>

<summary>click here to expand</summary>

First, please make sure that the environmental variables `PUID` and `PGID` in the compose.yaml file for NPM are either unset or set to `0`.
If you need to change the GID/PID then please add `net.ipv4.ip_unprivileged_port_start=0` at the end of `/etc/sysctl.conf`. Note: this will cause that non root users can bind privilleged ports.

Second, see these screenshots for a working config:

![grafik](https://user-images.githubusercontent.com/75573284/213889707-b7841ca0-3ea7-4321-acf6-50e1c1649442.png)

![grafik](https://user-images.githubusercontent.com/75573284/213889724-1ab32264-3e0c-4d83-b067-9fe9d1672fb2.png)

![grafik](https://user-images.githubusercontent.com/75573284/213889797-42642302-b079-4378-a4a6-079f4f67058c.png)

![grafik](https://user-images.githubusercontent.com/75573284/213889746-87dbe8c5-4d1f-492f-b251-bbf82f1510d0.png)

```
client_body_buffer_size 512k;
proxy_read_timeout 86400s;
client_max_body_size 0;
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also change `<you>@<your-mail-provider-domain>` to a mail address of yours. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

</details>

### Nginx-Proxy

<details>

<summary>click here to expand</summary>

Unfortunately it is not possible to configure nginx-proxy in a way that works because it completely relies on environmental variables of the docker containers itself. Providing these variables does not work as stated above.

If you really want to use AIO, we recommend you to switch to caddy. It is simply amazing!<br>
Of course understandable if that is not possible for you.

Apart from that, there is this: [manual-install](https://github.com/nextcloud/all-in-one/tree/main/manual-install)

</details>

### Node.js with Express

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

For Node.js, we will use the npm package `http-proxy`. WebSockets must be handled separately.

This example only uses `http`, but if your Express server already uses a `https` server, then follow the same instructions for `https`.

```js
const HttpProxy = require('http-proxy');
const express = require('express');
const http = require('http');

const app = express();
const proxy = HttpProxy.createProxyServer({
	target: 'http://localhost:11000',
	// Timeout can be changed to your liking.
	timeout: 1000 * 60 * 3,
	proxyTimeout: 1000 * 60 * 3,
	// Not 100% certain whether autoRewrite is necessary, but enabling it SEEMS to make it behave more stably.
	autoRewrite: true,
	// Do not enable followRedirects.
	followRedirects: false,
});

// Handle errors with proxy.web and proxy.ws
function onProxyError(err, req, res, target) {
	// Handle errors however you like. Here's an example:
	if (err.code === 'ECONNREFUSED') {
		return res.status(503).send('Nextcloud server is currently not running. It may be down for temporary maintenance.');
	}
	// other errors
	else {
		console.error(err);
		return res.status(500).send(String(err));
	}
}

app.use((req, res) => {
	proxy.web(req, res, {}, onProxyError);
});

const httpServer = http.createServer(app);
httpServer.listen('80');

// Listen for an upgrade to a WebSocket connection.
httpServer.on('upgrade', (req, socket, head) => {
	proxy.ws(req, socket, head, {}, onProxyError);
});
```

If you are using the Express package `vhost` for your app, you can use `proxy.web` inside the vhosted express function (see the following code snippet), but `proxy.ws` still needs to be done "globally" on your http server. Nextcloud should automatically ignore websocket requests for other domains.

```js
const HttpProxy = require('http-proxy');
const express = require('express');
const http = require('http');

const myNextcloudApp = express();
const myOtherApp = express();
const vhost = express();

// Definitions for proxy and onProxyError unchanged. (see above)

myNextcloudApp.use((req, res) => {
	proxy.web(req, res, {}, onProxyError);
});

vhost.use(vhostFunc('<your-nextcloud-domain>', myNextcloudApp));

const httpServer = http.createServer(app);
httpServer.listen('80');

// Listen for an upgrade to a WebSocket connection.
httpServer.on('upgrade', (req, socket, head) => {
	proxy.ws(req, socket, head, {}, onProxyError);
});
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. 
**Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

</details>

### Synology Reverse Proxy

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

See these screenshots for a working config:

![image](https://user-images.githubusercontent.com/89748315/192525606-48cab54b-866e-4964-90a8-15e71bd362fb.png)

![image](https://user-images.githubusercontent.com/70434961/213193789-fa936edc-e307-4e6a-9a53-ae26d1bf2f42.jpg)

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen `APACHE_PORT`. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

</details>

### Traefik 2

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Traefik's building blocks (router, service, middlewares) need to be defined using dynamic configuration similar to [this](https://doc.traefik.io/traefik/providers/file/#configuration-examples) official Traefik configuration example. Using **docker labels _won't work_** because of the nature of the project.

The examples below define the dynamic configuration in YAML files. If you rather prefer TOML, use a YAML to TOML converter.

1. In Traefik's static configuration define a [file provider](https://doc.traefik.io/traefik/providers/file/) for dynamic providers:

    ```yml
    # STATIC CONFIGURATION
   
    entryPoints:
        https:
            address: ":443" # Create an entrypoint called "https" that uses port 443
    
    certificatesResolvers:
        # Define "letsencrypt" certificate resolver
        letsencrypt:
            acme:
                storage: /letsencrypt/acme.json # Defines the path where certificates should be stored
                email: <your-email-address> # Where LE sends notification about certificates expiring
                tlschallenge: true
   
    providers:
        file:
            directory: "/path/to/dynamic/conf" # Adjust the path according your needs.
            watch: true
    ```

1. Declare the router, service and middlewares for Nextcloud in `/path/to/dynamic/conf/nextcloud.yml`:

    ```yml
    http:
        routers:
            nextcloud:
                rule: "Host(<your-nextcloud-domain>)"
                entrypoints:
                    - "https"
                service: nextcloud
                middlewares:
                    - nextcloud-chain
                tls:
                    certresolver: "letsencrypt"

        services:
            nextcloud:
                loadBalancer:
                    servers:
                        - url: "http://localhost:11000" # Use the host's IP address if Traefik runs outside the host network

        middlewares:
            nextcloud-secure-headers:
                headers:
                    hostsProxyHeaders:
                        - "X-Forwarded-Host"
                    referrerPolicy: "same-origin"

            https-redirect:
                redirectscheme:
                    scheme: https 
   
            nextcloud-chain:
                chain:
                    middlewares:
                        # - ... (e.g. rate limiting middleware)
                        - https-redirect
                        - nextcloud-secure-headers
    ```

---

Of course you need to modify `<your-nextcloud-domain>` in the `nextcloud.yml` to the domain on which you want to run Nextcloud. Also make sure to adjust the port `11000` to match the chosen `APACHE_PORT`. 

**Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network (if you are using a firewall on the server, you need to open ports 80 and 443 for the reverse proxy in that case manually). ***If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)***

**Hint**: see https://www.youtube.com/watch?v=VLPSRrLMDmA for a video on configuring Traefik.

</details>

### Others

<details>

<summary>click here to expand</summary>

Config examples for other reverse proxies are currently not documented. Pull requests are welcome!

</details>

## 2. Use this startup command

After adjusting your reverse proxy config, use the following command to start AIO:<br>

(For a docker-compose example, see the example further [below](#inspiration-for-a-docker-compose-file).)

```
# For Linux:
sudo docker run \
--init \
--sig-proxy=false \
--name nextcloud-aio-mastercontainer \
--restart always \
--publish 8080:8080 \
--env APACHE_PORT=11000 \
--env APACHE_IP_BINDING=0.0.0.0 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest
```

Note: You may be interested in adjusting Nextcloud’s datadir to store the files in a different location than the default docker volume. See [this documentation](https://github.com/nextcloud/all-in-one#how-to-change-the-default-location-of-nextclouds-datadir) on how to do it.

You should also think about limiting the apache container to listen only on localhost in case the reverse proxy is running on the same host and in the host network, by providing an additional environmental variable to this docker run command. See [point 3](#3-limit-the-access-to-the-apache-container).

On macOS see https://github.com/nextcloud/all-in-one#how-to-run-aio-on-macos.

<details>

<summary>Command for Windows</summary>

On Windows, install [Docker Desktop](https://www.docker.com/products/docker-desktop/) (and don't forget to [enable ipv6](https://github.com/nextcloud/all-in-one/blob/main/docker-ipv6-support.md) if you should need that) and run the following command in the command prompt:

```
docker run ^
--init ^
--sig-proxy=false ^
--name nextcloud-aio-mastercontainer ^
--restart always ^
--publish 8080:8080 ^
--env APACHE_PORT=11000 ^
--env APACHE_IP_BINDING=0.0.0.0 ^
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config ^
--volume //var/run/docker.sock:/var/run/docker.sock:ro ^
nextcloud/all-in-one:latest
```

Also, you may be interested in adjusting Nextcloud's Datadir to store the files on the host system. See [this documentation](https://github.com/nextcloud/all-in-one#how-to-change-the-default-location-of-nextclouds-datadir) on how to do it.

</details>

On Synology DSM see https://github.com/nextcloud/all-in-one#how-to-run-aio-on-synology-dsm

### Inspiration for a docker-compose file

Simply translate the docker run command into a docker-compose file. You can have a look at [this file](https://github.com/nextcloud/all-in-one/blob/main/compose.yaml) for some inspiration but you will need to modify it either way. You can find further examples here: https://github.com/nextcloud/all-in-one/discussions/588

## 3. Limit the access to the apache container

Use this environment variable during the initial startup of the mastercontainer to make the apache container only listen on localhost: `--env APACHE_IP_BINDING=127.0.0.1`. **Attention:** This is only recommended to be set if you use `localhost` in your reverse proxy config to connect to your AIO instance. If you use an ip-address instead of localhost, you should set it to `0.0.0.0`.

## 4. Open the AIO interface.
After starting AIO, you should be able to access the AIO Interface via `https://ip.address.of.the.host:8080`. Enter your domain that you've entered in the reverse proxy config and you should be done. Please do not forget to open/forward port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

## 5. Optional: get a valid certificate for the AIO interface

If you want to also access your AIO interface publicly with a valid certificate, you can add e.g. the following config to your Caddyfile:

```
https://<your-nc-domain>:8443 {
    reverse_proxy https://localhost:8080 {
        transport http {
            tls_insecure_skip_verify
        }
    }
}
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option or not possible for you (like e.g. on Windows or if the reverse proxy is running on a different host), you can alternatively instead of `localhost` use the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)

Afterwards should the AIO interface be accessible via `https://ip.address.of.the.host:8443`. You can alternatively change the domain to a different subdomain by using `https://<your-alternative-domain>:443` instead of `https://<your-nc-domain>:8443` in the Caddyfile and use that to access the AIO interface.

## 6. How to debug things?
If something does not work, follow the steps below:
1. Make sure to exactly follow the whole reverse proxy documentation step-for-step from top to bottom!
1. Make sure that you used the docker run command that is described in this reverse proxy documentation. **Hint:** make sure that you have set the `APACHE_PORT` via e.g. `--env APACHE_PORT=11000` during the docker run command!
1. Make sure to set the `APACHE_IP_BINDING` variable correctly. If in doubt, set it to `--env APACHE_IP_BINDING=0.0.0.0`
1. Make sure that all ports to which your reverse proxy is pointing match the chosen `APACHE_PORT`.
1. Make sure that the reverse proxy is running on the host OS or if running in a container, connected to the host network. If that is not possible (e.g. on Windows or if the reverse proxy is running on a different host), substitute `localhost` or `127.0.0.1` in the default configurations by the private ip-address of the host that is running the docker daemon. If you are not sure how to retrieve that, you can run: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'`. If the command returns a public ip-address, use `ip a | grep "scope global" | grep docker0 | awk '{print $2}' | sed 's|/.*||'` instead (the commands only work on Linux)
1. Make sure that the mastercontainer is able to spawn other containers. You can do so by checking that the mastercontainer indeed has access to the Docker socket which might not be positioned in one of the suggested directories like `/var/run/docker.sock` but in a different directory, based on your OS and the way how you installed Docker. The mastercontainer logs should help figuring this out. You can have a look at them by running `sudo docker logs nextcloud-aio-mastercontainer` after the container is started the first time.
1. Check if after the mastercontainer was started, the reverse proxy if running inside a container, can reach the provided apache port. You can test this by running `nc -z localhost 11000; echo $?` from inside the reverse proxy container. If the output is `0`, everything works. Alternatively you can of course use instead of `localhost` the ip-address of the host here for the test.
1. Make sure that you are not behind CGNAT. If that is the case, you will not be able to open ports properly. In that case you might use a Cloudflare Tunnel.
1. If you use Cloudflare, you might need to skip the domain validation anyways since it is known that Cloudflare might block the validation attempts. In that case, see the last option below.
1. If your reverse proxy is configured to use the host network (as recommended in the above docs) or running on the host, make sure that you've configured your firewall to open port 443 and 80.
1. Check if you have a public IPv4- and public IPv6-address. If you only have a public IPv6-address (e.g. due to DS-Lite), make sure to enable IPv6 in Docker and your whole networking infrastructure (e.g. also by adding an AAAA DNS-entry to your domain).
1. Try to configure everything from scratch if it still does not work by following https://github.com/nextcloud/all-in-one#how-to-properly-reset-the-instance.
1. As last resort, you may disable the domain validation by adding `--env SKIP_DOMAIN_VALIDATION=true` to the docker run command. But only use this if you are completely sure that you've correctly configured everything!

